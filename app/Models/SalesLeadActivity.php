<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesLeadActivity extends Model
{
    protected $fillable=['sales_lead_id','type','description','occurred_at','created_by'];
    protected function casts(): array { return ['occurred_at'=>'datetime']; }
    public function lead(): BelongsTo { return $this->belongsTo(SalesLead::class,'sales_lead_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
