<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::permissions() as $code => $definition) {
            Permission::updateOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'group_name' => $definition['group']],
            );
        }

        // Stations are created only from station-type contracts; standalone creation was intentionally removed.
        Permission::where('code', 'stations.create')->delete();
        $roles = [
            'admin' => ['name' => 'مدير النظام', 'all' => true],
            'finance' => ['name' => 'الإدارة المالية', 'groups' => ['dashboard', 'customers', 'contracts', 'addendums', 'receivables', 'collections', 'discount-vouchers', 'purchases', 'expenses', 'bank-statements', 'reports', 'intermediaries', 'attachments'], 'permissions' => ['products.view', 'stations.view','customer-success.view','customer-success.dashboard','customer-success.flags.manage']],
            'collector' => ['name' => 'مسؤول التحصيل', 'permissions' => ['dashboard.view', 'customers.view', 'contracts.view', 'receivables.view', 'collections.view', 'collections.create', 'collections.update', 'reports.contracts', 'reports.statements', 'reports.exports', 'attachments.download']],
            'sales' => ['name' => 'المبيعات', 'groups' => ['dashboard', 'customers', 'quotations', 'contracts', 'products', 'pricing-offers', 'intermediaries', 'attachments', 'sales-leads', 'tasks'], 'permissions' => ['customer-success.view','customer-success.dashboard','customer-success.signals.manage'], 'exclude' => ['sales-leads.view-assigned-only']],
            'purchases' => ['name' => 'المشتريات', 'groups' => ['dashboard', 'customers', 'contracts', 'purchases', 'suppliers', 'attachments'], 'permissions' => ['products.view', 'stations.view']],
            'viewer' => ['name' => 'عرض فقط', 'permissions' => ['dashboard.view', 'customers.view', 'quotations.view', 'contracts.view', 'receivables.view', 'collections.view', 'purchases.view', 'expenses.view', 'bank-statements.view', 'stations.view', 'installations.view', 'products.view', 'pricing-offers.view', 'suppliers.view', 'intermediaries.view', 'reports.profitability', 'reports.contracts', 'reports.statements', 'attachments.download']],
            'customer-success' => ['name' => 'متابعة العملاء', 'groups' => ['customer-success', 'tasks'], 'permissions' => ['dashboard.view','customers.view','contracts.view','receivables.view']],
        ];
        foreach ($roles as $code => $definition) {
            $role = Role::updateOrCreate(['code' => $code], ['name' => $definition['name'], 'is_system' => true]);
            if (! empty($definition['all'])) {
                $ids = Permission::pluck('id');
            } else {
                $ids = collect();
                if (! empty($definition['groups'])) {
                    $ids = $ids->merge(Permission::whereIn('group_name', $definition['groups'])->pluck('id'));
                }
                if (! empty($definition['permissions'])) {
                    $ids = $ids->merge(Permission::whereIn('code', $definition['permissions'])->pluck('id'));
                }
                if (! empty($definition['exclude'])) {
                    $excludedIds = Permission::whereIn('code', $definition['exclude'])->pluck('id');
                    $ids = $ids->diff($excludedIds);
                }
                $ids = $ids->unique()->values();
            }
            $role->permissions()->sync($ids);
        }
    }
}
