<?php
namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ContractAddendum extends Model
{
    use HasAttachments;
    protected $fillable=['number','contract_id','addendum_date','service_start_date','subtotal','discount_total','promotional_discount_total','net_total','tax_total','grand_total','maintenance_total','pts_count','sensor_count','next_maintenance_date','status','notes','created_by','updated_by','pricing_snapshot','cancelled_at','cancelled_by','cancellation_reason','reopened_at','reopened_by'];
    protected function casts(): array { return ['addendum_date'=>'date','service_start_date'=>'date','next_maintenance_date'=>'date','pricing_snapshot'=>'array','cancelled_at'=>'datetime','reopened_at'=>'datetime','subtotal'=>'decimal:2','discount_total'=>'decimal:2','promotional_discount_total'=>'decimal:2','net_total'=>'decimal:2','tax_total'=>'decimal:2','grand_total'=>'decimal:2','maintenance_total'=>'decimal:2']; }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function items(): HasMany { return $this->hasMany(ContractAddendumItem::class)->orderBy('sort_order'); }
    public function installments(): HasMany { return $this->hasMany(ContractAddendumInstallment::class)->orderBy('sort_order'); }
    public function pricingOffers(): BelongsToMany { return $this->belongsToMany(PricingOffer::class,'contract_addendum_pricing_offer'); }
    public function receivables(): HasMany { return $this->hasMany(Receivable::class); }
}
