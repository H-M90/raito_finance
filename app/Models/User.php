<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name','email','password','is_active','role_id'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean']; }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('role.permissions:id,code');
        if (! $this->is_active || ! $this->role) return false;
        if ($this->role->code === 'admin') return true;
        return $this->role->permissions->contains('code',$permission);
    }
}
