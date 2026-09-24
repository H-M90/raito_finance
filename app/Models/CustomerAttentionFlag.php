<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerAttentionFlag extends Model
{
    protected $fillable=['customer_id','flag_type_id','severity','source','status','opened_at','due_at','resolved_at','opened_by','resolved_by','playbook_run_id','notes','metadata'];
    protected function casts(): array{return ['opened_at'=>'datetime','due_at'=>'datetime','resolved_at'=>'datetime','metadata'=>'array'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function type(): BelongsTo{return $this->belongsTo(CustomerAttentionFlagType::class,'flag_type_id');}
    public function playbookRun(): BelongsTo{return $this->belongsTo(CustomerPlaybookRun::class,'playbook_run_id');}
    public function openedBy(): BelongsTo{return $this->belongsTo(User::class,'opened_by');}
    public function resolvedBy(): BelongsTo{return $this->belongsTo(User::class,'resolved_by');}
}
