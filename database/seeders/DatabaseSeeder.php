<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\FiscalPeriod;
use App\Models\InventoryLocation;
use App\Models\ProcurementSupplier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoPassword = env('ABPMS_DEMO_PASSWORD');
        if (! is_string($demoPassword) || strlen($demoPassword) < 16) {
            throw new RuntimeException('Set ABPMS_DEMO_PASSWORD to a strong local-only value before seeding demonstration accounts.');
        }

        $permissions = config('abpms.permissions');
        foreach (config('abpms.role_labels') as $name => $label) {
            Role::updateOrCreate(['name' => $name], ['label' => $label, 'description' => $label.' access profile.', 'permissions' => $permissions[$name] ?? ['*'], 'is_active' => true]);
        }

        $users = [
            ['name' => 'System Administrator', 'email' => 'admin@abpms.local', 'role' => 'system_administrator'],
            ['name' => 'MAO Administrator', 'email' => 'mao@abpms.local', 'role' => 'mao_administrator'],
            ['name' => 'Budget Officer', 'email' => 'budget@abpms.local', 'role' => 'budget_officer'],
            ['name' => 'Procurement Officer', 'email' => 'procurement@abpms.local', 'role' => 'procurement_officer'],
            ['name' => 'Municipal Auditor', 'email' => 'auditor@abpms.local', 'role' => 'auditor'],
        ];
        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user + ['password' => Hash::make($demoPassword), 'status' => 'active']);
        }

        $period = FiscalPeriod::updateOrCreate(['year' => 2026], ['name' => 'FY 2026 — Annual Agriculture Program', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $budgetOfficer = User::where('email', 'budget@abpms.local')->firstOrFail();
        BudgetAllocation::updateOrCreate(['reference' => 'BUD-2026-SEEDS'], ['fiscal_period_id' => $period->id, 'program_code' => 'AGRI-SEED-01', 'program_name' => 'Certified Seed Support', 'funding_category' => 'Agricultural Inputs', 'description' => 'Seed assistance and crop production inputs for Bongabong farmers.', 'authorized_amount' => '450000.00', 'status' => 'active', 'created_by' => $budgetOfficer->id]);
        BudgetAllocation::updateOrCreate(['reference' => 'BUD-2026-EQUIP'], ['fiscal_period_id' => $period->id, 'program_code' => 'AGRI-EQUIP-02', 'program_name' => 'Farm Equipment Support', 'funding_category' => 'Agricultural Equipment', 'description' => 'Small farm equipment acquisition for municipal programs.', 'authorized_amount' => '320000.00', 'status' => 'active', 'created_by' => $budgetOfficer->id]);

        ProcurementSupplier::updateOrCreate(['name' => 'Bongabong Agri Supply Cooperative'], ['contact_person' => 'Maria Santos', 'email' => 'sales@bongabongagri.local', 'phone' => '(043) 123-4567', 'address' => 'Bongabong Public Market, Oriental Mindoro', 'registration_reference' => 'BAC-SUP-2026-001', 'is_active' => true]);
        ProcurementSupplier::updateOrCreate(['name' => 'Mindoro Machinery and Tools'], ['contact_person' => 'Rogelio Cruz', 'email' => 'orders@mindorotools.local', 'phone' => '(043) 765-4321', 'address' => 'Calapan City, Oriental Mindoro', 'registration_reference' => 'BAC-SUP-2026-002', 'is_active' => true]);
        InventoryLocation::updateOrCreate(['code' => 'MAO-WH'], ['name' => 'MAO Central Warehouse', 'is_active' => true]);
        InventoryLocation::updateOrCreate(['code' => 'MAO-OPS'], ['name' => 'Agriculture Operations Store', 'is_active' => true]);
    }
}
