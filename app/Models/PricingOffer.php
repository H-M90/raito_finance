<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class PricingOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code','name','type','customer_segment','billing_cycle','discount_percentage','fixed_discount_amount','minimum_users',
        'free_users','repeat_for_each_threshold','starts_at','ends_at','is_stackable','is_active','priority','notes',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:4',
            'fixed_discount_amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'repeat_for_each_threshold' => 'boolean',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'pricing_offer_product');
    }

    public function scopeAvailable(Builder $query, \DateTimeInterface|string|null $date = null): Builder
    {
        $date = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $date));
    }

    public function appliesToCycle(string $cycle): bool
    {
        return $this->billing_cycle === 'all' || $this->billing_cycle === $cycle;
    }

    public function appliesToSegment(string $segment): bool
    {
        return $this->customer_segment === 'all' || $this->customer_segment === $segment;
    }

    public function appliesToProduct(int $productId): bool
    {
        return ! $this->relationLoaded('products') || $this->products->isEmpty() || $this->products->contains('id', $productId);
    }

    public function bundleProductIds(): array
    {
        if ($this->type !== 'bundle_fixed_discount' || ! $this->relationLoaded('products')) return [];
        return $this->products->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function bundleQualifies(iterable $selectedProductIds): bool
    {
        if ($this->type !== 'bundle_fixed_discount') return false;
        $required = collect($this->bundleProductIds());
        if ($required->count() < 2) return false;
        $selected = collect($selectedProductIds)->map(fn ($id) => (int) $id)->unique();
        return $required->diff($selected)->isEmpty();
    }
}
