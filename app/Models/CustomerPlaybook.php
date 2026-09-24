<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerPlaybook extends Model
{
    protected $fillable=['code','name','trigger_type','trigger_code','owner_role','sla_hours','exit_criteria','result_code','is_active'];
    protected function casts(): array{return ['is_active'=>'boolean'];}
    public function steps(): HasMany{return $this->hasMany(CustomerPlaybookStep::class,'playbook_id')->orderBy('sort_order');}
    public function runs(): HasMany{return $this->hasMany(CustomerPlaybookRun::class,'playbook_id');}
}
