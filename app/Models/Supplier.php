<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model { protected $fillable=['name','phone','email','notes','is_active']; protected function casts(): array { return ['is_active'=>'boolean']; } }
