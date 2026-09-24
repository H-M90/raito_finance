<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ImportBatch extends Model
{
    protected $fillable = ['uuid','type','original_filename','stored_path','status','total_rows','valid_rows','invalid_rows','summary','created_by','committed_at','processed_rows','progress_percentage','error_file_path','failure_message'];
    protected function casts(): array { return ['summary'=>'array','committed_at'=>'datetime']; }
    public function rows(): HasMany { return $this->hasMany(ImportRow::class); }
}
