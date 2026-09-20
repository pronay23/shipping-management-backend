<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = [
            'employee' => ['view', 'create', 'update', 'delete'],
            'voyage' => ['view', 'create', 'update', 'delete'],
            'bill-of-lading' => ['view', 'create', 'update', 'delete'],
            'container-manifest' => ['view'],
            'invoice' => ['view', 'create', 'update', 'delete'],
            'money-receipt' => ['view', 'create', 'update', 'delete'],
            'ap-invoice' => ['view', 'create', 'update', 'delete'],
            'ap-payment' => ['view', 'create', 'update', 'delete'],
            'journal-entry' => ['view', 'create', 'approve', 'void'],
            'chart-of-account' => ['view', 'create', 'update', 'delete'],
            'account-type' => ['view', 'create', 'update', 'delete'],
            'vendor' => ['view', 'create', 'update', 'delete'],
            'vendor-category' => ['view', 'create', 'update', 'delete'],
            'country' => ['view', 'create', 'update', 'delete'],
            'currency' => ['view', 'create', 'update', 'delete'],
            'uom' => ['view', 'create', 'update', 'delete'],
            'item' => ['view', 'create', 'update', 'delete'],
            'reports' => ['view'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'api',
                ]);
            }
        }

        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'api'],
            ['display_name' => 'Super Administrator', 'description' => 'Full system access']
        );
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['display_name' => 'Administrator', 'description' => 'Administrative access, no employee management']
        );
        $adminPermissions = Permission::where('name', '!=', 'employee.create')
            ->where('name', '!=', 'employee.update')
            ->where('name', '!=', 'employee.delete')
            ->get();
        $admin->syncPermissions($adminPermissions);

        $manager = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'api'],
            ['display_name' => 'Manager', 'description' => 'Operational access, limited finance']
        );
        $managerPermissions = Permission::whereIn('name', [
            'employee.view',
            'voyage.view', 'voyage.create', 'voyage.update',
            'bill-of-lading.view', 'bill-of-lading.create', 'bill-of-lading.update',
            'container-manifest.view',
            'invoice.view', 'invoice.create', 'invoice.update',
            'money-receipt.view', 'money-receipt.create', 'money-receipt.update',
            'ap-invoice.view', 'ap-invoice.create', 'ap-invoice.update',
            'ap-payment.view', 'ap-payment.create', 'ap-payment.update',
            'journal-entry.view',
            'chart-of-account.view',
            'account-type.view',
            'vendor.view', 'vendor.create', 'vendor.update',
            'vendor-category.view',
            'country.view',
            'currency.view',
            'uom.view',
            'item.view', 'item.create', 'item.update',
            'reports.view',
        ])->get();
        $manager->syncPermissions($managerPermissions);
    }
}
