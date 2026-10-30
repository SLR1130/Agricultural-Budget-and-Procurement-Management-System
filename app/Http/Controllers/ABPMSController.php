<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\FiscalPeriod;
use App\Models\InventoryLocation;
use App\Models\ProcurementRequest;
use App\Models\ProcurementSupplier;
use App\Models\PurchaseOrder;
use App\Services\BudgetService;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ABPMSController extends Controller
{
    public function __construct(private readonly BudgetService $budget, private readonly ProcurementService $procurement) {}

    public function storePeriod(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2200', 'unique:fiscal_periods,year'], 'name' => ['required', 'string', 'max:120'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'status' => ['required', 'in:open,closed,draft']]);
        FiscalPeriod::create($data);
        return back()->with('success', 'Fiscal period created.');
    }

    public function storeAllocation(Request $request)
    {
        $data = $request->validate(['fiscal_period_id' => ['required', 'exists:fiscal_periods,id'], 'program_code' => ['required', 'string', 'max:80'], 'program_name' => ['required', 'string', 'max:160'], 'funding_category' => ['required', 'string', 'max:120'], 'description' => ['required', 'string'], 'authorized_amount' => ['required', 'numeric', 'gt:0']]);
        $this->budget->createAllocation($data, $request->user());
        return back()->with('success', 'Budget allocation created.');
    }

    public function adjustAllocation(Request $request, BudgetAllocation $budgetAllocation)
    {
        $data = $request->validate(['direction' => ['required', 'in:credit,debit'], 'amount' => ['required', 'numeric', 'gt:0'], 'description' => ['required', 'string', 'min:8']]);
        $this->budget->adjust($budgetAllocation, $data, $request->user());
        return back()->with('success', 'Budget adjustment posted to the ledger.');
    }

    public function expenditure(Request $request, BudgetAllocation $budgetAllocation)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['required', 'string', 'max:100'], 'description' => ['required', 'string']]);
        $this->budget->postExpenditure($budgetAllocation, $data, $request->user());
        return back()->with('success', 'Expenditure posted.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180', 'unique:procurement_suppliers,name'], 'contact_person' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:255'], 'registration_reference' => ['nullable', 'string', 'max:100']]);
        ProcurementSupplier::create($data + ['is_active' => true]);
        return back()->with('success', 'Supplier registered.');
    }

    public function updateSupplier(Request $request, ProcurementSupplier $procurementSupplier)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180', 'unique:procurement_suppliers,name,'.$procurementSupplier->id], 'contact_person' => ['nullable', 'string', 'max:120'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:255'], 'registration_reference' => ['nullable', 'string', 'max:100'], 'is_active' => ['required', 'boolean']]);
        $procurementSupplier->update($data);
        \App\Models\AuditLog::record('updated', 'ProcurementSupplier', $procurementSupplier->id, $procurementSupplier->name);
        return back()->with('success', 'Supplier information updated.');
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate(['budget_allocation_id' => ['required', 'exists:budget_allocations,id'], 'requesting_office' => ['required', 'string', 'max:150'], 'purpose' => ['required', 'string'], 'justification' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:100'], 'items' => ['required', 'array', 'min:1'], 'items.*.item_type' => ['required', 'in:supply,equipment'], 'items.*.description' => ['nullable', 'string', 'max:180'], 'items.*.unit' => ['nullable', 'string', 'max:40'], 'items.*.quantity' => ['nullable', 'numeric', 'gt:0'], 'items.*.estimated_unit_price' => ['nullable', 'numeric', 'gte:0']]);
        $this->procurement->createRequest($data, $request->user());
        return back()->with('success', 'Procurement request submitted.');
    }

    public function reviewRequest(Request $request, ProcurementRequest $procurementRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:under_review,approved,rejected'], 'review_notes' => ['nullable', 'string']]);
        $this->procurement->reviewRequest($procurementRequest, $data['status'], $data['review_notes'] ?? null, $request->user());
        return back()->with('success', 'Request review recorded.');
    }

    public function storePurchaseOrder(Request $request, ProcurementRequest $procurementRequest)
    {
        $data = $request->validate(['supplier_id' => ['required', 'exists:procurement_suppliers,id'], 'expected_delivery_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'idempotency_key' => ['required', 'string', 'max:100']]);
        $this->procurement->createPurchaseOrder($procurementRequest, $data, $request->user());
        return back()->with('success', 'Purchase order drafted.');
    }

    public function approvePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->procurement->approvePurchaseOrder($purchaseOrder, $request->user());
        return back()->with('success', 'Purchase order approved and budget committed.');
    }

    public function orderPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->procurement->orderPurchaseOrder($purchaseOrder, $request->user());
        return back()->with('success', 'Purchase order marked as ordered.');
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->procurement->cancelPurchaseOrder($purchaseOrder, $request->user());
        return back()->with('success', 'Purchase order cancelled and any commitment released.');
    }

    public function receive(Request $request)
    {
        $data = $request->validate(['purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'], 'quantity' => ['required', 'numeric', 'gt:0'], 'received_on' => ['required', 'date'], 'idempotency_key' => ['required', 'string', 'max:100'], 'batch_number' => ['nullable', 'string', 'max:100'], 'expires_on' => ['nullable', 'date'], 'inventory_location_id' => ['nullable', 'exists:inventory_locations,id'], 'location' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string'], 'item_notes' => ['nullable', 'string']]);
        $item = \App\Models\PurchaseOrderItem::findOrFail($data['purchase_order_item_id']);
        $this->procurement->receive($item, $data, $request->user());
        return back()->with('success', 'Delivery receipt posted exactly once.');
    }

    public function export(): StreamedResponse
    {
        $allocations = BudgetAllocation::with('period')->get();
        return response()->streamDownload(function () use ($allocations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Fiscal period', 'Program', 'Authorized amount', 'Available amount', 'Status']);
            foreach ($allocations as $allocation) {
                $summary = $this->budget->summary($allocation);
                fputcsv($handle, [$allocation->reference, $allocation->period?->name, $allocation->program_name, $summary['authorized'], $summary['available'], $allocation->status]);
            }
            fclose($handle);
        }, 'abpms-budget-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:30']]);
        InventoryLocation::create($data + ['is_active' => true]);
        return back()->with('success', 'Receiving location added.');
    }
}
