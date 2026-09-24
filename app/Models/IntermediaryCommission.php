<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class IntermediaryCommission extends Model
{
    protected $fillable=['number','intermediary_id','contract_id','collection_id','collection_allocation_id','due_date','currency','basis','base_amount','commission_type','commission_value','amount','paid_amount','remaining_amount','status','notes','created_by'];
    protected function casts(): array { return ['due_date'=>'date','base_amount'=>'decimal:2','commission_value'=>'decimal:4','amount'=>'decimal:2','paid_amount'=>'decimal:2','remaining_amount'=>'decimal:2']; }
    public function intermediary(): BelongsTo { return $this->belongsTo(Intermediary::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function collection(): BelongsTo { return $this->belongsTo(Collection::class); }
    public function payments(): HasMany { return $this->hasMany(IntermediaryCommissionPayment::class); }
}
