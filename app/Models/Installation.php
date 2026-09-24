<?php
namespace App\Models;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Installation extends Model
{
    use HasAttachments;
    protected $fillable=['number','customer_id','contract_id','planned_date','installation_date','team_name','status','handover_attachment','notes','created_by','updated_by','cancelled_at','cancelled_by','cancellation_reason','reopened_at','reopened_by'];
    protected function casts(): array { return ['planned_date'=>'date','installation_date'=>'date','cancelled_at'=>'datetime','reopened_at'=>'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function stations(): HasMany { return $this->hasMany(InstallationStation::class); }
}
