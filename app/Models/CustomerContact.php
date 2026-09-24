<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    protected $fillable = ['customer_id', 'name', 'role_code', 'phone', 'email', 'is_primary', 'is_active', 'started_at', 'ended_at', 'notes'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_active' => 'boolean', 'started_at' => 'date', 'ended_at' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
