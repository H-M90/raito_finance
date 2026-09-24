<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'dashboard' => ['view'],
            'customers' => ['view', 'create', 'update'],
            'quotations' => ['view', 'create', 'update', 'status', 'convert', 'reports'],
            'contracts' => ['view', 'create', 'update', 'cancel', 'reopen'],
            'addendums' => ['view', 'create', 'update', 'cancel', 'reopen'],
            'receivables' => ['view', 'create', 'update'],
            'collections' => ['view', 'create', 'update', 'cancel', 'reopen'],
            'discount-vouchers' => ['view', 'create', 'update', 'cancel', 'reopen'],
            'purchases' => ['view', 'create', 'update', 'delete'],
            'expenses' => ['view', 'create', 'update', 'cancel', 'reopen', 'delete'],
            'bank-statements' => ['view', 'create', 'update', 'approve', 'delete'],
            'stations' => ['view', 'update'],
            'installations' => ['view', 'create', 'update', 'cancel', 'reopen'],
            'products' => ['view', 'create', 'update'],
            'pricing-offers' => ['view', 'create', 'update'],
            'suppliers' => ['view', 'create', 'update'],
            'intermediaries' => ['view', 'create', 'update', 'commissions', 'pay-commissions'],
            'reports' => ['profitability', 'contracts', 'statements', 'exports', 'audit'],
            'imports' => ['view', 'create', 'commit', 'cancel'],
            'users' => ['view', 'create', 'update', 'roles'],
            'attachments' => ['download', 'delete'],
            'customer-success' => ['view', 'update', 'dashboard', 'flags.manage', 'health.update', 'contacts.manage', 'playbooks.view', 'playbooks.manage', 'playbooks.run', 'tasks.assign', 'tasks.complete', 'signals.manage'],
            'sales-leads' => ['view', 'create', 'update', 'assign', 'convert', 'tasks'],
            'tasks' => ['view', 'create', 'update', 'complete', 'assign'],
        ];
        foreach ($groups as $group => $actions) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(['code' => "{$group}.{$action}"], ['name' => "{$group} {$action}", 'group_name' => $group]);
            }
        }
        $customerFollowUpPermissionNames = [
            'customer-success.view' => 'عرض متابعة العملاء',
            'customer-success.update' => 'تعديل بيانات متابعة العميل',
            'customer-success.dashboard' => 'عرض لوحة متابعة العملاء',
            'customer-success.flags.manage' => 'إدارة تنبيهات متابعة العميل',
            'customer-success.health.update' => 'تحديث رضا العميل',
            'customer-success.contacts.manage' => 'إدارة جهات اتصال العميل',
            'customer-success.playbooks.view' => 'عرض خطط متابعة العميل',
            'customer-success.playbooks.manage' => 'إدارة وحسم خطط المتابعة',
            'customer-success.playbooks.run' => 'بدء خطة متابعة للعميل',
            'customer-success.tasks.assign' => 'إسناد مهام متابعة العميل',
            'customer-success.tasks.complete' => 'إكمال مهام متابعة العميل',
            'customer-success.signals.manage' => 'إدارة فرص البيع والتوسع للعميل',
        ];
        foreach ($customerFollowUpPermissionNames as $code => $name) {
            Permission::where('code',$code)->update(['name'=>$name]);
        }

        $salesLeadPermissionNames = [
            'sales-leads.view' => 'عرض العملاء المحتملين ورحلة المبيعات',
            'sales-leads.create' => 'إضافة عميل محتمل',
            'sales-leads.update' => 'تحديث رحلة العميل المحتمل',
            'sales-leads.assign' => 'تعيين مسؤول للعميل المحتمل',
            'sales-leads.convert' => 'تحويل العميل المحتمل إلى عميل',
            'sales-leads.tasks' => 'إدارة مهام ومتابعات المبيعات',
        ];
        foreach ($salesLeadPermissionNames as $code => $name) {
            Permission::where('code',$code)->update(['name'=>$name]);
        }

        $taskPermissionNames = [
            'tasks.view' => 'عرض مركز المهام',
            'tasks.create' => 'إضافة مهام',
            'tasks.update' => 'تعديل المهام',
            'tasks.complete' => 'إكمال وإعادة فتح المهام',
            'tasks.assign' => 'إسناد المهام لمستخدمين آخرين',
        ];
        foreach ($taskPermissionNames as $code => $name) {
            Permission::where('code',$code)->update(['name'=>$name]);
        }

        Permission::updateOrCreate(
            ['code' => 'transactions.view-own'],
            ['name' => 'عرض الحركات التي أدخلها المستخدم فقط', 'group_name' => 'transactions'],
        );
        // Stations are created only from station-type contracts; standalone creation was intentionally removed.
        Permission::where('code', 'stations.create')->delete();
        $roles = [
            'admin' => ['name' => 'مدير النظام', 'all' => true],
            'finance' => ['name' => 'الإدارة المالية', 'groups' => ['dashboard', 'customers', 'contracts', 'addendums', 'receivables', 'collections', 'discount-vouchers', 'purchases', 'expenses', 'bank-statements', 'reports', 'intermediaries', 'attachments'], 'permissions' => ['products.view', 'stations.view','customer-success.view','customer-success.dashboard','customer-success.flags.manage']],
            'collector' => ['name' => 'مسؤول التحصيل', 'permissions' => ['dashboard.view', 'customers.view', 'contracts.view', 'receivables.view', 'collections.view', 'collections.create', 'collections.update', 'reports.contracts', 'reports.statements', 'reports.exports', 'attachments.download']],
            'sales' => ['name' => 'المبيعات', 'groups' => ['dashboard', 'customers', 'quotations', 'contracts', 'products', 'pricing-offers', 'intermediaries', 'attachments', 'sales-leads', 'tasks'], 'permissions' => ['customer-success.view','customer-success.dashboard','customer-success.signals.manage']],
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
                $ids = $ids->unique()->values();
            }
            $role->permissions()->sync($ids);
        }
    }
}
