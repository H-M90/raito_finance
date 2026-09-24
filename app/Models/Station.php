<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Station extends Model
{
    protected $fillable=['customer_id','code','name','city','location','contact_name','phone','is_active','relationship_start_date','notes'];
    protected function casts(): array { return ['is_active'=>'boolean','relationship_start_date'=>'date']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function installationRows(): HasMany { return $this->hasMany(InstallationStation::class); }
    public function contracts(): BelongsToMany { return $this->belongsToMany(Contract::class,'contract_station')->withPivot(['pts_count','sensor_count'])->withTimestamps(); }
}
