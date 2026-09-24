<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InstallationStation extends Model
{
    protected $fillable=['installation_id','station_id','pts_installed','sensor_count','installed_at','status','notes'];
    protected function casts(): array { return ['pts_installed'=>'boolean','installed_at'=>'date']; }
    public function installation(): BelongsTo { return $this->belongsTo(Installation::class); }
    public function station(): BelongsTo { return $this->belongsTo(Station::class); }
}
