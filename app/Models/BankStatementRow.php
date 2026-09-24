<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class BankStatementRow extends Model
{
    protected $fillable=['bank_statement_batch_id','row_number','transaction_date','description','reference_no','debit','credit','amount','direction','classification','status','expense_category_id','expense_description','beneficiary','expense_customer_id','expense_contract_id','collection_customer_id','collection_notes','allocation_data','source_payload','validation_errors','generated_type','generated_id'];
    protected function casts(): array { return ['transaction_date'=>'date','debit'=>'decimal:2','credit'=>'decimal:2','amount'=>'decimal:2','allocation_data'=>'array','source_payload'=>'array','validation_errors'=>'array']; }
    public function batch(): BelongsTo { return $this->belongsTo(BankStatementBatch::class,'bank_statement_batch_id'); }
    public function expenseCategory(): BelongsTo { return $this->belongsTo(ExpenseCategory::class); }
    public function expenseCustomer(): BelongsTo { return $this->belongsTo(Customer::class,'expense_customer_id'); }
    public function expenseContract(): BelongsTo { return $this->belongsTo(Contract::class,'expense_contract_id'); }
    public function collectionCustomer(): BelongsTo { return $this->belongsTo(Customer::class,'collection_customer_id'); }
    public function generated(): MorphTo { return $this->morphTo(__FUNCTION__,'generated_type','generated_id'); }
}
