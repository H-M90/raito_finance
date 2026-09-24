<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesLeadInterest extends Model
{
    protected $fillable=['sales_lead_id','name','created_by'];
    public function lead(): BelongsTo { return $this->belongsTo(SalesLead::class,'sales_lead_id'); }
}
