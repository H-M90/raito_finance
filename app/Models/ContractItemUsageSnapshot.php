<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractItemUsageSnapshot extends Model
{
    protected $fillable = ['contract_item_id', 'active_quantity', 'inactive_quantity', 'total_quantity', 'captured_at'];

    protected function casts(): array
    {
        return ['captured_at' => 'date', 'active_quantity' => 'decimal:3', 'inactive_quantity' => 'decimal:3', 'total_quantity' => 'decimal:3'];
    }

    public function contractItem(): BelongsTo
    {
        return $this->belongsTo(ContractItem::class);
    }
}
