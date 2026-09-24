<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CollectionAllocation extends Model
{
    protected $fillable = ['collection_id','receivable_id','amount'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function collection(): BelongsTo { return $this->belongsTo(Collection::class); }
    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
}
