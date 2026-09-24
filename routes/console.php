<?php

use App\Models\SalesQuotation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('finance:generate-receivables')->dailyAt('01:00')->withoutOverlapping();

Schedule::call(function (): void {
    SalesQuotation::query()->where('is_current_version', true)->whereIn('status', ['draft','sent','negotiation'])->whereNotNull('valid_until')->whereDate('valid_until', '<', today())->update(['status' => 'expired']);
})->dailyAt('00:30')->name('expire-sales-quotations')->withoutOverlapping();

Schedule::command('customer-success:sync')->dailyAt('07:00')->withoutOverlapping();
