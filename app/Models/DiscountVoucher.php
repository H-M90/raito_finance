<?php
namespace App\Models;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DiscountVoucher extends Model
{
    use SoftDeletes, HasAttachments;
    protected $fillable=['number','customer_id','contract_id','receivable_id','voucher_date','currency','amount','reason','status','notes','created_by','updated_by','cancelled_at','cancelled_by','cancellation_reason'];
    protected function casts(): array { return ['voucher_date'=>'date','amount'=>'decimal:2','cancelled_at'=>'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
}
