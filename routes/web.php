<?php

use App\Http\Controllers\ABPMSController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/budget', fn () => redirect()->route('dashboard', ['section' => 'budget']))->name('budget.index');
    Route::get('/fiscal-periods', fn () => redirect()->route('dashboard', ['section' => 'periods']))->name('periods.index');
    Route::get('/allocations', fn () => redirect()->route('dashboard', ['section' => 'allocations']))->name('allocations.index');
    Route::get('/budget-adjustments', fn () => redirect()->route('dashboard', ['section' => 'adjustments']))->name('adjustments.index');
    Route::get('/budget-ledger', fn () => redirect()->route('dashboard', ['section' => 'ledger']))->name('ledger.index');
    Route::get('/procurement', fn () => redirect()->route('dashboard', ['section' => 'procurement']))->name('procurement.index');
    Route::get('/request-approvals', fn () => redirect()->route('dashboard', ['section' => 'approvals']))->name('approvals.index');
    Route::get('/suppliers', fn (\Illuminate\Http\Request $request) => redirect()->route('dashboard', ['section' => 'suppliers', 'supplier_search' => $request->query('supplier_search'), 'supplier_status' => $request->query('supplier_status')]))->name('suppliers.index');
    Route::get('/purchase-orders', fn () => redirect()->route('dashboard', ['section' => 'orders']))->name('orders.index');
    Route::get('/receiving', fn () => redirect()->route('dashboard', ['section' => 'receiving']))->name('receiving.index');
    Route::get('/equipment-acquisitions', fn () => redirect()->route('dashboard', ['section' => 'equipment']))->name('equipment.index');
    Route::get('/reports', fn () => redirect()->route('dashboard', ['section' => 'reports']))->name('reports.index');
    Route::get('/audit-trail', [AdministrationController::class, 'auditLogs'])->middleware('role:system_administrator,mao_administrator,auditor')->name('audit.index');
    Route::get('/export/budget', [ABPMSController::class, 'export'])->middleware('role:system_administrator,mao_administrator,budget_officer,auditor')->name('reports.export');

    Route::post('/fiscal-periods', [ABPMSController::class, 'storePeriod'])->middleware('role:system_administrator,mao_administrator,budget_officer')->name('periods.store');
    Route::post('/allocations', [ABPMSController::class, 'storeAllocation'])->middleware('role:system_administrator,mao_administrator,budget_officer')->name('allocations.store');
    Route::post('/allocations/{budgetAllocation}/adjust', [ABPMSController::class, 'adjustAllocation'])->middleware('role:system_administrator,mao_administrator,budget_officer')->name('allocations.adjust');
    Route::post('/allocations/{budgetAllocation}/expenditure', [ABPMSController::class, 'expenditure'])->middleware('role:system_administrator,mao_administrator,budget_officer')->name('allocations.expenditure');

    Route::post('/suppliers', [ABPMSController::class, 'storeSupplier'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('suppliers.store');
    Route::patch('/suppliers/{procurementSupplier}', [ABPMSController::class, 'updateSupplier'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('suppliers.update');
    Route::post('/requests', [ABPMSController::class, 'storeRequest'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('requests.store');
    Route::post('/requests/{procurementRequest}/review', [ABPMSController::class, 'reviewRequest'])->middleware('role:system_administrator,mao_administrator')->name('requests.review');
    Route::post('/requests/{procurementRequest}/purchase-orders', [ABPMSController::class, 'storePurchaseOrder'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('orders.store');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [ABPMSController::class, 'approvePurchaseOrder'])->middleware('role:system_administrator,mao_administrator')->name('orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/order', [ABPMSController::class, 'orderPurchaseOrder'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('orders.order');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [ABPMSController::class, 'cancelPurchaseOrder'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('orders.cancel');
    Route::post('/receipts', [ABPMSController::class, 'receive'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('receipts.store');
    Route::post('/locations', [ABPMSController::class, 'storeLocation'])->middleware('role:system_administrator,mao_administrator,procurement_officer')->name('locations.store');

    Route::get('/admin/users', [AdministrationController::class, 'users'])->middleware('role:system_administrator')->name('users.index');
    Route::post('/admin/users', [AdministrationController::class, 'storeUser'])->middleware('role:system_administrator')->name('users.store');
    Route::patch('/admin/users/{user}/status', [AdministrationController::class, 'updateStatus'])->middleware('role:system_administrator')->name('users.status');
    Route::patch('/admin/users/{user}/role', [AdministrationController::class, 'updateRole'])->middleware('role:system_administrator')->name('users.role');
    Route::get('/admin/roles', [AdministrationController::class, 'roles'])->middleware('role:system_administrator')->name('roles.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::view('/settings', 'settings')->name('settings');
});
