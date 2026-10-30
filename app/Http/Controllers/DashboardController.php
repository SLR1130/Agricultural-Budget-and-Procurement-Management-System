<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\EquipmentAsset;
use App\Models\FiscalPeriod;
use App\Models\InventoryMovement;
use App\Models\ProcurementRequest;
use App\Models\ProcurementSupplier;
use App\Models\PurchaseOrder;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly BudgetService $budget) {}

    public function index(Request $request)
    {
        $allocations = BudgetAllocation::with('period')->latest()->get();
        $allocationRows = $allocations->map(fn ($allocation) => ['model' => $allocation, 'summary' => $this->budget->summary($allocation)]);

        $supplierQuery = ProcurementSupplier::query();
        if ($request->filled('supplier_search')) {
            $supplierQuery->where(fn ($query) => $query->where('name', 'like', '%'.$request->string('supplier_search').'%')->orWhere('contact_person', 'like', '%'.$request->string('supplier_search').'%')->orWhere('registration_reference', 'like', '%'.$request->string('supplier_search').'%'));
        }
        if ($request->filled('supplier_status') && in_array($request->supplier_status, ['active', 'inactive'], true)) {
            $supplierQuery->where('is_active', $request->supplier_status === 'active');
        }

        return view('dashboard', [
            'section' => $request->query('section', 'overview'),
            'periods' => FiscalPeriod::latest('year')->get(),
            'allocations' => $allocationRows,
            'requests' => ProcurementRequest::with(['requester', 'allocation'])->latest()->limit(20)->get(),
            'orders' => PurchaseOrder::with(['supplier', 'request'])->latest()->limit(20)->get(),
            'suppliers' => $supplierQuery->latest()->get(),
            'movements' => InventoryMovement::latest()->limit(20)->get(),
            'assets' => EquipmentAsset::latest()->limit(20)->get(),
            'auditLogs' => AuditLog::with('user')->latest()->limit(30)->get(),
            'metrics' => [
                'authorized' => $allocationRows->sum(fn ($row) => (int) $row['summary']['authorized_cents']),
                'available' => $allocationRows->sum(fn ($row) => (int) $row['summary']['available_cents']),
                'requests' => ProcurementRequest::count(),
                'pending' => ProcurementRequest::whereIn('status', ['submitted', 'under_review'])->count(),
                'orders' => PurchaseOrder::whereIn('status', ['approved', 'ordered'])->count(),
                'assets' => EquipmentAsset::count(),
            ],
        ]);
    }
}
