<?php
namespace App\Models;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Expense extends Model
{
    use HasAttachments;
    protected $fillable=['number','expense_date','expense_category_id','bank_statement_row_id','description','beneficiary','amount','currency','payment_method','customer_id','contract_id','attachment','status','notes','created_by','updated_by','cancelled_at','cancelled_by','cancellation_reason','reopened_at','reopened_by'];
    protected function casts(): array { return ['expense_date'=>'date','amount'=>'decimal:2','cancelled_at'=>'datetime','reopened_at'=>'datetime']; }
    public function bankStatementRow(): BelongsTo { return $this->belongsTo(BankStatementRow::class); }
    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class,'expense_category_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
