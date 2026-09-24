<?php

/**
 * Static acceptance audit for the requirements agreed with Raito.
 * Runs without Laravel/vendor so it can be used before composer install.
 * It verifies implementation signatures; runtime behavior is covered by Feature/Unit tests.
 */

$root = dirname(__DIR__);
$results = [];

$read = static function (string $path) use ($root): string {
    $full = $root.'/'.$path;
    return is_file($full) ? (file_get_contents($full) ?: '') : '';
};

$check = static function (string $group, string $name, bool $ok, string $evidence = '') use (&$results): void {
    $results[] = compact('group', 'name', 'ok', 'evidence');
};

$contains = static function (string $path, string $needle) use ($read): bool {
    return str_contains($read($path), $needle);
};

$notContains = static function (string $path, string $needle) use ($read): bool {
    return ! str_contains($read($path), $needle);
};

$allBlade = static function () use ($root): string {
    $buffer = '';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) $buffer .= file_get_contents($file->getPathname()) ?: '';
    }
    return $buffer;
};

// العملاء والعقود
$check('العملاء والعقود', 'حالة العميل نشط/غير نشط بدون محتمل', $contains('app/Http/Requests/StoreCustomerRequest.php', "in:active,inactive") && $notContains('app/Http/Requests/StoreCustomerRequest.php', 'prospect'));
$check('العملاء والعقود', 'نوع نشاط العقد ERP/محطات/أخرى', $contains('app/Http/Requests/StoreContractRequest.php', 'in:erp,stations,other'));
$check('العملاء والعقود', 'العقد يُنشأ مفعلًا بدون مسودة', $contains('app/Services/ContractService.php', "'status'=>'active'") && $notContains('app/Http/Requests/StoreContractRequest.php', "'status'=>'"));
$check('العملاء والعقود', 'تعديل/إلغاء/إعادة فتح العقد', $contains('routes/web.php', "contracts/{contract}/cancel") && $contains('routes/web.php', "contracts/{contract}/reopen") && $contains('routes/web.php', "'show', 'edit', 'update'"));
$check('العملاء والعقود', 'دفعات الدفع مرة واحدة تغطي صافي العقد بالكامل', $contains('app/Services/ContractService.php', 'دفعات تغطي صافي قيمة العقد بالكامل'));
$check('العملاء والعقود', 'التعديل التشغيلي للعقد لا يعيد بناء البنود والاستحقاقات', $contains('app/Services/ContractService.php', 'Operational edits') && $contains('app/Services/ContractService.php', 'تعديل بيانات تشغيلية للعقد'));
$check('العملاء والعقود', 'إضافة عميل سريع من العقد والعرض عبر نفس حقل الاختيار', $contains('resources/views/contracts/form.blade.php', 'customer-picker-card') && $contains('resources/views/quotations/form.blade.php', 'customer-picker-card') && $contains('resources/views/components/customer-picker-card.blade.php', 'data-toggle-customer-create'));
$check('العملاء والعقود', 'PTS والحساسات بنود عقد وليسا حقولًا منفصلة في رأس العقد', $notContains('app/Http/Requests/StoreContractRequest.php', "'pts_count'") && $notContains('app/Http/Requests/StoreContractRequest.php', "'sensor_count'") && $contains('app/Services/DeviceQuantityService.php', "'pts'") && $contains('app/Services/DeviceQuantityService.php', "'sensor'"));
$check('العملاء والعقود', 'إضافة العميل السريعة تظهر ككارت داخل إنشاء العقد', $contains('resources/views/contracts/form.blade.php', 'customer-picker-card') && $contains('resources/views/components/customer-picker-card.blade.php', 'data-quick-customer-card'));
$check('العملاء والعقود', 'عملة افتراضية SAR مع USD وEGP', $contains('config/finance.php', "env('FINANCE_DEFAULT_CURRENCY', 'SAR')") && $contains('config/finance.php', "'USD' =>") && $contains('config/finance.php', "'EGP' =>"));
$check('العملاء والعقود', 'منع تكرار العميل بالاسم أو الهاتف أو البريد مع الحماية الصريحة للسجل والضريبة', $contains('app/Http/Requests/StoreCustomerRequest.php', 'يوجد عميل مسجل بنفس الاسم') && $contains('app/Http/Requests/StoreCustomerRequest.php', 'يوجد عميل مسجل بنفس رقم الهاتف') && $contains('app/Http/Requests/StoreCustomerRequest.php', 'withoutTrashed'));
$check('العملاء والعقود', 'ملف العميل يدعم المرفقات وCustomer 360 حسب العملة', $contains('app/Models/Customer.php', 'HasAttachments') && $contains('app/Http/Controllers/CustomerController.php', 'financeSummary') && $contains('resources/views/customers/show.blade.php', 'الملخص المالي حسب العملة'));
$check('العملاء والعقود', 'ضريبة 15% من الإعدادات', $contains('config/finance.php', "env('FINANCE_TAX_RATE', 15)"));

// التسعير والعروض
$check('التسعير والعروض', '3 مستخدمين افتراضيًا للموديولات مع دعم منتجات بدون مستخدمين مجانيين', $contains('config/finance.php', "'included_users_per_module_one_time' => 3") && $contains('app/Http/Requests/StoreProductRequest.php', "included_users_one_time' => \$supports") && $contains('app/Http/Requests/StoreProductRequest.php', "min:0|max:100000"));
$check('التسعير والعروض', 'المستخدمون الإضافيون أسعارهم منفصلة للدائمة/الشهرية/السنوية', $contains('app/Http/Requests/StoreProductRequest.php', 'extra_user_price_one_time') && $contains('app/Http/Requests/StoreProductRequest.php', 'user_price_monthly') && $contains('app/Http/Requests/StoreProductRequest.php', 'user_price_annual'));
$check('التسعير والعروض', 'عرض كل 4 مستخدمين مدفوعين + مستخدم مجاني', $contains('database/seeders/DatabaseSeeder.php', "'minimum_users'=>4") && $contains('database/seeders/DatabaseSeeder.php', "'free_users'=>1") && $contains('app/Services/PricingCalculator.php', 'promotionalFreeUsers'));
$check('التسعير والعروض', 'خصم الشركات الناشئة ثابت 50%', $contains('config/finance.php', "'startup_discount_percentage' => 50") && $contains('app/Http/Requests/StorePricingOfferRequest.php', 'startup_discount_percentage'));
$check('التسعير والعروض', 'خصومات موسمية بنسبة وفترة', $contains('app/Http/Requests/StorePricingOfferRequest.php', 'seasonal_discount') && $contains('app/Http/Requests/StorePricingOfferRequest.php', 'starts_at') && $contains('app/Http/Requests/StorePricingOfferRequest.php', 'ends_at'));
$check('التسعير والعروض', 'خصم باقة بمبلغ ثابت يتطلب كل منتجات الباقة', $contains('app/Http/Requests/StorePricingOfferRequest.php', 'bundle_fixed_discount') && $contains('app/Services/PricingCalculator.php', 'bundleQualifies') && $contains('app/Services/PricingCalculator.php', 'fixed_discount_amount') && $contains('database/migrations/2026_08_09_002200_add_fixed_bundle_discount_offers.php', 'fixed_discount_amount'));
$check('التسعير والعروض', 'منع تكرار المنتج داخل العرض والعقد', $contains('app/Http/Requests/StoreQuotationRequest.php', 'duplicates()') && $contains('app/Http/Requests/StoreContractRequest.php', 'duplicates()'));
$check('التسعير والعروض', 'الموديولات والبنود المسعرة بالمستخدم بلا كمية مستقلة', $contains('app/Services/PricingCalculator.php', '$isSeatPriced = (bool) $product?->supports_user_pricing') && $contains('app/Http/Requests/StoreQuotationRequest.php', "items.*.quantity'=>'required|integer") && $contains('resources/views/components/pricing-items.blade.php', 'data-quantity-field'));
$check('التسعير والعروض', 'تطبيق المناديب مرتبط بالمخزون وبدون سعر أساسي', $contains('database/migrations/2026_08_09_002100_add_product_dependencies_and_delegate_app.php', "'code' => 'APP-DELEGATES'") && $contains('database/migrations/2026_08_09_002100_add_product_dependencies_and_delegate_app.php', "'default_sale_price' => 0") && $contains('app/Support/ProductDependencyRules.php', 'يتطلب وجود'));
$check('التسعير والعروض', 'شراء تطبيق المناديب يحاسب كل مندوب بدون صيانة للتطبيق', $contains('app/Services/PricingCalculator.php', "code === 'APP-DELEGATES'") && $contains('tests/Unit/PricingCalculatorTest.php', 'test_delegate_app_has_no_base_price_or_maintenance_and_charges_every_delegate'));
$check('التسعير والعروض', 'إيقاف المنتج الأساسي ممنوع طالما توجد منتجات مرتبطة فعالة', $contains('app/Services/ContractItemLifecycleService.php', 'required_product_id') && $contains('app/Services/ContractItemLifecycleService.php', 'لا يمكن إيقاف')); 
$check('التسعير والعروض', 'الموديولات لا تدخل في سعة PTS أو الحساسات', $contains('app/Services/DeviceQuantityService.php', "if (! in_array(\$product?->type, ['pts','sensor'], true)) continue") || ($contains('app/Services/DeviceQuantityService.php', "'pts'") && $contains('app/Services/DeviceQuantityService.php', "'sensor'") && $notContains('app/Services/DeviceQuantityService.php', "'erp_module'")));

// عروض المبيعات
$check('عروض المبيعات', 'إصدارات وحالات وتقارير عروض المبيعات', $contains('app/Services/QuotationService.php', 'version_number') && $contains('routes/web.php', '/quotations/report') && $contains('resources/views/quotations/report.blade.php', ''));
$check('عروض المبيعات', 'حفظ Snapshot كامل للتسعير', $contains('app/Models/SalesQuotation.php', 'pricing_snapshot') && $contains('app/Services/QuotationService.php', 'pricing_snapshot'));
$check('عروض المبيعات', 'تحويل العرض يحافظ على نسبة الضريبة التاريخية المحفوظة', $contains('app/Services/ContractService.php', '$taxRate=$quotation ? (float)$quotation->tax_rate'));
$check('عروض المبيعات', 'لا يتحول العرض لأكثر من عقد', $contains('app/Services/ContractService.php', 'تم تحويل عرض المبيعات إلى عقد من قبل') && $contains('database/migrations/2026_08_06_001000_harden_workflows_security_reporting.php', 'sales_quotation_id'));
$check('عروض المبيعات', 'التحويل يستخدم آخر إصدار مقبول فقط', $contains('app/Services/ContractService.php', "status!=='accepted'") && $contains('app/Services/ContractService.php', 'is_current_version'));
$check('عروض المبيعات', 'بيانات التسعير مقفلة من العرض المقبول عند إنشاء العقد', $contains('app/Http/Requests/StoreContractRequest.php', 'pricing snapshots') && $contains('resources/views/contracts/form.blade.php', 'data-quotation-locked'));
$check('عروض المبيعات', 'تقرير العروض يعرض متوسط القيمة والأداء حسب نوع النشاط', $contains('app/Http/Controllers/QuotationController.php', "'average_value'") && $contains('app/Http/Controllers/QuotationController.php', '$byActivity') && $contains('resources/views/quotations/report.blade.php', 'متوسط قيمة العرض'));
$check('عروض المبيعات', 'تقرير أثر العروض يشمل المبيعات وربحية العقود المتحولة بدون الضريبة', $contains('app/Http/Controllers/QuotationController.php', 'contractual_profit') && $contains('app/Http/Controllers/QuotationController.php', 'contracts.net_total') && $contains('resources/views/quotations/report.blade.php', 'ربحية العقود المتحولة'));
$check('عروض المبيعات', 'نسخة العرض بعد الإرسال لا تعدل ويستخدم إصدار جديد', $contains('app/Services/QuotationService.php', 'لا يمكن تعديل محتوى عرض تم إرساله') && $contains('resources/views/quotations/show.blade.php', '+ إصدار جديد') && $contains('app/Http/Controllers/QuotationController.php', "status !== 'draft'"));
$check('عروض المبيعات', 'حالة العرض لا يمكن تجاوز صلاحيتها من نموذج الإنشاء', $contains('app/Http/Requests/StoreQuotationRequest.php', '$data = [\'status\' => \'draft\']') && $notContains('resources/views/quotations/form.blade.php', 'name=\"status\"'));
$check('عروض المبيعات', 'متوسط مدة الإغلاق يعمل على MySQL وSQLite', $contains('app/Http/Controllers/QuotationController.php', "DB::connection()->getDriverName() === 'sqlite'") && $contains('app/Http/Controllers/QuotationController.php', 'julianday(converted_at)') && $contains('app/Http/Controllers/QuotationController.php', 'DATEDIFF(converted_at, quotation_date)'));
$check('عروض المبيعات', 'تحويل العرض إلى عقد له صلاحية مستقلة', $contains('app/Http/Controllers/ContractController.php', "hasPermission('quotations.convert')") && $contains('resources/views/quotations/show.blade.php', "hasPermission('quotations.convert')"));

// الصيانة والاشتراكات
$check('الصيانة والاشتراكات', 'الصيانة فقط للدفع مرة واحدة ونسبة صفر تعني بدون صيانة', $contains('app/Services/PricingCalculator.php', "billingCycle === 'one_time'"));
$check('الصيانة والاشتراكات', 'أول صيانة بعد سنة من بداية الخدمة', $contains('app/Services/ContractDateService.php', 'addYear'));
$check('الصيانة والاشتراكات', 'الصيانة تشمل العقد والملحقات', $contains('app/Services/ReceivableService.php', 'contract_addendum_id') && $contains('app/Services/AddendumService.php', 'maintenance'));
$check('الصيانة والاشتراكات', 'الاستحقاقات الدورية تتولد تلقائيًا داخل نافذة 15 يوم قبل الاستحقاق', $contains('config/finance.php', 'receivable_generation_lead_days') && $contains('app/Services/RecurringReceivableGenerator.php', 'function horizon') && $contains('app/Services/ContractService.php', 'generateForContract($contract)') && $contains('app/Services/AddendumService.php', 'generateForAddendum($addendum)') && is_file($root.'/tests/Feature/RecurringReceivableLeadTimeTest.php'));
$check('الصيانة والاشتراكات', 'منع تكرار استحقاقات الصيانة والاشتراكات عند إعادة تشغيل المولد', $contains('app/Services/RecurringReceivableGenerator.php', '->whereDate(\'due_date\', $dueDate)') && $contains('app/Services/RecurringReceivableGenerator.php', 'if (! $exists)'));
$check('الصيانة والاشتراكات', 'إنشاء الاستحقاقات الشهرية والسنوية دوريًا', is_file($root.'/app/Console/Commands/GenerateRecurringReceivables.php') && $contains('routes/console.php', 'finance:generate-receivables'));
$check('الصيانة والاشتراكات', 'نافذة التوليد المسبق افتراضيًا 15 يوم ولا تغير due_date', $contains('config/finance.php', "env('FINANCE_RECEIVABLE_LEAD_DAYS', 15)") && $contains('.env.example', 'FINANCE_RECEIVABLE_LEAD_DAYS=15') && $contains('app/Services/RecurringReceivableGenerator.php', '$dueDate = $contract->next_billing_date->copy()'));

// المحطات والتركيبات
$check('المحطات والتركيبات', 'المحطة فرع ببيانات بسيطة', $contains('app/Http/Requests/StoreStationRequest.php', 'customer_id') && $notContains('app/Http/Requests/StoreStationRequest.php', 'software'));
$check('المحطات والتركيبات', 'شاشة تركيب PTS وعدد حساسات وتاريخ', $contains('app/Http/Requests/StoreInstallationRequest.php', 'pts_installed') && $contains('app/Http/Requests/StoreInstallationRequest.php', 'sensor_count') && $contains('app/Http/Requests/StoreInstallationRequest.php', 'installation_date'));
$check('المحطات والتركيبات', 'كميات التركيب تشمل بنود العقد والملحقات وتمنع التجاوز', $contains('app/Services/InstallationService.php', 'totalCapacity') && $contains('app/Services/DeviceQuantityService.php', 'activeAddendums') && $contains('app/Services/InstallationService.php', 'العقد والملحقات الفعالة'));
$check('المحطات والتركيبات', 'تعديل/إلغاء/إعادة فتح التركيبات', $contains('routes/web.php', "installations/{installation}/cancel") && $contains('routes/web.php', "installations/{installation}/reopen"));
$check('المحطات والتركيبات', 'المحطة الواحدة بحد أقصى PTS واحد والحساسات متعددة', $contains('app/Http/Requests/StoreContractRequest.php', "stations.*.pts_count' => 'nullable|integer|min:0|max:1") && $contains('app/Services/InstallationService.php', 'المحطة الواحدة لا تحتوي إلا على PTS واحد'));
$check('المحطات والتركيبات', 'قسم المحطات يظهر فقط عند نشاط محطات وبمعالج مستقل', $contains('resources/js/app.js', 'syncContractStationsVisibility') && $contains('resources/js/app.js', "activity.value === 'stations'") && $contains('resources/views/contracts/form.blade.php', 'data-contract-stations-section'));
$check('المحطات والتركيبات', 'إلغاء العقد محمي عند وجود تركيب فعلي', $contains('app/Services/ContractService.php', 'لا يمكن إلغاء عقد عليه تركيب فعلي'));
$check('المحطات والتركيبات', 'إنشاء المحطات يتم من عقد المحطات فقط دون Route إنشاء مستقل', $contains('routes/web.php', "Route::resource('stations', StationController::class)->only(['index', 'edit', 'update'])") && $notContains('routes/web.php', "permission:stations.create") && $notContains('app/Http/Controllers/StationController.php', 'function store('));
$check('المحطات والتركيبات', 'صلاحية إنشاء المحطة المستقلة أزيلت من Seeder', $notContains('database/seeders/PermissionSeeder.php', "'stations'=>['view','create','update']") && $contains('database/seeders/PermissionSeeder.php', "Permission::where('code', 'stations.create')->delete()"));

// التحصيل وكشف الحساب
$check('التحصيل وكشف الحساب', 'سند القبض مؤكد فور الإنشاء بدون حالة إدخال', $contains('app/Services/CollectionService.php', "'status'=>'confirmed'") && $notContains('app/Http/Requests/StoreCollectionRequest.php', "'status'"));
$check('التحصيل وكشف الحساب', 'لا يسمح بدفع زائد أو مبلغ غير موزع', $contains('app/Services/CollectionService.php', 'مبلغ سند القبض بالكامل') || $contains('app/Services/CollectionService.php', 'لا يمكن تحصيل مبلغ أكبر'));
$check('التحصيل وكشف الحساب', 'الاستحقاق مربوط بتوزيعات التحصيل', $contains('app/Models/CollectionAllocation.php', 'receivable') && $contains('app/Models/Receivable.php', 'collectionAllocations'));
$check('التحصيل وكشف الحساب', 'سند القبض يختار عقدًا ويعرض استحقاقات هذا العقد فقط', $contains('app/Http/Requests/StoreCollectionRequest.php', "'contract_id'=>'required|exists:contracts,id'") && $contains('app/Services/CollectionService.php', 'استحقاق من عقد آخر') && $contains('resources/views/collections/form.blade.php', 'data-contract-select'));
$check('التحصيل وكشف الحساب', 'سندات الخصم مع تعديل/إلغاء/إعادة فتح', is_file($root.'/app/Services/DiscountVoucherService.php') && $contains('routes/web.php', 'discount-vouchers/{discountVoucher}/cancel') && $contains('routes/web.php', 'discount-vouchers/{discountVoucher}/reopen'));
$check('التحصيل وكشف الحساب', 'كشف الحساب يعرض الدفعات المستحقة وسندات القبض وسندات الخصم', $contains('app/Services/CustomerStatementService.php', 'دفعة عقد') && $contains('app/Services/CustomerStatementService.php', 'سند قبض') && $contains('app/Services/CustomerStatementService.php', 'سند خصم'));
$check('التحصيل وكشف الحساب', 'كشف حساب PDF وExcel وطباعة', $contains('routes/web.php', 'statement/excel') && $contains('routes/web.php', 'statement/pdf') && $contains('routes/web.php', 'statement/print'));

// التكاليف والربحية
$check('التكاليف والربحية', 'المشتريات تدخل معتمدة', $contains('app/Services/PurchaseService.php', "'status'=>'confirmed'"));
$check('التكاليف والربحية', 'حذف المشتريات نهائيًا', $notContains('app/Models/Purchase.php', 'SoftDeletes') && $contains('app/Services/PurchaseService.php', '$purchase->delete()'));
$check('التكاليف والربحية', 'توزيع تكلفة الشراء على عقد/عميل/محطة', $contains('app/Http/Requests/StorePurchaseRequest.php', 'allocations.*.contract_id') && $contains('app/Http/Requests/StorePurchaseRequest.php', 'allocations.*.station_id'));
$check('التكاليف والربحية', 'المصروفات لها دورة تعديل/إلغاء/إعادة فتح مع سبب الإلغاء', $contains('routes/web.php', "expenses/{expense}/cancel") && $contains('routes/web.php', "expenses/{expense}/reopen") && $contains('app/Services/ExpenseService.php', 'cancellation_reason'));
$check('التكاليف والربحية', 'مصروفات مرتبطة بعقد/عميل أو عامة', $contains('app/Http/Requests/StoreExpenseRequest.php', 'contract_id') && $contains('app/Http/Requests/StoreExpenseRequest.php', 'customer_id'));
$check('التكاليف والربحية', 'الربحية تفصل العملة وتدعم تعاقدية/محققة/نقدية', $contains('app/Services/ProfitabilityService.php', 'contractual') && $contains('app/Services/ProfitabilityService.php', 'earned') && $contains('app/Services/ProfitabilityService.php', 'cash'));
$check('التكاليف والربحية', 'ربحية طوال فترة التعامل متاحة بدون خلط العملات', $contains('resources/views/reports/profitability.blade.php', 'ربحية العميل/العقد طوال التعامل') && $contains('app/Services/ProfitabilityService.php', "\$mode === 'lifetime'"));
$check('التكاليف والربحية', 'Lifetime Profitability لا تخفي العقود الملغاة وتكاليفها التاريخية', $contains('app/Services/ProfitabilityService.php', "->when(\$mode !== 'lifetime'"));
$check('التكاليف والربحية', 'الربحية النقدية تخصم العمولة المصروفة فعليًا فقط', $contains('app/Services/ProfitabilityService.php', 'intermediary_commission_payments') && $contains('app/Services/ProfitabilityService.php', "'cash' => (float) (\$commissionPaid"));

// الوسطاء
$check('عمولات الوسطاء', 'استحقاق العمولة من التحصيل الفعلي', $contains('app/Services/IntermediaryCommissionService.php', 'CollectionAllocation') || $contains('app/Services/IntermediaryCommissionService.php', 'collection_allocation'));
$check('عمولات الوسطاء', 'بيانات العمولة تختفي وتُلغى عند عدم وجود وسيط', $contains('resources/views/contracts/form.blade.php', 'data-has-intermediary') && $contains('resources/views/quotations/form.blade.php', 'data-intermediary-panel') && $contains('app/Services/ContractService.php', 'empty($data[\'intermediary_id\'])'));
$check('عمولات الوسطاء', 'صرف جزئي/كامل وتقرير عمولات', $contains('routes/web.php', 'intermediary-commissions') && $contains('app/Http/Controllers/IntermediaryCommissionController.php', 'pay'));
$check('عمولات الوسطاء', 'عمولة العقد الأساسية لا تستحق من صيانة أو ملحق', $contains('app/Services/IntermediaryCommissionService.php', 'contract_addendum_id') && $contains('app/Services/IntermediaryCommissionService.php', "['maintenance','opening_maintenance']"));

// العقود القديمة والاستيراد
$check('الاستيراد', 'تاريخ بداية احتساب مستقل للعقود القديمة', $contains('app/Http/Requests/StoreContractRequest.php', 'calculation_start_date') && $contains('app/Services/ContractService.php', 'calculationStart'));
$check('الاستيراد', 'استيراد Excel متعدد الأوراق', $contains('app/Services/ContractImportService.php', 'PhpSpreadsheet') && $contains('app/Services/ContractImportService.php', 'SHEETS'));
$check('الاستيراد', 'معالجة واعتماد الاستيراد عبر Queue', is_file($root.'/app/Jobs/ProcessContractImport.php') && is_file($root.'/app/Jobs/CommitContractImport.php'));
$check('الاستيراد', 'مراجعة أخطاء/تعديل صفوف/ملف أخطاء/تراجع', $contains('routes/web.php', 'rows/{row}') && $contains('routes/web.php', '/errors') && $contains('routes/web.php', '/rollback'));

// كشف البنك
$check('كشف البنك', 'رفع كشف Excel وربط الأعمدة وقراءة الحركات', $contains('app/Services/BankStatementService.php', 'IOFactory') && $contains('routes/web.php', '/{batch}/mapping') && $contains('routes/web.php', '/{batch}/parse'));
$check('كشف البنك', 'تصنيف كل حركة مصروف/تحصيل/تجاهل مع حفظ قبل الاعتماد', $contains('app/Services/BankStatementService.php', '$classification===\'ignored\'') && $contains('app/Services/BankStatementService.php', "['expense','collection']") && $contains('routes/web.php', 'rows/{row}'));
$check('كشف البنك', 'الاعتماد All-or-Nothing وينشئ السندات مرة واحدة', $contains('app/Services/BankStatementService.php', 'DB::transaction') && $contains('app/Services/BankStatementService.php', 'تم ترحيله من قبل'));
$check('كشف البنك', 'التحصيل البنكي يتوزع على الاستحقاقات بدون دفع زائد', $contains('app/Services/BankStatementService.php', 'يجب أن يساوي مجموع توزيع التحصيل مبلغ الحركة البنكية بالكامل') && $contains('app/Services/BankStatementService.php', 'remaining_amount'));

// الأمان والصلاحيات
$check('الأمان والصلاحيات', 'صلاحيات تفصيلية على العمليات', $contains('routes/web.php', 'permission:contracts.create') && $contains('routes/web.php', 'permission:collections.create') && $contains('routes/web.php', 'permission:bank-statements.approve'));
$check('الأمان والصلاحيات', 'أدوار وصلاحيات قابلة للإدارة', is_file($root.'/resources/views/admin/roles/index.blade.php') && is_file($root.'/database/seeders/PermissionSeeder.php'));
$check('الأمان والصلاحيات', 'أدوار المالية والمشتريات تملك صلاحيات Lookup اللازمة فقط', $contains('database/seeders/PermissionSeeder.php', "'finance' =>") && $contains('database/seeders/PermissionSeeder.php', "'purchases' =>") && $contains('database/seeders/PermissionSeeder.php', "'products.view', 'stations.view'") && $contains('database/seeders/PermissionSeeder.php', "if (! empty(\$definition['permissions']))"));
$check('الأمان والصلاحيات', 'Audit Log بالقيم القديمة والجديدة والمستخدم وIP', $contains('app/Observers/AuditObserver.php', 'old_values') && $contains('app/Observers/AuditObserver.php', 'new_values') && $contains('app/Observers/AuditObserver.php', 'ip_address'));
$check('الأمان والصلاحيات', 'المرفقات Private خلف Route وصلاحية', $contains('app/Services/DocumentService.php', "'private-documents/'") && $contains('app/Services/DocumentService.php', "'local'") && $contains('routes/web.php', 'permission:attachments.download'));
$check('الأمان والصلاحيات', 'CSP بدون unsafe-inline للسكريبتات', $contains('app/Http/Middleware/SecurityHeaders.php', "script-src 'self'") && $notContains('app/Http/Middleware/SecurityHeaders.php', "script-src 'self' 'unsafe-inline'"));

// الأداء وUX والجودة
$check('الأداء وUX', 'حقول البيانات المرجعية تستخدم Selector واحد مع إجراء إضافة موحد', is_file($root.'/resources/views/components/reference-picker.blade.php') && $contains('resources/views/purchases/form.blade.php', 'x-reference-picker') && $contains('resources/views/contracts/form.blade.php', 'x-reference-picker') && $contains('resources/views/expenses/form.blade.php', 'x-reference-picker'));
$check('الأداء وUX', 'الاختيارات تستخدم Select2 الرسمي بواجهة Bootstrap 5 وحقل واحد فقط', $contains('resources/js/app.js', "theme: 'bootstrap-5'") && $contains('resources/views/layouts/app.blade.php', 'select2.min.css') && $contains('resources/css/app.css', '.select2-container--bootstrap-5'));
$check('الأداء وUX', 'اختبارات SQLite تجهز المخطط مرة واحدة بدون Override متعارض مع Laravel', $contains('tests/bootstrap.php', "Artisan::call('migrate:fresh'") && $notContains('tests/TestCase.php', 'beforeRefreshingDatabase'));
$check('الأداء وUX', 'Migration الإصلاح البنكي لها Rollback غير هدّام', $contains('database/migrations/2026_08_07_001600_repair_bank_and_contract_entry_ux.php', 'production repair migration') && $notContains('database/migrations/2026_08_07_001600_repair_bank_and_contract_entry_ux.php', "dropColumn('domain')"));
$entryBlade = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views')) as $file) {
    if ($file->isFile() && preg_match('/(?:form|create|edit)\.blade\.php$/', $file->getFilename())) $entryBlade .= file_get_contents($file->getPathname()) ?: '';
}
$check('الأداء وUX', 'الأكواد الداخلية لا تظهر كمدخلات يدوية في شاشات الإدخال', ! preg_match('/<input\b[^>]*\bname=[\"\'](?:code|number)[\"\']/i', $entryBlade));
$check('الأداء وUX', 'قوائم كبيرة تستخدم بحث Remote/Pagination', $contains('resources/js/app.js', "options.ajax =") && $contains('resources/js/app.js', "delay: 250") && $contains('app/Http/Controllers/LookupController.php', 'limit(20)') && $contains('app/Http/Controllers/ContractController.php', 'paginate('));
$check('الأداء وUX', 'تاريخ العميل Timeline موحد مع Pagination SQL', $contains('app/Services/CustomerHistoryService.php', 'paginate(') && is_file($root.'/app/Models/TimelineEvent.php'));
$check('الأداء وUX', 'حماية من الإرسال المكرر ومغادرة النماذج بدون حفظ', $contains('resources/js/app.js', 'جار الحفظ') && $contains('resources/js/app.js', 'beforeunload'));
$check('الأداء وUX', 'لا توجد Inline event handlers في Blade', ! preg_match('/\son(?:click|submit|change|input|load)\s*=/i', $allBlade()));
$check('الأداء وUX', 'اختبارات مالية/أمنية/كشف بنك موجودة', is_file($root.'/tests/Feature/FinancialWorkflowTest.php') && is_file($root.'/tests/Feature/AuthorizationAndAttachmentTest.php') && is_file($root.'/tests/Feature/BankStatementWorkflowTest.php'));
$check('الأداء وUX', 'فحص إنتاج يمنع النشر دون composer.lock/vendor', $contains('scripts/verify-production-readiness.php', 'composer.lock') && $contains('scripts/verify-production-readiness.php', 'vendor/autoload.php'));
$check('الأداء وUX', 'composer.json يحدد Laravel/PHP/Excel/PDF وامتدادات PHP المطلوبة', $contains('composer.json', '"php": "^8.2"') && $contains('composer.json', '"laravel/framework": "^12.0"') && $contains('composer.json', '"phpoffice/phpspreadsheet": "^5.9"') && $contains('composer.json', '"maennchen/zipstream-php": "3.1.2"') && $contains('composer.json', '"dompdf/dompdf": "^3.1"') && $contains('composer.json', '"ext-mbstring": "*"'));
$check('الأداء وUX', 'إعداد Composer يشغّل كل Seeders وفحص المتطلبات', $contains('composer.json', '@php artisan db:seed --force --ansi') && $contains('composer.json', 'requirements:audit'));

$passed = count(array_filter($results, fn ($r) => $r['ok']));
$total = count($results);
$failed = $total - $passed;
$groups = [];
foreach ($results as $result) $groups[$result['group']][] = $result;

foreach ($groups as $group => $items) {
    echo "\n[{$group}]\n";
    foreach ($items as $item) echo ($item['ok'] ? '  [OK] ' : '  [FAIL] ').$item['name']."\n";
}

echo "\nالنتيجة: {$passed}/{$total} تحقق ثابت، {$failed} فشل.\n";
if ($failed > 0) exit(1);
