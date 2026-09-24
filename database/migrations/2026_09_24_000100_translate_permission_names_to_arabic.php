<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $labels = [
            'dashboard.view' => ['عرض لوحة القيادة', 'dashboard'],
            'sales-leads.view' => ['عرض العملاء المحتملين ورحلة المبيعات', 'sales-leads'],
            'sales-leads.create' => ['إضافة عميل محتمل', 'sales-leads'],
            'sales-leads.update' => ['تعديل رحلة العميل المحتمل', 'sales-leads'],
            'sales-leads.assign' => ['إسناد العميل المحتمل لمسؤول', 'sales-leads'],
            'sales-leads.convert' => ['تحويل العميل المحتمل إلى عميل', 'sales-leads'],
            'sales-leads.tasks' => ['إدارة مهام ومتابعات المبيعات', 'sales-leads'],
            'tasks.view' => ['عرض مركز المهام', 'tasks'],
            'tasks.create' => ['إضافة مهمة', 'tasks'],
            'tasks.update' => ['تعديل المهام', 'tasks'],
            'tasks.complete' => ['إكمال وإعادة فتح المهام', 'tasks'],
            'tasks.assign' => ['إسناد المهام لمستخدمين آخرين', 'tasks'],
            'customers.view' => ['عرض العملاء', 'customers'],
            'customers.create' => ['إضافة عميل', 'customers'],
            'customers.update' => ['تعديل بيانات العملاء', 'customers'],
            'customer-success.view' => ['عرض متابعة العملاء', 'customer-success'],
            'customer-success.update' => ['تعديل بيانات متابعة العميل', 'customer-success'],
            'customer-success.dashboard' => ['عرض لوحة متابعة العملاء', 'customer-success'],
            'customer-success.flags.manage' => ['إدارة تنبيهات متابعة العميل', 'customer-success'],
            'customer-success.health.update' => ['تحديث رضا العميل', 'customer-success'],
            'customer-success.contacts.manage' => ['إدارة جهات اتصال العميل', 'customer-success'],
            'customer-success.playbooks.view' => ['عرض خطط متابعة العميل', 'customer-success'],
            'customer-success.playbooks.manage' => ['إدارة وحسم خطط المتابعة', 'customer-success'],
            'customer-success.playbooks.run' => ['بدء خطة متابعة للعميل', 'customer-success'],
            'customer-success.tasks.assign' => ['إسناد مهام متابعة العميل', 'customer-success'],
            'customer-success.tasks.complete' => ['إكمال مهام متابعة العميل', 'customer-success'],
            'customer-success.signals.manage' => ['إدارة فرص البيع والتوسع للعميل', 'customer-success'],
            'quotations.view' => ['عرض عروض المبيعات', 'quotations'],
            'quotations.create' => ['إنشاء عرض مبيعات', 'quotations'],
            'quotations.update' => ['تعديل عروض المبيعات', 'quotations'],
            'quotations.status' => ['تغيير حالة عرض المبيعات', 'quotations'],
            'quotations.convert' => ['تحويل عرض المبيعات إلى عقد', 'quotations'],
            'quotations.reports' => ['عرض تقارير عروض المبيعات', 'quotations'],
            'pricing-offers.view' => ['عرض العروض والخصومات', 'pricing-offers'],
            'pricing-offers.create' => ['إضافة عرض أو خصم', 'pricing-offers'],
            'pricing-offers.update' => ['تعديل العروض والخصومات', 'pricing-offers'],
            'contracts.view' => ['عرض العقود', 'contracts'],
            'contracts.create' => ['إنشاء عقد', 'contracts'],
            'contracts.update' => ['تعديل العقود', 'contracts'],
            'contracts.cancel' => ['إلغاء العقود', 'contracts'],
            'contracts.reopen' => ['إعادة فتح العقود', 'contracts'],
            'addendums.view' => ['عرض ملاحق العقود', 'addendums'],
            'addendums.create' => ['إضافة ملحق عقد', 'addendums'],
            'addendums.update' => ['تعديل ملاحق العقود', 'addendums'],
            'addendums.cancel' => ['إلغاء ملاحق العقود', 'addendums'],
            'addendums.reopen' => ['إعادة فتح ملاحق العقود', 'addendums'],
            'receivables.view' => ['عرض الاستحقاقات', 'receivables'],
            'receivables.create' => ['إضافة استحقاق', 'receivables'],
            'receivables.update' => ['تعديل الاستحقاقات', 'receivables'],
            'collections.view' => ['عرض سندات القبض', 'collections'],
            'collections.create' => ['إنشاء سند قبض', 'collections'],
            'collections.update' => ['تعديل سندات القبض', 'collections'],
            'collections.cancel' => ['إلغاء سندات القبض', 'collections'],
            'collections.reopen' => ['إعادة فتح سندات القبض', 'collections'],
            'discount-vouchers.view' => ['عرض سندات الخصم', 'discount-vouchers'],
            'discount-vouchers.create' => ['إنشاء سند خصم', 'discount-vouchers'],
            'discount-vouchers.update' => ['تعديل سندات الخصم', 'discount-vouchers'],
            'discount-vouchers.cancel' => ['إلغاء سندات الخصم', 'discount-vouchers'],
            'discount-vouchers.reopen' => ['إعادة فتح سندات الخصم', 'discount-vouchers'],
            'purchases.view' => ['عرض المشتريات', 'purchases'],
            'purchases.create' => ['إضافة عملية شراء', 'purchases'],
            'purchases.update' => ['تعديل المشتريات', 'purchases'],
            'purchases.delete' => ['حذف المشتريات', 'purchases'],
            'expenses.view' => ['عرض المصروفات', 'expenses'],
            'expenses.create' => ['إضافة مصروف', 'expenses'],
            'expenses.update' => ['تعديل المصروفات', 'expenses'],
            'expenses.cancel' => ['إلغاء المصروفات', 'expenses'],
            'expenses.reopen' => ['إعادة فتح المصروفات', 'expenses'],
            'expenses.delete' => ['حذف المصروفات', 'expenses'],
            'bank-statements.view' => ['عرض كشوف البنك', 'bank-statements'],
            'bank-statements.create' => ['إضافة كشف بنك', 'bank-statements'],
            'bank-statements.update' => ['مراجعة وتعديل حركات كشف البنك', 'bank-statements'],
            'bank-statements.approve' => ['اعتماد كشف البنك', 'bank-statements'],
            'bank-statements.delete' => ['حذف كشوف البنك', 'bank-statements'],
            'products.view' => ['عرض المنتجات والموديولات', 'products'],
            'products.create' => ['إضافة منتج أو موديول', 'products'],
            'products.update' => ['تعديل المنتجات والموديولات', 'products'],
            'suppliers.view' => ['عرض الموردين', 'suppliers'],
            'suppliers.create' => ['إضافة مورد', 'suppliers'],
            'suppliers.update' => ['تعديل بيانات الموردين', 'suppliers'],
            'intermediaries.view' => ['عرض الوسطاء', 'intermediaries'],
            'intermediaries.create' => ['إضافة وسيط', 'intermediaries'],
            'intermediaries.update' => ['تعديل بيانات الوسطاء', 'intermediaries'],
            'intermediaries.commissions' => ['عرض وإدارة عمولات الوسطاء', 'intermediaries'],
            'intermediaries.pay-commissions' => ['سداد عمولات الوسطاء', 'intermediaries'],
            'stations.view' => ['عرض المحطات', 'stations'],
            'stations.update' => ['تعديل بيانات المحطات', 'stations'],
            'installations.view' => ['عرض التركيبات', 'installations'],
            'installations.create' => ['إضافة عملية تركيب', 'installations'],
            'installations.update' => ['تعديل عمليات التركيب', 'installations'],
            'installations.cancel' => ['إلغاء عمليات التركيب', 'installations'],
            'installations.reopen' => ['إعادة فتح عمليات التركيب', 'installations'],
            'reports.profitability' => ['عرض تقرير الربحية', 'reports'],
            'reports.contracts' => ['عرض تقارير العقود والاستحقاقات', 'reports'],
            'reports.statements' => ['عرض كشوف حساب العملاء', 'reports'],
            'reports.exports' => ['تصدير وطباعة التقارير', 'reports'],
            'reports.audit' => ['عرض سجل التدقيق', 'reports'],
            'imports.view' => ['عرض عمليات الاستيراد', 'imports'],
            'imports.create' => ['بدء عملية استيراد', 'imports'],
            'imports.commit' => ['اعتماد الاستيراد', 'imports'],
            'imports.cancel' => ['إلغاء عملية الاستيراد', 'imports'],
            'attachments.download' => ['تحميل المرفقات', 'attachments'],
            'attachments.delete' => ['حذف المرفقات', 'attachments'],
            'users.view' => ['عرض المستخدمين', 'users'],
            'users.create' => ['إضافة مستخدم', 'users'],
            'users.update' => ['تعديل المستخدمين', 'users'],
            'users.roles' => ['إدارة الأدوار والصلاحيات', 'users'],
            'transactions.view-own' => ['عرض الحركات التي أدخلها المستخدم فقط', 'transactions'],
        ];

        foreach ($labels as $code => [$name, $group]) {
            DB::table('permissions')
                ->where('code', $code)
                ->update([
                    'name' => $name,
                    'group_name' => $group,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Permission codes never changed. Keeping localized display names on
        // rollback avoids replacing existing/custom labels with guessed values.
    }
};
