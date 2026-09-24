<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PurchaseAllocation extends Model
{
    protected $fillable=['purchase_item_id','customer_id','contract_id','station_id','quantity','amount','notes'];
    protected function casts(): array { return ['quantity'=>'decimal:3','amount'=>'decimal:2']; }
    public function item(): BelongsTo { return $this->belongsTo(PurchaseItem::class,'purchase_item_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function station(): BelongsTo { return $this->belongsTo(Station::class); }
}
