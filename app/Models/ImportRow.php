<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportRow extends Model
{
    protected $fillable = ['import_batch_id','row_number','sheet_name','row_type','payload','status','errors','contract_id','imported_model_type','imported_model_id'];
    protected function casts(): array { return ['payload'=>'array','errors'=>'array']; }
}
