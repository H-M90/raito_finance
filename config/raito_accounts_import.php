<?php

return [
    'main_sheet_aliases' => ['raito accounts main file', 'accounts', 'customers'],
    'collections_sheet_aliases' => ['التحصيلات', 'collections'],

    // These aliases are normalized by AccountsImportService before comparison.
    'fields' => [
        'customer_name' => ['اسم الشركة', 'اسم العميل', 'العميل', 'company', 'customer', 'company name'],
        'sector' => ['القطاع', 'النشاط', 'activity', 'sector'],
        'domain' => ['الرابط الأساسي', 'رابط النظام', 'الدومين', 'domain', 'url'],
        'contact_name' => ['اسم المسؤول', 'المسؤول', 'contact name'],
        'phone' => ['رقم الهاتف', 'الهاتف', 'phone', 'mobile'],
        'contract_date' => ['تاريخ العقد', 'تاريخ البداية', 'contract date', 'start date'],
        'due_date' => ['تاريخ اخر استحقاق مالي', 'تاريخ آخر استحقاق مالي', 'last financial due date', 'due date'],
        'billing_cycle' => ['تجديد الباقة الشهرية', 'billing cycle', 'renewal plan'],
        'customer_status' => ['حاله العميل', 'حالة العميل', 'customer status'],
        'city' => ['المدينة', 'city'],
        'employees' => ['عدد الموظفين', 'employees', 'employee count'],
        'collection_status' => ['حاله التحصيل', 'حالة التحصيل', 'collection status'],
        'collector_name' => ['المسؤول عن المتابعه', 'المسؤول عن المتابعة', 'collector', 'follow up owner'],
        'collection_start_date' => ['تاريخ بدء التحصيل', 'collection start date'],
        'collection_end_date' => ['تاريخ انتهاء التحصيل', 'collection end date'],
        'collection_amount' => ['المبلغ المستحق', 'collection amount', 'amount due'],
        'collection_claim' => ['المطالبه الماليه', 'المطالبة المالية', 'financial claim'],
        'collection_follow_up' => ['متابعه التحصيل', 'متابعة التحصيل', 'collection follow up'],
    ],

    // Header aliases are deliberately explicit; no fuzzy product matching is used.
    'products' => [
        'ERP-ACC' => ['حسابات', 'الحسابات', 'accounts'],
        'ERP-INV' => ['مخزون', 'المخازن', 'inventory', 'stock'],
        'APP-DELEGATES' => ['اجمالي المناديب', 'إجمالي المناديب', 'المناديب', 'تطبيق المناديب', 'delegates'],
        'ERP-FUEL-STATIONS' => ['محطات وقود', 'محطات الوقود', 'fuel stations'],
        'ERP-MAINT' => ['صيانة', 'maintenance'],
        'ERP-ASSETS' => ['اصول', 'أصول', 'assets'],
        'ERP-FLEET' => ['نقليات', 'النقليات', 'fleet'],
        'ERP-HR' => ['موارد بشريه', 'موارد بشرية', 'hr', 'human resources'],
        'ERP-MFG' => ['اداره الانتاج و التصنيع', 'إدارة الإنتاج والتصنيع', 'التصنيع', 'manufacturing'],
        'ERP-REALESTATE' => ['عقارات', 'real estate'],
        'APP-CUSTOM' => ['تطبيق العملاء', 'تطبيق العميل', 'customer application'],
        'WEBSITE' => ['ويب سايت', 'website', 'web site'],
        'EMAIL-HOSTING' => ['ميلات', 'ايميلات', 'إيميلات', 'email hosting'],
    ],

    'usage_headers' => [
        'ERP-ACC' => ['active' => ['حسابات نشط'], 'inactive' => ['حسابات غير نشط']],
        'ERP-INV' => ['active' => ['مخزون نشط'], 'inactive' => ['مخزون غير نشط']],
        'APP-DELEGATES' => ['active' => ['عدد المناديب النشطة', 'المناديب النشطة'], 'inactive' => ['عدد المناديب الغير نشطة', 'المناديب الغير نشطة']],
    ],

    'billing_cycles' => [
        'monthly' => ['شهري', 'monthly', 'كل شهر'],
        'annual' => ['سنوي', 'annual', 'yearly', 'سنة'],
        'one_time' => ['شامل', 'مرة واحدة', 'one time', 'one-off'],
    ],
];
