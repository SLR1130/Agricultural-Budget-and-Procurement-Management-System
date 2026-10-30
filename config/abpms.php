<?php

return [
    'permissions' => [
        'mao_administrator' => ['dashboard.view', 'budget.manage', 'budget.approve', 'procurement.manage', 'procurement.approve', 'reports.view', 'audit.view'],
        'budget_officer' => ['dashboard.view', 'budget.manage', 'budget.adjust', 'reports.view'],
        'procurement_officer' => ['dashboard.view', 'procurement.manage', 'receiving.manage'],
        'auditor' => ['dashboard.view', 'reports.view', 'audit.view'],
    ],
    'role_labels' => [
        'system_administrator' => 'System Administrator',
        'mao_administrator' => 'MAO Administrator',
        'budget_officer' => 'Budget Officer',
        'procurement_officer' => 'Procurement Officer',
        'auditor' => 'Auditor',
    ],
];
