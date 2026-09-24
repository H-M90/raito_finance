<?php
namespace App\Models;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Purchase extends Model
{
    use HasAttachments;
    protected $fillable=['number','supplier_id','supplier_invoice_no','purchase_date','currency','total_amount','status','attachment','notes','created_by','updated_by'];
    protected function casts(): array { return ['purchase_date'=>'date','total_amount'=>'decimal:2']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
