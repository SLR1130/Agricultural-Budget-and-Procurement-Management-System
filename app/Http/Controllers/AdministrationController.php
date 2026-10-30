<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdministrationController extends Controller
{
    public function users() { return view('admin.users', ['users' => User::latest()->get(), 'roles' => config('abpms.role_labels')]); }

    public function storeUser(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:40'], 'role' => ['required', 'in:system_administrator,mao_administrator,budget_officer,procurement_officer,auditor'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $data['password'] = Hash::make($data['password']);
        User::create($data + ['status' => 'active']);
        AuditLog::record('created', 'User', null, $data['email']);
        return back()->with('success', 'User account created.');
    }

    public function updateStatus(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot deactivate your own account.');
        $data = $request->validate(['status' => ['required', 'in:active,inactive']]);
        $user->update($data);
        AuditLog::record('status_changed', 'User', $user->id, $user->email, $data);
        return back()->with('success', 'Account status updated.');
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', 'in:system_administrator,mao_administrator,budget_officer,procurement_officer,auditor']]);
        $user->update($data);
        AuditLog::record('role_changed', 'User', $user->id, $user->email, $data);
        return back()->with('success', 'Role updated.');
    }

    public function roles() { return view('admin.roles', ['roles' => Role::orderBy('label')->get(), 'roleLabels' => config('abpms.role_labels'), 'permissions' => config('abpms.permissions')]); }
    public function auditLogs() { return view('dashboard', ['section' => 'audit', 'auditLogs' => AuditLog::with('user')->latest()->paginate(50), 'periods' => collect(), 'allocations' => collect(), 'requests' => collect(), 'orders' => collect(), 'suppliers' => collect(), 'movements' => collect(), 'assets' => collect(), 'metrics' => array_fill_keys(['authorized', 'available', 'requests', 'pending', 'orders', 'assets'], 0)]); }
}
