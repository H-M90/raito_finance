<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerSuccessStatus extends Model
{
    protected $fillable=['code','name','default_attention','sort_order','is_active'];
    protected function casts(): array{return ['is_active'=>'boolean'];}
    public function profiles(): HasMany{return $this->hasMany(CustomerSuccessProfile::class,'lifecycle_status_id');}
}
