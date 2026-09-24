<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerAttentionFlagType extends Model
{
    protected $fillable=['code','name','default_severity','default_days','source','playbook_code','is_active'];
    protected function casts(): array{return ['is_active'=>'boolean'];}
}
