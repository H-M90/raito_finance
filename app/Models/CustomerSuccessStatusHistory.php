<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSuccessStatusHistory extends Model
{
    protected $table='customer_success_status_history';
    protected $fillable=['customer_id','from_status_id','to_status_id','reason','changed_by','changed_at'];
    protected function casts(): array{return ['changed_at'=>'datetime'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function fromStatus(): BelongsTo{return $this->belongsTo(CustomerSuccessStatus::class,'from_status_id');}
    public function toStatus(): BelongsTo{return $this->belongsTo(CustomerSuccessStatus::class,'to_status_id');}
    public function changedBy(): BelongsTo{return $this->belongsTo(User::class,'changed_by');}
}
