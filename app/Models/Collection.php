<?php
namespace App\Models;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Collection extends Model
{
    use SoftDeletes, HasAttachments;
    protected $fillable=['number','customer_id','bank_statement_row_id','collection_date','currency','amount','allocated_amount','unallocated_amount','payment_method','reference_no','attachment','collector_name','status','notes','created_by','updated_by','cancelled_at','cancelled_by','cancellation_reason','reopened_at','reopened_by'];
    protected function casts(): array { return ['collection_date'=>'date','amount'=>'decimal:2','allocated_amount'=>'decimal:2','unallocated_amount'=>'decimal:2','cancelled_at'=>'datetime','reopened_at'=>'datetime']; }
    public function bankStatementRow(): BelongsTo { return $this->belongsTo(BankStatementRow::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function allocations(): HasMany { return $this->hasMany(CollectionAllocation::class); }
}
