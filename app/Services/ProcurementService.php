<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\EquipmentAsset;
use App\Models\InventoryMovement;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementReceiptItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplyBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(private readonly BudgetService $budget) {}

    public function createRequest(array $data, User $actor): ProcurementRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['idempotency_key']) && ($existing = ProcurementRequest::where('idempotency_key', $data['idempotency_key'])->first())) {
                return $existing->load('items');
            }

            $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['description'] ?? null)));
            if ($items === []) throw ValidationException::withMessages(['items' => 'At least one procurement item is required.']);

            $allocation = BudgetAllocation::query()->where('status', 'active')->findOrFail($data['budget_allocation_id']);
            $totalCents = 0;
            foreach ($items as $item) {
                if ($this->budget->cents($item['quantity']) <= 0 || $this->budget->cents($item['estimated_unit_price']) < 0) {
                    throw ValidationException::withMessages(['items' => 'Quantities must be greater than zero and prices cannot be negative.']);
                }
                $totalCents += $this->budget->cents($this->budget->lineTotal($item['quantity'], $item['estimated_unit_price']));
            }
            $this->budget->assertAvailable($allocation, $totalCents);

            $request = ProcurementRequest::create([
                'request_number' => $this->reference('PR'),
                'budget_allocation_id' => $allocation->id,
                'requester_id' => $actor->id,
                'requesting_office' => $data['requesting_office'],
                'purpose' => $data['purpose'],
                'justification' => $data['justification'] ?? null,
                'estimated_total' => $this->budget->money($totalCents),
                'status' => 'submitted',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($items as $item) {
                ProcurementRequestItem::create([
                    'procurement_request_id' => $request->id,
                    'item_type' => $item['item_type'] ?? 'supply',
                    'description' => $item['description'],
                    'unit' => $item['unit'] ?? 'unit',
                    'quantity' => $item['quantity'],
                    'estimated_unit_price' => $item['estimated_unit_price'],
                    'estimated_total' => $this->budget->lineTotal($item['quantity'], $item['estimated_unit_price']),
                ]);
            }

            $this->requestHistory($request, null, 'submitted', $actor, 'Procurement request submitted.');
            AuditLog::record('created', 'ProcurementRequest', $request->id, $request->request_number);
            return $request->load('items');
        });
    }

    public function reviewRequest(ProcurementRequest $procurementRequest, string $status, ?string $notes, User $actor): ProcurementRequest
    {
        if (! in_array($status, ['under_review', 'approved', 'rejected'], true)) throw ValidationException::withMessages(['status' => 'Unsupported request status.']);
        if ($status === 'rejected' && blank($notes)) throw ValidationException::withMessages(['review_notes' => 'A rejection reason is required.']);

        return DB::transaction(function () use ($procurementRequest, $status, $notes, $actor) {
            $request = ProcurementRequest::query()->lockForUpdate()->findOrFail($procurementRequest->id);
            if (! in_array($request->status, ['submitted', 'under_review'], true)) throw ValidationException::withMessages(['status' => 'This request is no longer reviewable.']);
            if ($status === 'approved' && (int) $request->requester_id === (int) $actor->id) throw ValidationException::withMessages(['status' => 'A requester cannot approve their own request.']);
            if ($status === 'approved') {
                $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($request->budget_allocation_id);
                $this->budget->assertAvailable($allocation, $this->budget->cents($request->estimated_total));
            }

            $from = $request->status;
            $request->update(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes]);
            $this->requestHistory($request, $from, $status, $actor, $notes);
            AuditLog::record($status, 'ProcurementRequest', $request->id, $request->request_number, ['notes' => $notes]);
            return $request->refresh();
        });
    }

    public function createPurchaseOrder(ProcurementRequest $procurementRequest, array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($procurementRequest, $data, $actor) {
            $request = ProcurementRequest::query()->lockForUpdate()->with('items')->findOrFail($procurementRequest->id);
            if ($request->status !== 'approved') throw ValidationException::withMessages(['procurement_request_id' => 'Only approved requests may create purchase orders.']);
            if (! empty($data['idempotency_key']) && ($existing = PurchaseOrder::where('idempotency_key', $data['idempotency_key'])->first())) return $existing->load('items');
            if ($request->purchaseOrders()->whereNotIn('status', ['cancelled'])->exists()) throw ValidationException::withMessages(['procurement_request_id' => 'An active purchase order already exists for this request.']);

            $supplier = \App\Models\ProcurementSupplier::where('is_active', true)->findOrFail($data['supplier_id']);
            $order = PurchaseOrder::create([
                'purchase_order_number' => $this->reference('PO'),
                'procurement_request_id' => $request->id,
                'supplier_id' => $supplier->id,
                'created_by' => $actor->id,
                'total_amount' => $request->estimated_total,
                'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
            foreach ($request->items as $item) {
                PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'procurement_request_item_id' => $item->id, 'item_type' => $item->item_type, 'description' => $item->description, 'unit' => $item->unit, 'ordered_quantity' => $item->quantity, 'unit_price' => $item->estimated_unit_price, 'line_total' => $item->estimated_total]);
            }
            $this->orderHistory($order, null, 'draft', $actor, 'Purchase order drafted.');
            AuditLog::record('created', 'PurchaseOrder', $order->id, $order->purchase_order_number);
            return $order->load('items');
        });
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('request')->findOrFail($purchaseOrder->id);
            if ($order->status !== 'draft') throw ValidationException::withMessages(['status' => 'Only draft purchase orders can be approved.']);
            if ((int) $order->request->requester_id === (int) $actor->id) throw ValidationException::withMessages(['status' => 'A requester cannot approve the related purchase order.']);
            $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($order->request->budget_allocation_id);
            $this->budget->commit($allocation, $this->budget->cents($order->total_amount), $order, $actor);
            $order->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->orderHistory($order, 'draft', 'approved', $actor, 'Purchase order approved and budget committed.');
            AuditLog::record('approved', 'PurchaseOrder', $order->id, $order->purchase_order_number);
            return $order->refresh();
        });
    }

    public function orderPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($order->status !== 'approved') throw ValidationException::withMessages(['status' => 'Only approved purchase orders can be placed.']);
            $order->update(['status' => 'ordered', 'ordered_on' => now()->toDateString()]);
            $this->orderHistory($order, 'approved', 'ordered', $actor, 'Purchase order placed with supplier.');
            AuditLog::record('ordered', 'PurchaseOrder', $order->id, $order->purchase_order_number);
            return $order->refresh();
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('request')->findOrFail($purchaseOrder->id);
            if (! in_array($order->status, ['draft', 'approved', 'ordered'], true)) throw ValidationException::withMessages(['status' => 'This purchase order cannot be cancelled in its current state.']);
            if ($order->items()->where('received_quantity', '>', 0)->exists()) throw ValidationException::withMessages(['status' => 'A purchase order with received goods cannot be cancelled.']);
            $from = $order->status;
            if (in_array($from, ['approved', 'ordered'], true)) {
                $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($order->request->budget_allocation_id);
                $this->budget->releaseCommitment($allocation, $this->budget->cents($order->total_amount), $order, $actor);
            }
            $order->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now()]);
            $this->orderHistory($order, $from, 'cancelled', $actor, 'Purchase order cancelled.');
            AuditLog::record('cancelled', 'PurchaseOrder', $order->id, $order->purchase_order_number);
            return $order->refresh();
        });
    }

    public function receive(PurchaseOrderItem $purchaseOrderItem, array $data, User $actor): ProcurementReceipt
    {
        return DB::transaction(function () use ($purchaseOrderItem, $data, $actor) {
            if ($existing = ProcurementReceipt::where('idempotency_key', $data['idempotency_key'])->first()) return $existing->load('items');
            $item = PurchaseOrderItem::query()->lockForUpdate()->with('order')->findOrFail($purchaseOrderItem->id);
            if (! in_array($item->order->status, ['approved', 'ordered'], true)) throw ValidationException::withMessages(['purchase_order_item_id' => 'Only approved or ordered purchase orders may be received.']);
            $quantity = $this->budget->cents($data['quantity']);
            $ordered = $this->budget->cents($item->ordered_quantity);
            $received = $this->budget->cents($item->received_quantity);
            if ($quantity <= 0) throw ValidationException::withMessages(['quantity' => 'Receipt quantity must be greater than zero.']);
            if ($received + $quantity > $ordered) throw ValidationException::withMessages(['quantity' => 'Receipt quantity exceeds the remaining ordered quantity.']);

            $receipt = ProcurementReceipt::create(['receipt_reference' => $this->reference('GRN'), 'purchase_order_id' => $item->purchase_order_id, 'received_by' => $actor->id, 'received_on' => $data['received_on'], 'notes' => $data['notes'] ?? null, 'idempotency_key' => $data['idempotency_key']]);
            $receiptItem = ProcurementReceiptItem::create(['procurement_receipt_id' => $receipt->id, 'purchase_order_item_id' => $item->id, 'quantity' => $data['quantity'], 'batch_number' => $data['batch_number'] ?? null, 'expires_on' => $data['expires_on'] ?? null, 'inventory_location_id' => $data['inventory_location_id'] ?? null, 'notes' => $data['item_notes'] ?? null]);
            $item->update(['received_quantity' => $this->budget->money($received + $quantity)]);

            if ($item->item_type === 'equipment') {
                $asset = EquipmentAsset::create(['asset_tag' => $this->reference('AST'), 'name' => $item->description, 'acquired_on' => $data['received_on'], 'acquisition_cost' => $this->budget->lineTotal($data['quantity'], $item->unit_price), 'purchase_order_reference' => $item->order->purchase_order_number, 'purchase_order_item_id' => $item->id, 'status' => 'active', 'location' => $data['location'] ?? null]);
                $receiptItem->update(['notes' => trim(($receiptItem->notes ? $receiptItem->notes.' ' : '').'Equipment asset '.$asset->asset_tag.' created.')]);
            } else {
                $movement = InventoryMovement::create(['movement_reference' => $this->reference('MOV'), 'movement_type' => 'receipt', 'item_description' => $item->description, 'quantity' => $data['quantity'], 'inventory_location_id' => $data['inventory_location_id'] ?? null, 'procurement_receipt_id' => $receipt->id, 'performed_by' => $actor->id]);
                $receiptItem->update(['inventory_movement_id' => $movement->id]);
                SupplyBatch::create(['batch_number' => $data['batch_number'] ?? $receipt->receipt_reference, 'item_description' => $item->description, 'quantity_received' => $data['quantity'], 'quantity_available' => $data['quantity'], 'inventory_location_id' => $data['inventory_location_id'] ?? null, 'procurement_receipt_id' => $receipt->id, 'expires_on' => $data['expires_on'] ?? null]);
            }
            AuditLog::record('received', 'ProcurementReceipt', $receipt->id, $receipt->receipt_reference, ['quantity' => $data['quantity']]);
            return $receipt->load('items');
        });
    }

    private function requestHistory(ProcurementRequest $request, ?string $from, string $to, User $actor, ?string $notes): void
    {
        ProcurementStatusHistory::create(['procurement_request_id' => $request->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'processed_by' => $actor->id]);
    }

    private function orderHistory(PurchaseOrder $order, ?string $from, string $to, User $actor, ?string $notes): void
    {
        ProcurementStatusHistory::create(['purchase_order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'processed_by' => $actor->id]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
