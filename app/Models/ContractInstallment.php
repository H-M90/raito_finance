<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractInstallment extends Model
{
    protected $fillable = ['contract_id','name','percentage','due_date','net_amount','tax_amount','total_amount','sort_order','notes'];
    protected function casts(): array { return ['due_date'=>'date','percentage'=>'decimal:4','net_amount'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2']; }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
