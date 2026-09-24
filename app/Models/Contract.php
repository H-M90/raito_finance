<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use SoftDeletes, HasAttachments;

    protected $fillable = [
        'number','customer_id','sales_quotation_id','activity_type','domain','contract_date','service_start_date','billing_cycle',
        'currency','tax_rate','subtotal','discount_total','promotional_discount_total','net_total','tax_total','grand_total',
        'maintenance_total','pts_count','sensor_count','sales_owner_id','intermediary_id','commission_type','commission_value',
        'commission_total','commission_due_basis','status','calculation_start_date','maintenance_paid_until','next_billing_date',
        'next_maintenance_date','opening_receivable_balance','opening_maintenance_balance','previous_collections_total',
        'is_imported','notes','created_by','updated_by','pricing_snapshot','cancelled_at','cancelled_by','cancellation_reason','reopened_at','reopened_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_date'=>'date','service_start_date'=>'date','calculation_start_date'=>'date','maintenance_paid_until'=>'date',
            'next_billing_date'=>'date','next_maintenance_date'=>'date','subtotal'=>'decimal:2','discount_total'=>'decimal:2',
            'promotional_discount_total'=>'decimal:2','net_total'=>'decimal:2','tax_total'=>'decimal:2','grand_total'=>'decimal:2',
            'maintenance_total'=>'decimal:2','commission_value'=>'decimal:4','commission_total'=>'decimal:2',
            'opening_receivable_balance'=>'decimal:2','opening_maintenance_balance'=>'decimal:2',
            'previous_collections_total'=>'decimal:2','is_imported'=>'boolean','pricing_snapshot'=>'array','cancelled_at'=>'datetime','reopened_at'=>'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function salesOwner(): BelongsTo { return $this->belongsTo(User::class, 'sales_owner_id'); }
    public function quotation(): BelongsTo { return $this->belongsTo(SalesQuotation::class,'sales_quotation_id'); }
    public function intermediary(): BelongsTo { return $this->belongsTo(Intermediary::class); }
    public function items(): HasMany { return $this->hasMany(ContractItem::class)->orderBy('sort_order'); }
    public function installments(): HasMany { return $this->hasMany(ContractInstallment::class)->orderBy('sort_order'); }
    public function addendums(): HasMany { return $this->hasMany(ContractAddendum::class); }
    public function receivables(): HasMany { return $this->hasMany(Receivable::class); }
    public function maintenanceSettings(): HasMany { return $this->hasMany(ContractMaintenanceSetting::class)->orderBy('effective_from')->orderBy('id'); }
    public function pricingOffers(): BelongsToMany { return $this->belongsToMany(PricingOffer::class,'contract_pricing_offer'); }
    public function stations(): BelongsToMany { return $this->belongsToMany(Station::class,'contract_station')->withPivot(['pts_count','sensor_count'])->withTimestamps(); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function purchaseAllocations(): HasMany { return $this->hasMany(PurchaseAllocation::class); }
    public function installations(): HasMany { return $this->hasMany(Installation::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(CustomerLedgerEntry::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('status','active'); }
}
