<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesLeadTask extends Model
{
    protected $fillable=['sales_lead_id','title','description','team','type','priority','due_at','status','is_follow_up','assigned_to','completed_at','completed_by','created_by'];
    protected function casts(): array { return ['due_at'=>'datetime','completed_at'=>'datetime','is_follow_up'=>'boolean']; }
    public function lead(): BelongsTo { return $this->belongsTo(SalesLead::class,'sales_lead_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class,'completed_by'); }
    public function scopeOpen(Builder $query): Builder { return $query->where('status','open'); }
}
