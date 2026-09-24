<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractAddendumInstallment extends Model
{
    protected $fillable=['contract_addendum_id','name','percentage','due_date','net_amount','tax_amount','total_amount','sort_order','notes'];
    protected function casts(): array { return ['percentage'=>'decimal:4','due_date'=>'date','net_amount'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2']; }
    public function addendum(): BelongsTo { return $this->belongsTo(ContractAddendum::class,'contract_addendum_id'); }
}
