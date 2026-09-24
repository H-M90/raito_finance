<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code','name','type','unit','default_sale_price','billing_cycle','supports_user_pricing','required_product_id',
        'included_users_one_time','extra_user_price_one_time','user_price_monthly','user_price_annual',
        'default_maintenance_rate','is_active','description',
    ];

    protected function casts(): array
    {
        return [
            'default_sale_price'=>'decimal:2','default_maintenance_rate'=>'decimal:4','is_active'=>'boolean',
            'supports_user_pricing'=>'boolean','extra_user_price_one_time'=>'decimal:2',
            'user_price_monthly'=>'decimal:2','user_price_annual'=>'decimal:2',
        ];
    }

    public function requiredProduct(): BelongsTo { return $this->belongsTo(self::class, 'required_product_id'); }
    public function dependentProducts(): HasMany { return $this->hasMany(self::class, 'required_product_id'); }
    public function contractItems(): HasMany { return $this->hasMany(ContractItem::class); }
    public function quotationItems(): HasMany { return $this->hasMany(SalesQuotationItem::class); }
    public function addendumItems(): HasMany { return $this->hasMany(ContractAddendumItem::class); }
    public function pricingOffers(): BelongsToMany { return $this->belongsToMany(PricingOffer::class, 'pricing_offer_product'); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }

    public function userPriceForCycle(string $cycle): float
    {
        return match ($cycle) {
            'monthly' => (float) $this->user_price_monthly,
            'annual' => (float) $this->user_price_annual,
            default => (float) $this->extra_user_price_one_time,
        };
    }
}
