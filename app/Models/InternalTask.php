<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalTask extends Model
{
    protected $fillable = [
        'title','description','team','type','priority','due_at','status','assigned_to',
        'completed_at','completed_by','created_by','updated_by',
    ];

    protected function casts(): array
    {
        return ['due_at'=>'datetime','completed_at'=>'datetime'];
    }

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
