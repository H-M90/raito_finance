<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesLeadNote extends Model
{
    protected $fillable=['sales_lead_id','body','created_by'];
    public function lead(): BelongsTo { return $this->belongsTo(SalesLead::class,'sales_lead_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
