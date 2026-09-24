<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerCommercialSignal extends Model
{
    protected $fillable=['customer_id','code','status','source','owner_id','estimated_value','currency','due_at','playbook_run_id','notes','metadata'];
    protected function casts(): array{return ['estimated_value'=>'decimal:2','due_at'=>'datetime','metadata'=>'array'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function owner(): BelongsTo{return $this->belongsTo(User::class,'owner_id');}
    public function playbookRun(): BelongsTo{return $this->belongsTo(CustomerPlaybookRun::class,'playbook_run_id');}
}
