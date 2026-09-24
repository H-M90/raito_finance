<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PurchaseItem extends Model
{
    protected $fillable=['purchase_id','product_id','description','quantity','unit_cost','total_cost'];
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_cost'=>'decimal:2','total_cost'=>'decimal:2']; }
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function allocations(): HasMany { return $this->hasMany(PurchaseAllocation::class); }
}
