<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSuccessProfile extends Model
{
    protected $fillable=['customer_id','lifecycle_status_id','health','attention_level','owner_id','next_review_at','activated_at','go_live_at','stabilized_at','churned_at','last_health_change_at','notes'];
    protected function casts(): array{return ['next_review_at'=>'datetime','activated_at'=>'datetime','go_live_at'=>'datetime','stabilized_at'=>'datetime','churned_at'=>'datetime','last_health_change_at'=>'datetime'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function lifecycleStatus(): BelongsTo{return $this->belongsTo(CustomerSuccessStatus::class,'lifecycle_status_id');}
    public function owner(): BelongsTo{return $this->belongsTo(User::class,'owner_id');}
}
