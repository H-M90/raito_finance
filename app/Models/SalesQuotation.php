<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesQuotation extends Model
{
    use SoftDeletes, HasAttachments;

    protected $fillable = [
        'number','customer_id','parent_quotation_id','version_number','is_current_version','activity_type',
        'quotation_date','valid_until','currency','sales_owner_id','lead_source','intermediary_id','commission_type',
        'commission_value','commission_total','billing_cycle','station_count','pts_count','sensor_count','subtotal',
        'discount_total','promotional_discount_total','net_total','tax_rate','tax_total','grand_total','maintenance_total',
        'pricing_snapshot','status','payment_terms','expected_execution_period','notes','rejection_reason','lost_to_competitor','sent_at','converted_at','converted_by','created_by','updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date'=>'date','valid_until'=>'date','sent_at'=>'datetime','is_current_version'=>'boolean',
            'subtotal'=>'decimal:2','discount_total'=>'decimal:2','promotional_discount_total'=>'decimal:2','net_total'=>'decimal:2',
            'tax_rate'=>'decimal:4','tax_total'=>'decimal:2','grand_total'=>'decimal:2','maintenance_total'=>'decimal:2',
            'commission_value'=>'decimal:4','commission_total'=>'decimal:2','pricing_snapshot'=>'array','converted_at'=>'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function salesOwner(): BelongsTo { return $this->belongsTo(User::class, 'sales_owner_id'); }
    public function items(): HasMany { return $this->hasMany(SalesQuotationItem::class)->orderBy('sort_order'); }
    public function intermediary(): BelongsTo { return $this->belongsTo(Intermediary::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class,'parent_quotation_id'); }
    public function versions(): HasMany { return $this->hasMany(self::class,'parent_quotation_id'); }
    public function contract(): HasOne { return $this->hasOne(Contract::class); }
    public function pricingOffers(): BelongsToMany { return $this->belongsToMany(PricingOffer::class,'sales_quotation_pricing_offer'); }
    public function scopeCurrent(Builder $query): Builder { return $query->where('is_current_version',true); }
}
