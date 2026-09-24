<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Intermediary extends Model
{
    protected $fillable = ['name','phone','email','is_active','notes'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
}
