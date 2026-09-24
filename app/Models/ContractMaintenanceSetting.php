<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractMaintenanceSetting extends Model
{
    protected $fillable = ['contract_id','effective_from','mode','annual_amount','reason','created_by'];

    protected function casts(): array
    {
        return ['effective_from'=>'date','annual_amount'=>'decimal:2'];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
