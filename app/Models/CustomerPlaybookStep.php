<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerPlaybookStep extends Model
{
    protected $fillable=['playbook_id','sort_order','title','description','due_offset_hours','default_priority'];
    public function playbook(): BelongsTo{return $this->belongsTo(CustomerPlaybook::class,'playbook_id');}
}
