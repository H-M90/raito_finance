<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class BankStatementBatch extends Model
{
    protected $fillable=['uuid','bank_name','account_name','original_filename','stored_path','currency','sheet_name','header_row','mapping','detected_headers','preview_rows','status','total_rows','total_debit','total_credit','created_by','approved_by','approved_at'];
    protected function casts(): array { return ['mapping'=>'array','detected_headers'=>'array','preview_rows'=>'array','total_debit'=>'decimal:2','total_credit'=>'decimal:2','approved_at'=>'datetime']; }
    public function rows(): HasMany { return $this->hasMany(BankStatementRow::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
