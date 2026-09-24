<?php

return [
    'default_currency' => env('FINANCE_DEFAULT_CURRENCY', 'SAR'),
    'currencies' => [
        'SAR' => 'ريال سعودي',
        'USD' => 'دولار أمريكي',
        'EGP' => 'جنيه مصري',
    ],
    'tax_rate' => (float) env('FINANCE_TAX_RATE', 15),
    'included_users_per_module_one_time' => 3,
    'startup_discount_percentage' => 50,
    'quotation_valid_days' => 15,
    // Create recurring receivables this many days before their actual due date.
    'receivable_generation_lead_days' => (int) env('FINANCE_RECEIVABLE_LEAD_DAYS', 15),
    'dashboard_cache_seconds' => 60,
    'pagination' => 20,
];
