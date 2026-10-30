<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\EquipmentAsset;
use App\Models\InventoryMovement;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ABPMSWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_and_dashboard_are_role_aware(): void
    {
        $this->get('/')->assertOk()->assertSee('Clearer budgets');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@abpms.local', 'password' => 'testing-password-2026'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Authorized budget')->assertSee('ABPMS');
    }

    public function test_budget_adjustment_is_decimal_safe_and_cannot_go_negative(): void
    {
        $officer = User::where('email', 'budget@abpms.local')->firstOrFail();
        $allocation = BudgetAllocation::where('reference', 'BUD-2026-SEEDS')->firstOrFail();

        $this->actingAs($officer)->post('/allocations/'.$allocation->id.'/adjust', ['direction' => 'credit', 'amount' => '125.55', 'description' => 'Authorized supplemental seed funding.'])->assertRedirect();
        $this->assertDatabaseHas('budget_transactions', ['entry_type' => 'adjustment', 'amount' => '125.55']);

        $this->actingAs($officer)->post('/allocations/'.$allocation->id.'/adjust', ['direction' => 'debit', 'amount' => '99999999', 'description' => 'Invalid overdraw attempt.'])->assertSessionHasErrors('budget_allocation_id');
    }

    public function test_request_approval_requires_separation_of_duties_and_rejection_notes(): void
    {
        $requester = User::where('email', 'mao@abpms.local')->firstOrFail();
        $request = $this->createRequest($requester, 'separation-test', 'supply');

        $this->actingAs($requester)->post('/requests/'.$request->id.'/review', ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->actingAs(User::where('email', 'admin@abpms.local')->firstOrFail())->post('/requests/'.$request->id.'/review', ['status' => 'rejected'])->assertSessionHasErrors('review_notes');
    }

    public function test_purchase_order_commitment_and_partial_supply_receipts_are_idempotent(): void
    {
        $requester = User::where('email', 'procurement@abpms.local')->firstOrFail();
        $approver = User::where('email', 'mao@abpms.local')->firstOrFail();
        $request = $this->createRequest($requester, 'supply-workflow', 'supply');

        $this->actingAs($approver)->post('/requests/'.$request->id.'/review', ['status' => 'approved'])->assertRedirect();
        $supplier = \App\Models\ProcurementSupplier::firstOrFail();
        $this->actingAs($requester)->post('/requests/'.$request->id.'/purchase-orders', ['supplier_id' => $supplier->id, 'idempotency_key' => 'po-supply-workflow'])->assertRedirect();
        $order = PurchaseOrder::where('idempotency_key', 'po-supply-workflow')->firstOrFail();
        $this->actingAs($approver)->post('/purchase-orders/'.$order->id.'/approve')->assertRedirect();
        $this->assertDatabaseHas('budget_transactions', ['purchase_order_id' => $order->id, 'entry_type' => 'commitment', 'direction' => 'debit']);
        $this->actingAs($requester)->post('/purchase-orders/'.$order->id.'/order')->assertRedirect();

        $item = $order->fresh('items')->items->firstOrFail();
        $payload = ['purchase_order_item_id' => $item->id, 'quantity' => '2', 'received_on' => '2026-10-30', 'idempotency_key' => 'grn-supply-exactly-once', 'batch_number' => 'BATCH-001'];
        $this->actingAs($requester)->post('/receipts', $payload)->assertRedirect();
        $this->actingAs($requester)->post('/receipts', $payload)->assertRedirect();
        $this->assertDatabaseCount('procurement_receipts', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'received_quantity' => '2.00']);
    }

    public function test_equipment_receipt_creates_asset_without_supply_movement(): void
    {
        $requester = User::where('email', 'procurement@abpms.local')->firstOrFail();
        $approver = User::where('email', 'mao@abpms.local')->firstOrFail();
        $request = $this->createRequest($requester, 'equipment-workflow', 'equipment');
        $this->actingAs($approver)->post('/requests/'.$request->id.'/review', ['status' => 'approved'])->assertRedirect();
        $supplier = \App\Models\ProcurementSupplier::firstOrFail();
        $this->actingAs($requester)->post('/requests/'.$request->id.'/purchase-orders', ['supplier_id' => $supplier->id, 'idempotency_key' => 'po-equipment-workflow'])->assertRedirect();
        $order = PurchaseOrder::where('idempotency_key', 'po-equipment-workflow')->firstOrFail();
        $this->actingAs($approver)->post('/purchase-orders/'.$order->id.'/approve')->assertRedirect();
        $this->actingAs($requester)->post('/purchase-orders/'.$order->id.'/order')->assertRedirect();
        $item = $order->fresh('items')->items->firstOrFail();

        $this->actingAs($requester)->post('/receipts', ['purchase_order_item_id' => $item->id, 'quantity' => '1', 'received_on' => '2026-10-30', 'idempotency_key' => 'grn-equipment', 'location' => 'MAO Operations Store'])->assertRedirect();
        $this->assertDatabaseCount('equipment_assets', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('equipment_assets', ['purchase_order_item_id' => $item->id, 'name' => 'Portable irrigation pump']);
    }

    public function test_supplier_directory_supports_registration_editing_and_filtering(): void
    {
        $officer = User::where('email', 'procurement@abpms.local')->firstOrFail();
        $this->actingAs($officer)->post('/suppliers', ['name' => 'New Field Partner', 'contact_person' => 'Ana Reyes', 'email' => 'ana@partner.local', 'phone' => '555-0100', 'address' => 'Bongabong', 'registration_reference' => 'SUP-TEST-001'])->assertRedirect();
        $supplier = \App\Models\ProcurementSupplier::where('name', 'New Field Partner')->firstOrFail();
        $this->actingAs($officer)->patch('/suppliers/'.$supplier->id, ['name' => 'Updated Field Partner', 'contact_person' => 'Ana Reyes', 'email' => 'ana@partner.local', 'phone' => '555-0101', 'address' => 'Bongabong', 'registration_reference' => 'SUP-TEST-001', 'is_active' => '1'])->assertRedirect();
        $this->assertDatabaseHas('procurement_suppliers', ['id' => $supplier->id, 'name' => 'Updated Field Partner', 'phone' => '555-0101']);
        $this->get('/suppliers?supplier_search=Updated')->assertRedirect()->assertRedirectContains('supplier_search=Updated');
    }

    private function createRequest(User $actor, string $key, string $itemType): ProcurementRequest
    {
        $allocation = BudgetAllocation::where('reference', 'BUD-2026-SEEDS')->firstOrFail();
        $this->actingAs($actor)->post('/requests', [
            'budget_allocation_id' => $allocation->id,
            'requesting_office' => 'Municipal Agriculture Office',
            'purpose' => 'Support a scheduled agriculture field activity.',
            'justification' => 'Required for the approved seasonal work plan.',
            'idempotency_key' => $key,
            'items' => [['item_type' => $itemType, 'description' => $itemType === 'equipment' ? 'Portable irrigation pump' : 'Certified rice seed', 'unit' => 'unit', 'quantity' => '4', 'estimated_unit_price' => $itemType === 'equipment' ? '5000' : '100']],
        ])->assertRedirect();

        return ProcurementRequest::where('idempotency_key', $key)->firstOrFail();
    }
}
