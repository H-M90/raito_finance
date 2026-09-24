<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ContractItem extends Model
{
    protected $fillable = [
        'contract_id','product_id','active_from','stopped_at','status','stop_reason','stopped_by',
        'quantity','requested_users','included_users','promotional_free_users','billable_users','total_licensed_users',
        'unit_price','user_unit_price','user_total','discount_value','promotional_discount_value','line_subtotal','line_net',
        'maintenance_rate','maintenance_annual','notes','sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active_from'=>'date','stopped_at'=>'date','quantity'=>'decimal:3','unit_price'=>'decimal:2','user_unit_price'=>'decimal:2',
            'user_total'=>'decimal:2','discount_value'=>'decimal:2','promotional_discount_value'=>'decimal:2','line_subtotal'=>'decimal:2',
            'line_net'=>'decimal:2','maintenance_rate'=>'decimal:4','maintenance_annual'=>'decimal:2',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stoppedBy(): BelongsTo { return $this->belongsTo(User::class, 'stopped_by'); }
    public function usageSnapshot(): HasOne { return $this->hasOne(ContractItemUsageSnapshot::class); }

    public function scopeActiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();
        return $query
            ->where(fn (Builder $q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('stopped_at')->orWhereDate('stopped_at', '>', $date));
    }

    public function isActiveOn(\DateTimeInterface|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();
        $start = ($this->active_from ?: $this->contract?->service_start_date)?->copy()?->startOfDay();
        $stop = $this->stopped_at?->copy()?->startOfDay();

        return (! $start || $start->lte($date)) && (! $stop || $stop->gt($date));
    }
}
