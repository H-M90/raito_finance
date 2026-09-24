<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $fillable = [
        'code','name','country','city','address','commercial_registration_no','tax_no','contact_name','phone',
        'email','sales_owner_id','segment','status','inactive_reason','inactive_at','notes','source_type','source_id',
    ];

    protected function casts(): array { return ['inactive_at'=>'date']; }

    public function salesOwner(): BelongsTo { return $this->belongsTo(User::class, 'sales_owner_id'); }
    public function quotations(): HasMany { return $this->hasMany(SalesQuotation::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function stations(): HasMany { return $this->hasMany(Station::class); }
    public function receivables(): HasMany { return $this->hasMany(Receivable::class); }
    public function collections(): HasMany { return $this->hasMany(Collection::class); }
    public function collectionFollowUps(): HasMany { return $this->hasMany(CollectionFollowUp::class); }
    public function contacts(): HasMany { return $this->hasMany(CustomerContact::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(CustomerLedgerEntry::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function sourceSalesLead(): HasOne { return $this->hasOne(SalesLead::class, 'customer_id'); }
    public function successProfile(): HasOne { return $this->hasOne(CustomerSuccessProfile::class); }
    public function attentionFlags(): HasMany { return $this->hasMany(CustomerAttentionFlag::class); }
    public function commercialSignals(): HasMany { return $this->hasMany(CustomerCommercialSignal::class); }
    public function successTasks(): HasMany { return $this->hasMany(CustomerSuccessTask::class); }
    public function playbookRuns(): HasMany { return $this->hasMany(CustomerPlaybookRun::class); }
    public function successEvents(): HasMany { return $this->hasMany(CustomerSuccessEvent::class); }
    public function successStatusHistory(): HasMany { return $this->hasMany(CustomerSuccessStatusHistory::class); }

    public function scopeActive(Builder $query): Builder { return $query->where('status', 'active'); }
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) return $query;
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name','like',"%{$term}%")->orWhere('code','like',"%{$term}%")
                ->orWhere('phone','like',"%{$term}%")->orWhere('tax_no','like',"%{$term}%")
                ->orWhere('commercial_registration_no','like',"%{$term}%");
        });
    }
    public function timelineEvents(): HasMany { return $this->hasMany(TimelineEvent::class); }
}
