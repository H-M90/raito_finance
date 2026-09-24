<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSuccessTask extends Model
{
    protected $fillable=['customer_id','playbook_run_id','playbook_step_id','title','description','team','type','status','priority','assigned_to','due_at','completed_at','completed_by','completion_notes','created_by'];
    protected function casts(): array{return ['due_at'=>'datetime','completed_at'=>'datetime'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function playbookRun(): BelongsTo{return $this->belongsTo(CustomerPlaybookRun::class,'playbook_run_id');}
    public function step(): BelongsTo{return $this->belongsTo(CustomerPlaybookStep::class,'playbook_step_id');}
    public function assignee(): BelongsTo{return $this->belongsTo(User::class,'assigned_to');}
    public function completedBy(): BelongsTo{return $this->belongsTo(User::class,'completed_by');}
    public function scopeOpen(Builder $q): Builder{return $q->whereIn('status',['open','in_progress']);}
}
