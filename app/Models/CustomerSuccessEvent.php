<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class CustomerSuccessEvent extends Model
{
    protected $fillable=['customer_id','event_type','event_code','source_type','source_id','occurred_at','payload','created_by','processed_at'];
    protected function casts(): array{return ['occurred_at'=>'datetime','processed_at'=>'datetime','payload'=>'array'];}
    public function customer(): BelongsTo{return $this->belongsTo(Customer::class);}
    public function source(): MorphTo{return $this->morphTo();}
}
