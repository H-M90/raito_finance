<?php

namespace App\Support;

final class FinanceOptions
{
    public static function activityTypes(): array { return ['erp'=>'ERP','stations'=>'محطات','other'=>'أخرى']; }
    public static function billingCycles(): array { return ['one_time'=>'مرة واحدة','monthly'=>'شهري','annual'=>'سنوي']; }
    public static function currencies(): array { return config('finance.currencies'); }
    public static function productTypes(): array { return ['erp_module'=>'موديول ERP','application'=>'تطبيق','software'=>'برنامج / موقع','hosting'=>'استضافة','pts'=>'جهاز PTS','sensor'=>'حساس','device'=>'جهاز / قطعة','other'=>'بند آخر']; }
    public static function units(): array { return ['license'=>'رخصة','user'=>'مستخدم','company'=>'شركة','station'=>'محطة','device'=>'جهاز','sensor'=>'حساس','piece'=>'قطعة','project'=>'مشروع','service'=>'خدمة']; }
    public static function quotationStatuses(): array { return ['draft'=>'مسودة','sent'=>'مرسل للعميل','negotiation'=>'تحت التفاوض','accepted'=>'مقبول','rejected'=>'مرفوض','expired'=>'منتهي الصلاحية','cancelled'=>'ملغي','converted'=>'تحول إلى عقد']; }
    public static function contractStatuses(): array { return ['active'=>'نشط','cancelled'=>'ملغي']; }
    public static function paymentMethods(): array { return ['cash'=>'نقدي','transfer'=>'تحويل','cheque'=>'شيك','card'=>'بطاقة','other'=>'أخرى']; }
    public static function receivableTypes(): array { return ['installment'=>'دفعة عقد','maintenance'=>'صيانة','monthly'=>'اشتراك شهري','annual'=>'اشتراك سنوي','addendum_installment'=>'دفعة ملحق','opening_contract'=>'رصيد افتتاحي عقد','opening_maintenance'=>'رصيد افتتاحي صيانة','manual'=>'استحقاق يدوي']; }
}
