<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerLedgerEntry extends Model
{
    protected $fillable = ['customer_id', 'contract_id', 'entry_date', 'currency', 'entry_type', 'source_type', 'source_id', 'reference_no', 'description', 'debit', 'credit', 'is_reversed', 'posted_at', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'is_reversed' => 'boolean', 'posted_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
