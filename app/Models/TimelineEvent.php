<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
class TimelineEvent extends Model
{
    protected $fillable=['customer_id','contract_id','event_at','event_type','title','description','source_type','source_id','url','created_by'];
    protected function casts(): array { return ['event_at'=>'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function source(): MorphTo { return $this->morphTo(); }
}
