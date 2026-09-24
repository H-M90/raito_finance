<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractAddendumItem extends Model
{
    protected $fillable=['contract_addendum_id','product_id','quantity','requested_users','included_users','promotional_free_users','billable_users','total_licensed_users','unit_price','user_unit_price','user_total','discount_value','promotional_discount_value','line_subtotal','line_net','maintenance_rate','maintenance_annual','notes','sort_order'];
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','user_unit_price'=>'decimal:2','user_total'=>'decimal:2','discount_value'=>'decimal:2','promotional_discount_value'=>'decimal:2','line_subtotal'=>'decimal:2','line_net'=>'decimal:2','maintenance_rate'=>'decimal:4','maintenance_annual'=>'decimal:2']; }
    public function addendum(): BelongsTo { return $this->belongsTo(ContractAddendum::class,'contract_addendum_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
