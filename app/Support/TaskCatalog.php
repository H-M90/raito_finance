<?php

namespace App\Support;

class TaskCatalog
{
    public const TEAMS = [
        'sales' => 'المبيعات',
        'account_management' => 'إدارة الحسابات',
    ];

    public const TYPES = [
        'follow_up' => 'متابعة',
        'meeting' => 'اجتماع',
        'demo' => 'عرض توضيحي',
        'quotation' => 'عرض سعر',
        'contract' => 'عقد',
        'onboarding' => 'تهيئة العميل',
        'training' => 'تدريب',
        'support' => 'دعم',
        'customer_success' => 'نجاح العميل',
        'renewal' => 'تجديد',
        'collection' => 'تحصيل',
        'internal' => 'مهمة داخلية',
        'other' => 'أخرى',
    ];

    public const LEGACY_FOLLOW_UP_TYPES = [
        'call' => 'مكالمة',
        'message' => 'رسالة',
        'whatsapp' => 'واتساب',
        'email' => 'بريد',
    ];

    public const PRIORITIES = [
        'critical' => 'حرجة',
        'high' => 'عالية',
        'medium' => 'متوسطة',
        'low' => 'منخفضة',
        'normal' => 'منخفضة',
    ];

    public static function teamLabel(?string $code): string
    {
        return self::TEAMS[$code] ?? ($code ?: '—');
    }

    public static function typeLabel(?string $code): string
    {
        if (isset(self::TYPES[$code])) return self::TYPES[$code];
        if (isset(self::LEGACY_FOLLOW_UP_TYPES[$code])) return self::LEGACY_FOLLOW_UP_TYPES[$code];
        return $code ?: 'مهمة';
    }

    public static function normalizedType(?string $code): string
    {
        if (in_array($code, ['call','message','whatsapp','email'], true)) return 'follow_up';
        return $code ?: 'other';
    }

    public static function priorityLabel(?string $code): string
    {
        return self::PRIORITIES[$code] ?? ($code ?: '—');
    }

    public static function normalizedPriority(?string $code): string
    {
        return $code === 'normal' ? 'low' : ($code ?: 'medium');
    }
}
