<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class IntermediaryCommissionPayment extends Model
{
    protected $fillable=['number','intermediary_commission_id','payment_date','amount','payment_method','reference_no','notes','created_by'];
    protected function casts(): array { return ['payment_date'=>'date','amount'=>'decimal:2']; }
    public function commission(): BelongsTo { return $this->belongsTo(IntermediaryCommission::class,'intermediary_commission_id'); }
}
