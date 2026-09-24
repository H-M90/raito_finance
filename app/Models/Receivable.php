<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Money;
use Illuminate\Support\Carbon;

class Receivable extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'customer_id', 'contract_id', 'contract_addendum_id', 'source_type', 'source_id', 'type', 'name', 'due_date', 'currency', 'net_amount', 'tax_amount', 'total_amount', 'collected_amount', 'discounted_amount', 'remaining_amount', 'status', 'is_opening_balance', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'collected_amount' => 'decimal:2', 'discounted_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'is_opening_balance' => 'boolean'];
    }

    public function setDueDateAttribute(\DateTimeInterface|string|null $value): void
    {
        $this->attributes['due_date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collectionAllocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('remaining_amount', '>', 0)->whereNotIn('status', ['cancelled', 'waived']);
    }

    public function refreshStatus(): void
    {
        $total = Money::minor($this->total_amount);
        $collected = Money::minor($this->collected_amount);
        $discounted = Money::minor($this->discounted_amount);
        $remainingMinor = max(0, $total - $collected - $discounted);

        // Imported legacy dues intentionally start without an approved value.
        // They must never look paid simply because their placeholder amount is zero.
        if ($this->type === 'legacy_import_due' && $total === 0 && $collected === 0 && $discounted === 0) {
            $this->forceFill(['remaining_amount' => '0.00', 'status' => 'needs_review'])->save();
            return;
        }

        $status = match (true) {
            $remainingMinor === 0 => 'paid',
            $collected > 0 => 'partially_paid',
            $this->due_date->isPast() => 'overdue',
            $this->due_date->isToday() => 'due',
            default => 'future',
        };
        $this->forceFill(['remaining_amount' => Money::decimal($remainingMinor), 'status' => $status])->save();
    }
}
