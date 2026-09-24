<?php

namespace App\Support;

class CustomerSuccessOptions
{
    public static function attentionLevels(): array
    {
        return [
            'critical' => 'حرج',
            'high' => 'مرتفع',
            'medium' => 'متوسط',
            'normal' => 'طبيعي',
            'closed' => 'مغلق',
        ];
    }

    public static function healthLevels(): array
    {
        return [
            'VERY_SATISFIED' => 'راضٍ جدًا',
            'SATISFIED' => 'راضٍ',
            'NEUTRAL' => 'محايد',
            'DISSATISFIED' => 'غير راضٍ',
            'VERY_DISSATISFIED' => 'غير راضٍ جدًا',
        ];
    }

    public static function signalTypes(): array
    {
        return [
            'REFERRAL_READY' => 'مرشح لترشيح عميل جديد',
            'TESTIMONIAL_READY' => 'مرشح لشهادة عميل',
            'AMBASSADOR' => 'عميل سفير',
            'UPSELL' => 'فرصة زيادة الاشتراك أو المستخدمين',
            'CROSS_SELL' => 'فرصة بيع خدمة أو موديول إضافي',
            'EXPANSION_SALE' => 'فرصة توسع',
            'RENEWAL' => 'فرصة تجديد',
        ];
    }

    public static function signalStatuses(): array
    {
        return [
            'active' => 'فعالة',
            'won' => 'تمت بنجاح',
            'lost' => 'لم تتم',
            'deferred' => 'مؤجلة',
            'closed' => 'مغلقة',
        ];
    }

    public static function contactRoles(): array
    {
        return [
            'ACCOUNTANT' => 'المحاسب',
            'FINANCE_MANAGER' => 'المدير المالي',
            'DECISION_MAKER' => 'صاحب القرار',
            'KEY_USER' => 'المستخدم الرئيسي',
            'OWNER' => 'المالك / الإدارة',
            'OTHER' => 'أخرى',
        ];
    }

    public static function followUpTypes(): array
    {
        return [
            'CALL' => 'اتصال بالعميل',
            'MEETING' => 'اجتماع مع العميل',
            'TRAINING' => 'تدريب أو شرح',
            'SUPPORT' => 'حل مشكلة أو دعم',
            'PAYMENT' => 'متابعة سداد',
            'RENEWAL' => 'متابعة تجديد',
            'SALES' => 'متابعة فرصة بيع',
            'OTHER' => 'متابعة أخرى',
        ];
    }

    public static function priorities(): array
    {
        return [
            'normal' => 'عادية',
            'medium' => 'متوسطة',
            'high' => 'مرتفعة',
            'critical' => 'حرجة',
        ];
    }

    public static function taskStatuses(): array
    {
        return [
            'open' => 'مفتوحة',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
        ];
    }

    public static function runStatuses(): array
    {
        return [
            'active' => 'جارية',
            'awaiting_resolution' => 'بانتظار الحسم',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
        ];
    }

    public static function triggerTypes(): array
    {
        return [
            'event' => 'حدث',
            'lifecycle' => 'مرحلة العميل',
            'flag' => 'تنبيه متابعة',
            'signal' => 'فرصة تجارية',
            'health' => 'رضا العميل',
            'review' => 'مراجعة دورية',
            'manual' => 'تشغيل يدوي',
        ];
    }

    public static function eventTypes(): array
    {
        return [
            'lifecycle' => 'تغيير مرحلة العميل',
            'health' => 'تقييم رضا العميل',
            'flag' => 'تنبيه متابعة',
            'signal' => 'فرصة تجارية',
            'contact' => 'جهة اتصال',
            'playbook' => 'خطة متابعة',
            'event' => 'حدث',
        ];
    }

    public static function codeLabels(): array
    {
        return [
            'NEW' => 'عميل جديد',
            'ACTIVATING' => 'تحت التفعيل',
            'STABILIZING' => 'فترة الاستقرار',
            'STABLE' => 'مستقر',
            'LOW_ADOPTION' => 'استخدام منخفض',
            'DORMANT' => 'خامل / متوقف',
            'AT_RISK' => 'معرض للفقد',
            'CHURNED' => 'مفقود / منتهي',
            'FIRST_CONTRACT' => 'إنشاء أول عقد',
            'ACCOUNTANT_CHANGED' => 'تغيير المحاسب',
            'FINANCE_MANAGER_CHANGED' => 'تغيير المدير المالي',
            'MANAGEMENT_CHANGED' => 'تغيير الإدارة أو صاحب القرار',
            'PROCESS_CHANGED' => 'تغيير إجراءات العمل',
            'COMPANY_EXPANSION' => 'توسع الشركة',
            'LIMITED_USAGE' => 'استخدام محدود للميزات',
            'LOW_USAGE' => 'انخفاض الاستخدام',
            'REPEATED_COMPLAINTS' => 'شكاوى متكررة',
            'CRITICAL_ISSUE' => 'مشكلة حرجة',
            'HIGH_SUPPORT_LOAD' => 'احتياج دعم مرتفع',
            'RENEWAL_DUE' => 'تجديد قريب',
            'PAYMENT_OVERDUE' => 'تأخر مالي',
            'CANCELLATION_REQUESTED' => 'طلب إلغاء',
            'REFERRAL_READY' => 'مرشح لترشيح عميل جديد',
            'TESTIMONIAL_READY' => 'مرشح لشهادة عميل',
            'AMBASSADOR' => 'عميل سفير',
            'UPSELL' => 'زيادة الاشتراك أو المستخدمين',
            'CROSS_SELL' => 'بيع خدمة أو موديول إضافي',
            'EXPANSION_SALE' => 'توسع العميل',
            'RENEWAL' => 'تجديد',
            'VERY_SATISFIED' => 'راضٍ جدًا',
            'SATISFIED' => 'راضٍ',
            'NEUTRAL' => 'محايد',
            'DISSATISFIED' => 'غير راضٍ',
            'VERY_DISSATISFIED' => 'غير راضٍ جدًا',
            'STATUS_CHANGED' => 'تم تغيير مرحلة العميل',
            'STATUS_REASSESSED' => 'تمت إعادة تقييم العميل',
            'HEALTH_CHANGED' => 'تم تحديث رضا العميل',
            'FLAG_OPENED' => 'تم فتح تنبيه متابعة',
            'FLAG_RESOLVED' => 'تم إغلاق تنبيه متابعة',
            'SIGNAL_OPENED' => 'تم تسجيل فرصة تجارية',
            'SIGNAL_CLOSED' => 'تم حسم فرصة تجارية',
            'CONTACT_ADDED' => 'تم تحديث جهة اتصال',
            'PLAYBOOK_STARTED' => 'تم بدء خطة متابعة',
            'PLAYBOOK_AWAITING_RESOLUTION' => 'خطة المتابعة تنتظر الحسم',
            'PLAYBOOK_COMPLETED' => 'تم إكمال خطة المتابعة',
            'TASK_COMPLETED' => 'تم إكمال مهمة متابعة',
            'MANUAL_FOLLOW_UP_CREATED' => 'تم تسجيل متابعة قادمة',
            'AUTO_FOLLOW_UP_SCHEDULED' => 'تم جدولة متابعة تلقائية',
            'CALL' => 'اتصال بالعميل',
            'MEETING' => 'اجتماع مع العميل',
            'TRAINING' => 'تدريب أو شرح',
            'SUPPORT' => 'حل مشكلة أو دعم',
            'PAYMENT' => 'متابعة سداد',
            'SALES' => 'متابعة فرصة بيع',
            'OTHER' => 'متابعة أخرى',
        ];
    }

    public static function label(?string $code): string
    {
        if ($code === null || $code === '') return '—';

        return self::codeLabels()[$code]
            ?? self::signalStatuses()[$code]
            ?? self::taskStatuses()[$code]
            ?? self::runStatuses()[$code]
            ?? self::priorities()[$code]
            ?? self::attentionLevels()[$code]
            ?? self::triggerTypes()[$code]
            ?? self::eventTypes()[$code]
            ?? $code;
    }
}
