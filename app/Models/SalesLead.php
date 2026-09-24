<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesLead extends Model
{
    use SoftDeletes;

    public const STAGES = [
        'lead' => 'عميل محتمل جديد',
        'contacted' => 'تم التواصل',
        'qualified' => 'مؤهل',
        'demo' => 'عرض توضيحي',
        'proposal' => 'عرض سعر',
        'evaluation' => 'متابعة التقييم',
        'negotiation' => 'تفاوض',
        'contract_pending' => 'في انتظار العقد',
        'won' => 'تم البيع',
        'nurturing' => 'متابعة مستقبلية',
        'lost' => 'مفقود',
        'disqualified' => 'غير مؤهل',
    ];

    public const RATINGS = [
        'hot' => 'ساخن',
        'promising' => 'واعد',
        'medium' => 'متوسط',
        'low' => 'ضعيف',
    ];

    public const QUALIFICATIONS = [
        'evaluating' => 'قيد التقييم',
        'qualified' => 'مؤهل',
        'unqualified' => 'غير مؤهل',
    ];

    public const PRIORITIES = ['high'=>'عالية','medium'=>'متوسطة','low'=>'منخفضة'];
    public const FOLLOW_UP_TYPES = ['call'=>'مكالمة','meeting'=>'اجتماع','message'=>'رسالة','whatsapp'=>'واتساب','email'=>'بريد'];
    public const TASK_TYPES = [
        'follow_up'=>'متابعة','meeting'=>'اجتماع','demo'=>'عرض توضيحي','quotation'=>'عرض سعر','contract'=>'عقد',
        'onboarding'=>'تهيئة العميل','training'=>'تدريب','support'=>'دعم','customer_success'=>'نجاح العميل',
        'renewal'=>'تجديد','collection'=>'تحصيل','internal'=>'مهمة داخلية','other'=>'أخرى',
        'call'=>'مكالمة','message'=>'رسالة','whatsapp'=>'واتساب','email'=>'بريد',
    ];
    public const SOURCES = ['website'=>'الموقع','referral'=>'إحالة','whatsapp'=>'واتساب','exhibition'=>'معرض','campaign'=>'حملة','linkedin'=>'لينكدإن','cold_call'=>'مكالمة باردة','other'=>'أخرى'];

    protected $fillable = [
        'code','company_name','contact_name','phone','email','city','sector','source','owner_id','stage','rating',
        'responded','qualification','priority','favorite','next_follow_up_at','next_follow_up_type','next_follow_up_priority',
        'next_follow_up_title','last_activity_at','customer_id','converted_at','lost_reason','created_by','updated_by',
    ];

    protected function casts(): array
    {
        return [
            'responded'=>'boolean','favorite'=>'boolean','next_follow_up_at'=>'datetime','last_activity_at'=>'datetime',
            'converted_at'=>'datetime',
        ];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function interests(): HasMany { return $this->hasMany(SalesLeadInterest::class); }
    public function activities(): HasMany { return $this->hasMany(SalesLeadActivity::class); }
    public function notes(): HasMany { return $this->hasMany(SalesLeadNote::class); }
    public function tasks(): HasMany { return $this->hasMany(SalesLeadTask::class); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', ['won','lost','disqualified']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') return $query;
        return $query->where(function (Builder $q) use ($term) {
            $like = "%{$term}%";
            $q->where('company_name','like',$like)->orWhere('contact_name','like',$like)->orWhere('phone','like',$like)
                ->orWhere('email','like',$like)->orWhere('city','like',$like)->orWhere('sector','like',$like)->orWhere('code','like',$like);
        });
    }

    public function stageLabel(): string { return self::STAGES[$this->stage] ?? $this->stage; }
    public function ratingLabel(): string { return self::RATINGS[$this->rating] ?? $this->rating; }
    public function qualificationLabel(): string { return self::QUALIFICATIONS[$this->qualification] ?? $this->qualification; }
    public function sourceLabel(): string { return self::SOURCES[$this->source] ?? ($this->source ?: '—'); }
}
