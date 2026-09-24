<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerPlaybookRun extends Model
{
    protected $fillable=['customer_id','playbook_id','trigger_type','trigger_code','trigger_id','status','owner_id','started_at','due_at','completed_at','completed_by','result_code','notes'];
    protected function casts(): array{return ['started_at'=>'datetime','due_at'=>'datetime','completed_at'=>'datetime'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function playbook(): BelongsTo{return $this->belongsTo(CustomerPlaybook::class,'playbook_id');}
    public function owner(): BelongsTo{return $this->belongsTo(User::class,'owner_id');}
    public function tasks(): HasMany{return $this->hasMany(CustomerSuccessTask::class,'playbook_run_id')->orderBy('due_at');}
}
