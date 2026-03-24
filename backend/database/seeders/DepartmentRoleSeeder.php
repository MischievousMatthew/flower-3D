<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DepartmentRoleSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'hr' => [
                'name' => 'HR',
                'roles' => [
                    [
                        'name'    => 'HR Manager',
                        'slug'    => 'hr-manager',
                        'level'   => 10,
                        'modules' => ['employees', 'attendance', 'payroll', 'leave'],
                    ],
                ],
            ],
            'finance' => [
                'name' => 'Finance',
                'roles' => [
                    [
                        'name'    => 'Finance Manager',
                        'slug'    => 'finance-manager',
                        'level'   => 10,
                        'modules' => ['funding_requests', 'payroll_requests'],
                    ],
                ],
            ],
            'procurement' => [
                'name' => 'Procurement',
                'roles' => [
                    [
                        'name'    => 'Inventory Manager',
                        'slug'    => 'inventory-manager',
                        'level'   => 8,
                        'modules' => ['products', 'funding_requests'],
                    ],
                    [
                        'name'    => 'Supply Chain Coordinator',
                        'slug'    => 'supply-chain-coordinator',
                        'level'   => 6,
                        'modules' => ['suppliers', 'warehouse', 'orders', 'shipments', 'deliveries', 'barcode'],
                    ],
                ],
            ],
            'crm' => [
                'name' => 'CRM',
                'roles' => [
                    [
                        'name'    => 'CRM Specialist',
                        'slug'    => 'crm-specialist',
                        'level'   => 5,
                        'modules' => ['customers', 'orders'],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $slug => $def) {
            $dept = Department::updateOrCreate(
                ['slug' => $slug],
                ['name' => $def['name'], 'is_active' => true]
            );

            foreach ($def['roles'] as $r) {
                Role::updateOrCreate(
                    ['slug' => $r['slug']],
                    [
                        'department_id'      => $dept->id,
                        'name'               => $r['name'],
                        'hierarchy_level'    => $r['level'],
                        'accessible_modules' => $r['modules'],
                    ]
                );
            }
        }
    }
}
