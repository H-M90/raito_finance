<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerAttentionFlag;
use App\Models\CustomerAttentionFlagType;
use App\Models\CustomerCommercialSignal;
use App\Models\CustomerPlaybook;
use App\Models\CustomerPlaybookRun;
use App\Models\CustomerSuccessEvent;
use App\Models\CustomerSuccessProfile;
use App\Models\CustomerSuccessStatus;
use App\Models\CustomerSuccessStatusHistory;
use App\Models\CustomerSuccessTask;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomerSuccessService
{
    public const AUTO_FOLLOW_UP_PREFIX = 'متابعة تلقائية — ';
    public const HEALTH = ['VERY_SATISFIED','SATISFIED','NEUTRAL','DISSATISFIED','VERY_DISSATISFIED'];
    public const SIGNALS = ['REFERRAL_READY','TESTIMONIAL_READY','AMBASSADOR','UPSELL','CROSS_SELL','EXPANSION_SALE','RENEWAL'];
    public const RESULT_OPTIONS = [
        'STABLE_OR_AT_RISK'=>['STABLE'=>'مستقر','AT_RISK'=>'معرض للفقد'],
        'STABILIZING_OR_AT_RISK'=>['STABILIZING'=>'فترة الاستقرار','AT_RISK'=>'معرض للفقد'],
        'STABLE_OR_CHURN'=>['STABLE'=>'مستقر','CHURNED'=>'مفقود / منتهي'],
        'STABLE_OR_CHURNED'=>['STABLE'=>'تم الاحتفاظ بالعميل','CHURNED'=>'إلغاء نهائي'],
        'CLOSE_RISK'=>['STABLE'=>'استقرت العلاقة','AT_RISK'=>'ما زال معرضًا للفقد'],
        'RENEWAL_RESULT'=>['RENEWED'=>'تم التجديد','AT_RISK'=>'لم يتم التجديد / معرض للفقد'],
    ];
    private const ATTENTION_RANK = ['normal'=>0,'medium'=>1,'high'=>2,'critical'=>3,'closed'=>-1];

    public function ensureProfile(Customer $customer): CustomerSuccessProfile
    {
        $profile = CustomerSuccessProfile::where('customer_id',$customer->id)->first();
        if ($profile) {
            $this->ensureInitialStatusHistory($customer, $profile);
            return $profile;
        }

        $statusCode = $this->inferInitialStatus($customer);
        $status = $this->ensureStatus($statusCode);
        $profile = CustomerSuccessProfile::create([
            'customer_id'=>$customer->id,
            'lifecycle_status_id'=>$status->id,
            'health'=>'NEUTRAL',
            'attention_level'=>$status->default_attention,
            'owner_id'=>null,
            'next_review_at'=>$statusCode==='STABLE'?now()->addDays(60):now()->addDays(14),
        ]);
        $this->ensureInitialStatusHistory($customer, $profile);
        return $profile;
    }

    public function transition(Customer $customer, string $statusCode, ?string $reason = null, ?int $actorId = null): CustomerSuccessProfile
    {
        return DB::transaction(function () use ($customer,$statusCode,$reason,$actorId) {
            $profile = $this->ensureProfile($customer);
            $to = $this->ensureStatus($statusCode);
            if ((int)$profile->lifecycle_status_id === (int)$to->id) {
                CustomerSuccessStatusHistory::create([
                    'customer_id'=>$customer->id,
                    'from_status_id'=>$profile->lifecycle_status_id,
                    'to_status_id'=>$to->id,
                    'reason'=>$reason ?: 'إعادة تقييم العميل دون تغيير الحالة',
                    'changed_by'=>$actorId ?? auth()->id(),
                    'changed_at'=>now(),
                ]);
                $this->recordEvent($customer,'lifecycle','STATUS_REASSESSED',null,['status'=>$statusCode,'reason'=>$reason],$actorId,false);
                $this->ensureAutomaticFollowUp($customer);
                return $profile->fresh('lifecycleStatus','owner');
            }
            $fromId = $profile->lifecycle_status_id;
            $changes=['lifecycle_status_id'=>$to->id];
            if ($statusCode==='ACTIVATING' && ! $profile->activated_at) $changes['activated_at']=now();
            if ($statusCode==='STABILIZING' && ! $profile->go_live_at) $changes['go_live_at']=now();
            if ($statusCode==='STABLE') { $changes['stabilized_at']=now(); $changes['next_review_at']=now()->addDays(60); }
            if ($statusCode==='CHURNED') $changes['churned_at']=now();
            $profile->update($changes);
            CustomerSuccessStatusHistory::create(['customer_id'=>$customer->id,'from_status_id'=>$fromId,'to_status_id'=>$to->id,'reason'=>$reason,'changed_by'=>$actorId ?? auth()->id(),'changed_at'=>now()]);
            $this->recordEvent($customer,'lifecycle','STATUS_CHANGED',null,['from_status_id'=>$fromId,'to_status'=>$statusCode,'reason'=>$reason],$actorId,false);
            $this->startTriggeredPlaybook($customer,'lifecycle',$statusCode,null,$actorId);
            $this->recalculateAttention($customer);
            $this->ensureAutomaticFollowUp($customer);
            return $profile->fresh('lifecycleStatus','owner');
        });
    }

    public function updateHealth(Customer $customer, string $health, ?string $note = null, ?int $actorId = null): CustomerSuccessProfile
    {
        if (! in_array($health,self::HEALTH,true)) throw new \DomainException('قيمة رضا العميل غير صحيحة.');
        return DB::transaction(function () use ($customer,$health,$note,$actorId) {
            $profile=$this->ensureProfile($customer);
            $old=$profile->health;
            $profile->update(['health'=>$health,'last_health_change_at'=>now(),'notes'=>$note ?: $profile->notes]);
            $this->recordEvent($customer,'health','HEALTH_CHANGED',null,['from'=>$old,'to'=>$health,'note'=>$note],$actorId,false);
            $this->startTriggeredPlaybook($customer,'health',$health==='VERY_DISSATISFIED'?'DISSATISFIED':$health,null,$actorId);
            if (in_array($health,['DISSATISFIED','VERY_DISSATISFIED'],true)) $this->raiseLifecycleRiskIfNeeded($customer,$actorId,'انخفاض رضا العميل');
            return $profile->fresh('lifecycleStatus','owner');
        });
    }

    public function openFlag(Customer $customer, string $flagCode, ?string $notes = null, string $source = 'manual', ?array $metadata = null, ?int $actorId = null): CustomerAttentionFlag
    {
        return DB::transaction(function () use ($customer,$flagCode,$notes,$source,$metadata,$actorId) {
            $type=CustomerAttentionFlagType::where('code',$flagCode)->where('is_active',true)->firstOrFail();
            $existing=CustomerAttentionFlag::where('customer_id',$customer->id)->where('flag_type_id',$type->id)->where('status','open')->lockForUpdate()->first();
            if ($existing) {
                if ($notes && $notes !== $existing->notes) $existing->update(['notes'=>$notes,'metadata'=>$metadata ?: $existing->metadata]);
                return $existing->fresh('type','playbookRun');
            }
            $flag=CustomerAttentionFlag::create([
                'customer_id'=>$customer->id,'flag_type_id'=>$type->id,'severity'=>$type->default_severity,'source'=>$source,
                'status'=>'open','opened_at'=>now(),'due_at'=>$type->default_days?now()->addDays($type->default_days):null,
                'opened_by'=>$actorId ?? auth()->id(),'notes'=>$notes,'metadata'=>$metadata,
            ]);
            $run=$this->startTriggeredPlaybook($customer,'flag',$flagCode,$flag->id,$actorId);
            if ($run) $flag->update(['playbook_run_id'=>$run->id]);
            $this->recordEvent($customer,'flag','FLAG_OPENED',$flag,['flag_code'=>$flagCode,'source'=>$source],$actorId,false);
            if (in_array($type->default_severity,['critical'],true) || $flagCode==='CANCELLATION_REQUESTED') {
                $this->raiseLifecycleRiskIfNeeded($customer,$actorId,$type->name);
            } elseif ($flagCode==='LOW_USAGE') {
                $profile=$this->ensureProfile($customer)->loadMissing('lifecycleStatus');
                if (! in_array($profile->lifecycleStatus?->code,['LOW_ADOPTION','AT_RISK','CHURNED'],true)) $this->transition($customer,'LOW_ADOPTION','انخفاض الاستخدام الفعلي',$actorId);
            }
            $this->recalculateAttention($customer);
            $this->ensureAutomaticFollowUp($customer);
            return $flag->fresh('type','playbookRun');
        });
    }

    public function resolveFlag(CustomerAttentionFlag $flag, ?string $notes = null, ?int $actorId = null): CustomerAttentionFlag
    {
        return DB::transaction(function () use ($flag,$notes,$actorId) {
            $flag->loadMissing(['playbookRun','customer','type']);
            if ($flag->status !== 'open') return $flag;
            $flag->update(['status'=>'resolved','resolved_at'=>now(),'resolved_by'=>$actorId ?? auth()->id(),'notes'=>$notes ?: $flag->notes]);
            if ($flag->playbookRun && in_array($flag->playbookRun->status,['active','awaiting_resolution'],true)) $this->completeRun($flag->playbookRun,'FLAG_RESOLVED',$notes,$actorId);
            $this->recordEvent($flag->customer,'flag','FLAG_RESOLVED',$flag,['flag_code'=>$flag->type?->code],$actorId,false);
            $this->recalculateAttention($flag->customer);
            $this->ensureAutomaticFollowUp($flag->customer);
            return $flag->fresh('type');
        });
    }

    public function addSignal(Customer $customer, string $code, array $data = [], ?int $actorId = null): CustomerCommercialSignal
    {
        if (! in_array($code,self::SIGNALS,true)) throw new \DomainException('نوع الفرصة التجارية غير صحيح.');
        return DB::transaction(function () use ($customer,$code,$data,$actorId) {
            $existing=CustomerCommercialSignal::where('customer_id',$customer->id)->where('code',$code)->where('status','active')->lockForUpdate()->first();
            if ($existing) return $existing;
            $signal=CustomerCommercialSignal::create([
                'customer_id'=>$customer->id,'code'=>$code,'status'=>'active','source'=>$data['source']??'manual','owner_id'=>$data['owner_id']??auth()->id(),
                'estimated_value'=>$data['estimated_value']??null,'currency'=>$data['currency']??'SAR','due_at'=>$data['due_at']??null,'notes'=>$data['notes']??null,'metadata'=>$data['metadata']??null,
            ]);
            $run=$this->startTriggeredPlaybook($customer,'signal',$code,$signal->id,$actorId);
            if ($run) $signal->update(['playbook_run_id'=>$run->id]);
            $this->recordEvent($customer,'signal','SIGNAL_OPENED',$signal,['signal_code'=>$code],$actorId,false);
            return $signal->fresh('owner','playbookRun');
        });
    }

    public function closeSignal(CustomerCommercialSignal $signal, string $status, ?string $notes = null, ?int $actorId = null): CustomerCommercialSignal
    {
        if (! in_array($status,['won','lost','deferred','closed'],true)) throw new \DomainException('نتيجة الفرصة غير صحيحة.');
        return DB::transaction(function () use ($signal,$status,$notes,$actorId) {
            $signal->loadMissing(['playbookRun','customer']);
            $signal->update(['status'=>$status,'notes'=>$notes ?: $signal->notes]);
            if ($signal->playbookRun && in_array($signal->playbookRun->status,['active','awaiting_resolution'],true)) $this->completeRun($signal->playbookRun,strtoupper($status),$notes,$actorId);
            $this->recordEvent($signal->customer,'signal','SIGNAL_CLOSED',$signal,['signal_code'=>$signal->code,'result'=>$status],$actorId,false);
            return $signal->fresh();
        });
    }

    public function recordEvent(Customer $customer, string $eventType, string $eventCode, ?Model $source = null, array $payload = [], ?int $actorId = null, bool $triggerPlaybook = true): CustomerSuccessEvent
    {
        $event=CustomerSuccessEvent::create([
            'customer_id'=>$customer->id,'event_type'=>$eventType,'event_code'=>$eventCode,
            'source_type'=>$source?->getMorphClass(),'source_id'=>$source?->getKey(),'occurred_at'=>now(),'payload'=>$payload ?: null,'created_by'=>$actorId ?? auth()->id(),
        ]);
        if ($triggerPlaybook) $this->startTriggeredPlaybook($customer,'event',$eventCode,$event->id,$actorId);
        $event->update(['processed_at'=>now()]);
        return $event;
    }

    public function startTriggeredPlaybook(Customer $customer, string $triggerType, string $triggerCode, ?int $triggerId = null, ?int $actorId = null): ?CustomerPlaybookRun
    {
        $playbook=CustomerPlaybook::with('steps')->where('trigger_type',$triggerType)->where('trigger_code',$triggerCode)->where('is_active',true)->first();
        if (! $playbook) return null;
        return $this->startPlaybook($customer,$playbook,$triggerType,$triggerCode,$triggerId,$actorId);
    }

    public function startPlaybook(Customer $customer, CustomerPlaybook $playbook, string $triggerType = 'manual', ?string $triggerCode = null, ?int $triggerId = null, ?int $actorId = null): CustomerPlaybookRun
    {
        return DB::transaction(function () use ($customer,$playbook,$triggerType,$triggerCode,$triggerId,$actorId) {
            $playbook->loadMissing('steps');
            $existing=CustomerPlaybookRun::where('customer_id',$customer->id)->where('playbook_id',$playbook->id)->whereIn('status',['active','awaiting_resolution'])
                ->when($triggerId,fn($q)=>$q->where('trigger_id',$triggerId))->lockForUpdate()->first();
            if ($existing) return $existing;
            $profile=$this->ensureProfile($customer);
            $started=now();
            $run=CustomerPlaybookRun::create([
                'customer_id'=>$customer->id,'playbook_id'=>$playbook->id,'trigger_type'=>$triggerType,'trigger_code'=>$triggerCode ?: $playbook->trigger_code,
                'trigger_id'=>$triggerId,'status'=>'active','owner_id'=>$customer->sales_owner_id ?: auth()->id(),'started_at'=>$started,
                'due_at'=>$playbook->sla_hours?$started->copy()->addHours($playbook->sla_hours):null,
            ]);
            foreach ($playbook->steps as $step) {
                CustomerSuccessTask::create([
                    'customer_id'=>$customer->id,'playbook_run_id'=>$run->id,'playbook_step_id'=>$step->id,'title'=>$step->title,'description'=>$step->description,
                    'team'=>'account_management','type'=>'customer_success','status'=>'open','priority'=>$step->default_priority,'assigned_to'=>$run->owner_id,
                    'due_at'=>$step->due_offset_hours?$started->copy()->addHours($step->due_offset_hours):$run->due_at,'created_by'=>$actorId ?? auth()->id(),
                ]);
            }
            $this->recordEvent($customer,'playbook','PLAYBOOK_STARTED',$run,['playbook_code'=>$playbook->code],$actorId,false);
            return $run->fresh('playbook','tasks');
        });
    }

    public function completeTask(CustomerSuccessTask $task, ?string $notes = null, ?int $actorId = null): CustomerSuccessTask
    {
        return DB::transaction(function () use ($task,$notes,$actorId) {
            $task->loadMissing('playbookRun.playbook', 'customer');
            if ($task->status==='completed') return $task;
            $task->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>$actorId ?? auth()->id(),'completion_notes'=>$notes]);
            $run=$task->playbookRun;
            if ($run && ! $run->tasks()->where('status','!=','completed')->exists()) {
                $resultTemplate=$run->playbook?->result_code;
                if (isset(self::RESULT_OPTIONS[$resultTemplate]) || $resultTemplate==='CLOSE_SIGNAL') {
                    $run->update(['status'=>'awaiting_resolution']);
                    $this->recordEvent($task->customer,'playbook','PLAYBOOK_AWAITING_RESOLUTION',$run,['playbook_code'=>$run->playbook?->code,'result_template'=>$resultTemplate],$actorId,false);
                } else {
                    $this->completeRun($run,null,null,$actorId);
                }
            }
            if (str_starts_with($task->title, self::AUTO_FOLLOW_UP_PREFIX)) {
                $this->ensureAutomaticFollowUp($task->customer);
            }
            return $task->fresh();
        });
    }

    /**
     * Keeps one recurring follow-up task that reflects the strongest current
     * customer need. Lifecycle describes where the customer is; flags explain
     * why intervention is needed. The task is only the operational reminder.
     */
    public function ensureAutomaticFollowUp(Customer $customer): ?CustomerSuccessTask
    {
        $policy = $this->automaticFollowUpPolicy($customer);
        $autoTasks = CustomerSuccessTask::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->where('title', 'like', self::AUTO_FOLLOW_UP_PREFIX.'%');

        if (! $policy) {
            $autoTasks->update(['status' => 'cancelled']);
            return null;
        }

        $title = self::AUTO_FOLLOW_UP_PREFIX.$policy['label'];
        $autoTasks->where('title', '!=', $title)->update(['status' => 'cancelled']);

        $existing = CustomerSuccessTask::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->where('title', $title)
            ->first();

        if ($existing) {
            $existing->update([
                'priority' => $policy['priority'],
                'description' => $this->automaticFollowUpDescription($policy),
            ]);
            return $existing;
        }

        $lastCompleted = CustomerSuccessTask::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->where('title', $title)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        $dueAt = $lastCompleted?->completed_at
            ? $lastCompleted->completed_at->copy()->addDays($policy['interval_days'])
            : now();

        $profile = $this->ensureProfile($customer);
        $task = CustomerSuccessTask::create([
            'customer_id' => $customer->id,
            'title' => $title,
            'description' => $this->automaticFollowUpDescription($policy),
            'team' => 'account_management',
            'type' => 'customer_success',
            'status' => 'open',
            'priority' => $policy['priority'],
            'assigned_to' => $customer->sales_owner_id ?: auth()->id(),
            'due_at' => $dueAt,
            'created_by' => auth()->id(),
        ]);

        $this->recordEvent($customer, 'event', 'AUTO_FOLLOW_UP_SCHEDULED', $task, [
            'reason_code' => $policy['code'],
            'reason' => $policy['reason'],
            'interval_days' => $policy['interval_days'],
            'due_at' => $dueAt->toDateTimeString(),
        ], null, false);

        return $task;
    }

    public function automaticFollowUpPolicy(Customer $customer): ?array
    {
        $profile = $this->ensureProfile($customer)->loadMissing('lifecycleStatus');
        if ($customer->status !== 'active' || $profile->lifecycleStatus?->code === 'CHURNED') return null;
        $candidates = [];

        $statusPolicies = [
            'NEW' => ['urgency'=>90,'label'=>'عميل جديد','interval_days'=>1,'priority'=>'high','reason'=>'العميل في بداية العلاقة ويحتاج متابعة يومية حتى بدء التفعيل'],
            'ACTIVATING' => ['urgency'=>78,'label'=>'عميل تحت التفعيل','interval_days'=>2,'priority'=>'high','reason'=>'العميل تحت التفعيل ويحتاج متابعة منتظمة حتى التشغيل'],
            'STABILIZING' => ['urgency'=>55,'label'=>'فترة الاستقرار','interval_days'=>7,'priority'=>'high','reason'=>'العميل في أول فترة بعد التشغيل ويحتاج مراجعة أسبوعية'],
            'LOW_ADOPTION' => ['urgency'=>72,'label'=>'استخدام منخفض','interval_days'=>3,'priority'=>'high','reason'=>'استخدام العميل أقل من المتوقع ويحتاج متابعة كل 3 أيام'],
            'DORMANT' => ['urgency'=>74,'label'=>'عميل خامل','interval_days'=>3,'priority'=>'high','reason'=>'الاستخدام شبه متوقف ويحتاج متابعة كل 3 أيام'],
            'AT_RISK' => ['urgency'=>95,'label'=>'عميل معرض للفقد','interval_days'=>1,'priority'=>'critical','reason'=>'العلاقة معرضة للفقد وتحتاج متابعة يومية حتى الحسم'],
        ];
        $statusCode = $profile->lifecycleStatus?->code;
        if ($statusCode && isset($statusPolicies[$statusCode])) {
            $candidates[] = ['code'=>$statusCode] + $statusPolicies[$statusCode];
        }

        $flagPolicies = [
            'CANCELLATION_REQUESTED' => ['urgency'=>110,'label'=>'طلب إلغاء','interval_days'=>1,'priority'=>'critical','reason'=>'العميل طلب الإلغاء ويحتاج متابعة يومية حتى الاحتفاظ به أو إنهاء العلاقة'],
            'CRITICAL_ISSUE' => ['urgency'=>108,'label'=>'مشكلة حرجة','interval_days'=>1,'priority'=>'critical','reason'=>'هناك مشكلة حرجة مؤثرة على العميل وتحتاج متابعة يومية حتى الحل'],
            'REPEATED_COMPLAINTS' => ['urgency'=>84,'label'=>'شكاوى متكررة','interval_days'=>2,'priority'=>'high','reason'=>'توجد شكاوى متكررة وتحتاج متابعة كل يومين حتى إغلاق السبب الجذري'],
            'PROCESS_CHANGED' => ['urgency'=>76,'label'=>'تغيير إجراءات العمل','interval_days'=>3,'priority'=>'high','reason'=>'إجراءات عمل العميل تغيرت وتحتاج متابعة متقاربة حتى اعتماد الوضع الجديد'],
            'MANAGEMENT_CHANGED' => ['urgency'=>75,'label'=>'تغيير الإدارة','interval_days'=>3,'priority'=>'high','reason'=>'تغير صاحب القرار ويجب تثبيت العلاقة وخطة العمل الجديدة'],
            'FINANCE_MANAGER_CHANGED' => ['urgency'=>70,'label'=>'تغيير المدير المالي','interval_days'=>3,'priority'=>'high','reason'=>'تغير المدير المالي ويحتاج تعريف ومتابعة سريعة'],
            'LOW_USAGE' => ['urgency'=>70,'label'=>'انخفاض الاستخدام','interval_days'=>3,'priority'=>'high','reason'=>'انخفض استخدام النظام ويجب متابعة سبب الانخفاض وخطة المعالجة'],
            'PAYMENT_OVERDUE' => ['urgency'=>68,'label'=>'تأخر مالي','interval_days'=>3,'priority'=>'high','reason'=>'يوجد استحقاق متأخر ويحتاج متابعة سداد كل 3 أيام'],
            'ACCOUNTANT_CHANGED' => ['urgency'=>58,'label'=>'تغيير المحاسب','interval_days'=>7,'priority'=>'high','reason'=>'تغير المحاسب ويحتاج متابعة حتى استقرار استخدامه للنظام'],
            'HIGH_SUPPORT_LOAD' => ['urgency'=>57,'label'=>'احتياج دعم مرتفع','interval_days'=>7,'priority'=>'medium','reason'=>'معدل الدعم مرتفع ويحتاج مراجعة أسبوعية حتى انخفاض الطلبات المتكررة'],
            'LIMITED_USAGE' => ['urgency'=>52,'label'=>'استخدام محدود للميزات','interval_days'=>7,'priority'=>'medium','reason'=>'العميل يستخدم جزءًا محدودًا من النظام ويحتاج مراجعة أسبوعية للتبني'],
            'RENEWAL_DUE' => ['urgency'=>50,'label'=>'تجديد قريب','interval_days'=>7,'priority'=>'high','reason'=>'موعد التجديد قريب ويحتاج متابعة أسبوعية حتى الحسم'],
            'COMPANY_EXPANSION' => ['urgency'=>40,'label'=>'توسع الشركة','interval_days'=>14,'priority'=>'medium','reason'=>'هناك توسع لدى العميل ويحتاج مراجعة دورية لاحتياجاته الجديدة'],
        ];

        $flags = $customer->attentionFlags()->where('status','open')->with('type:id,code,name')->get();
        foreach ($flags as $flag) {
            $code = $flag->type?->code;
            if ($code && isset($flagPolicies[$code])) {
                $candidates[] = ['code'=>$code] + $flagPolicies[$code];
            }
        }

        if ($candidates === []) return null;
        usort($candidates, fn ($a, $b) => $b['urgency'] <=> $a['urgency']);
        return $candidates[0];
    }

    private function automaticFollowUpDescription(array $policy): string
    {
        $cadence = match ((int)$policy['interval_days']) {
            1 => 'يوميًا',
            2 => 'كل يومين',
            3 => 'كل 3 أيام',
            7 => 'أسبوعيًا',
            14 => 'كل أسبوعين',
            default => 'كل '.$policy['interval_days'].' أيام',
        };
        return $policy['reason'].' — التكرار: '.$cadence.'. تتوقف تلقائيًا عند تغير الحالة أو إغلاق السبب.';
    }

    private function ensureInitialStatusHistory(Customer $customer, CustomerSuccessProfile $profile): void
    {
        if (! $profile->lifecycle_status_id) return;
        if (CustomerSuccessStatusHistory::where('customer_id',$customer->id)->exists()) return;

        CustomerSuccessStatusHistory::create([
            'customer_id'=>$customer->id,
            'from_status_id'=>null,
            'to_status_id'=>$profile->lifecycle_status_id,
            'reason'=>'التقييم الأولي للعميل',
            'changed_by'=>null,
            'changed_at'=>$profile->created_at ?: now(),
        ]);
    }

    public function completeRun(CustomerPlaybookRun $run, ?string $result = null, ?string $notes = null, ?int $actorId = null): CustomerPlaybookRun
    {
        $run->loadMissing(['playbook','customer']);
        if (! in_array($run->status,['active','awaiting_resolution'],true)) return $run;
        $template=$run->playbook?->result_code;
        $flagWasResolvedExternally=$run->trigger_type==='flag' && $result==='FLAG_RESOLVED';
        if (! $flagWasResolvedExternally) {
            if (isset(self::RESULT_OPTIONS[$template])) {
                if (! $result || ! array_key_exists($result,self::RESULT_OPTIONS[$template])) throw new \DomainException('اختر نتيجة نهائية صحيحة للـPlaybook.');
            } elseif ($template==='CLOSE_SIGNAL') {
                if ($run->status==='awaiting_resolution' && $result===null) throw new \DomainException('أغلق الإشارة التجارية وحدد Won/Lost/Deferred من قسم Commercial Signals.');
                if ($result!==null && ! in_array($result,['WON','LOST','DEFERRED','CLOSED'],true)) throw new \DomainException('نتيجة الإشارة التجارية غير صحيحة.');
            } elseif ($result!==null && $result!==$template) {
                throw new \DomainException('لا يمكن تجاوز النتيجة المحددة لهذا الـPlaybook.');
            }
        }
        $resultCode=$result ?: $template;
        $run->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>$actorId ?? auth()->id(),'result_code'=>$resultCode,'notes'=>$notes ?: $run->notes]);
        $this->recordEvent($run->customer,'playbook','PLAYBOOK_COMPLETED',$run,['playbook_code'=>$run->playbook?->code,'result'=>$resultCode],$actorId,false);
        if ($resultCode && CustomerSuccessStatus::where('code',$resultCode)->exists()) {
            $this->transition($run->customer,$resultCode,'إكمال '.$run->playbook?->name,$actorId);
        } elseif ($resultCode && in_array($resultCode,self::SIGNALS,true) && $run->trigger_type!=='signal') {
            $this->addSignal($run->customer,$resultCode,['source'=>'playbook','notes'=>'نتيجة '.$run->playbook?->name],$actorId);
        } elseif ($resultCode==='SIGNALS') {
            $this->addSignal($run->customer,'TESTIMONIAL_READY',['source'=>'playbook','notes'=>'العميل راضٍ جدًا ومرشح لشهادة/قصة نجاح.'],$actorId);
            $this->addSignal($run->customer,'REFERRAL_READY',['source'=>'playbook','notes'=>'العميل راضٍ جدًا ومرشح لطلب Introduction مناسب.'],$actorId);
        } elseif ($resultCode==='CLOSE_FLAG' && $run->trigger_type==='flag' && $run->trigger_id) {
            $flag=CustomerAttentionFlag::find($run->trigger_id);
            if ($flag && $flag->status==='open') $this->resolveFlag($flag,'تم إغلاق الـFlag بعد اكتمال الـPlaybook.',$actorId);
        } elseif ($resultCode==='RENEWED') {
            $renewalSignal=CustomerCommercialSignal::where('customer_id',$run->customer_id)->where('code','RENEWAL')->where('status','active')->first();
            if ($renewalSignal) $this->closeSignal($renewalSignal,'won','تم التجديد بنجاح.',$actorId);
        } elseif ($resultCode==='KEEP_ACTIVE' && $run->trigger_type==='signal' && $run->trigger_id) {
            CustomerCommercialSignal::whereKey($run->trigger_id)->where('status','active')->update(['due_at'=>now()->addDays(90)]);
        }
        if ($run->playbook?->code==='PB_STABLE_REVIEW') {
            $this->ensureProfile($run->customer)->update(['next_review_at'=>now()->addDays(60)]);
        }
        if ($run->trigger_type==='flag' && $run->trigger_id && $resultCode!=='FLAG_RESOLVED') {
            $flag=CustomerAttentionFlag::find($run->trigger_id);
            if ($flag && $flag->status==='open') $this->resolveFlag($flag,'تم حسم الـFlag بعد اكتمال الـPlaybook بنتيجة '.$resultCode.'.',$actorId);
        }
        return $run->fresh();
    }

    public function recalculateAttention(Customer $customer): void
    {
        $profile=$this->ensureProfile($customer)->loadMissing('lifecycleStatus');
        if ($profile->lifecycleStatus?->code==='CHURNED') { $profile->update(['attention_level'=>'closed']); return; }
        $levels=$customer->attentionFlags()->where('status','open')->pluck('severity')->push($profile->lifecycleStatus?->default_attention ?: 'normal');
        $level=$levels->sortByDesc(fn($v)=>self::ATTENTION_RANK[$v]??0)->first() ?: 'normal';
        $profile->update(['attention_level'=>$level]);
    }

    public function syncAutomations(): array
    {
        $stats=['overdue_opened'=>0,'overdue_resolved'=>0,'renewal_opened'=>0,'renewal_resolved'=>0,'reviews_started'=>0,'ambassador_reviews_started'=>0,'automatic_followups_synced'=>0];
        Customer::query()->where('status','active')->chunkById(100,function($customers)use(&$stats){
            foreach($customers as $customer){
                $this->ensureProfile($customer);
                $hasOverdue=Receivable::where('customer_id',$customer->id)->where('remaining_amount','>',0)->whereDate('due_date','<',today())->whereNotIn('status',['cancelled','waived','needs_review'])->exists();
                $openOverdue=$customer->attentionFlags()->whereHas('type',fn($q)=>$q->where('code','PAYMENT_OVERDUE'))->where('status','open')->first();
                if($hasOverdue&&!$openOverdue){$this->openFlag($customer,'PAYMENT_OVERDUE','تم فتح العلم تلقائيًا لوجود استحقاق متأخر.','receivables',null,null);$stats['overdue_opened']++;}
                if(!$hasOverdue&&$openOverdue){$this->resolveFlag($openOverdue,'تم إغلاق العلم تلقائيًا بعد عدم وجود استحقاقات متأخرة.',null);$stats['overdue_resolved']++;}

                $renewalDate=Contract::where('customer_id',$customer->id)->where('status','active')->where('billing_cycle','annual')->whereNotNull('next_billing_date')->whereDate('next_billing_date','<=',today()->addDays(60))->min('next_billing_date');
                $renewalFlags=$customer->attentionFlags()->whereHas('type',fn($q)=>$q->where('code','RENEWAL_DUE'))->latest('opened_at')->get();
                $openRenewal=$renewalFlags->firstWhere('status','open');
                $latestRenewal=$renewalFlags->first();
                $sameResolvedCycle=$renewalDate && $latestRenewal?->status==='resolved' && data_get($latestRenewal->metadata,'renewal_date')===\Illuminate\Support\Carbon::parse($renewalDate)->toDateString();
                if($renewalDate&&!$openRenewal&&!$sameResolvedCycle){$this->openFlag($customer,'RENEWAL_DUE','موعد التجديد السنوي خلال 60 يومًا أو متأخر.','contracts',['renewal_date'=>\Illuminate\Support\Carbon::parse($renewalDate)->toDateString()],null);$stats['renewal_opened']++;}
                if($renewalDate){
                    $signal=$this->addSignal($customer,'RENEWAL',['source'=>'contracts','due_at'=>$renewalDate,'notes'=>'فرصة تجديد مرتبطة بالعقد السنوي.'],null);
                    if($signal->due_at?->toDateString()!==\Illuminate\Support\Carbon::parse($renewalDate)->toDateString()) $signal->update(['due_at'=>$renewalDate]);
                }
                if(!$renewalDate&&$openRenewal){$this->resolveFlag($openRenewal,'تم إغلاق تنبيه التجديد تلقائيًا بعد تحديث/إنهاء دورة العقد.',null);$stats['renewal_resolved']++;}

                $profile=$this->ensureProfile($customer)->loadMissing('lifecycleStatus');
                if($profile?->lifecycleStatus?->code==='STABLE' && $profile->next_review_at && $profile->next_review_at->isPast()){
                    $reviewInProgress=CustomerPlaybookRun::where('customer_id',$customer->id)->whereHas('playbook',fn($q)=>$q->where('code','PB_STABLE_REVIEW'))->whereIn('status',['active','awaiting_resolution'])->exists();
                    if(!$reviewInProgress && $this->startTriggeredPlaybook($customer,'review','STABLE',null,null)) $stats['reviews_started']++;
                }

                $ambassadors=CustomerCommercialSignal::where('customer_id',$customer->id)->where('code','AMBASSADOR')->where('status','active')->whereNotNull('due_at')->where('due_at','<=',now())->get();
                foreach($ambassadors as $ambassador){
                    $run=$this->startTriggeredPlaybook($customer,'signal','AMBASSADOR',$ambassador->id,null);
                    if($run && (int)$ambassador->playbook_run_id!==(int)$run->id){$ambassador->update(['playbook_run_id'=>$run->id]);$stats['ambassador_reviews_started']++;}
                }
                if ($this->ensureAutomaticFollowUp($customer)) $stats['automatic_followups_synced']++;
            }
        });
        return $stats;
    }


    private function ensureStatus(string $code): CustomerSuccessStatus
    {
        $map=[
            'NEW'=>['عميل جديد','high',1],
            'ACTIVATING'=>['تحت التفعيل','high',2],
            'STABILIZING'=>['فترة الاستقرار','high',3],
            'STABLE'=>['مستقر','normal',4],
            'LOW_ADOPTION'=>['استخدام منخفض','high',5],
            'DORMANT'=>['خامل / متوقف','high',6],
            'AT_RISK'=>['معرض للفقد','critical',7],
            'CHURNED'=>['مفقود / منتهي','closed',8],
        ];
        if (! isset($map[$code])) throw new \DomainException('مرحلة نجاح العميل غير صحيحة.');
        [$name,$attention,$sort]=$map[$code];
        return CustomerSuccessStatus::firstOrCreate(['code'=>$code],['name'=>$name,'default_attention'=>$attention,'sort_order'=>$sort,'is_active'=>true]);
    }

    private function raiseLifecycleRiskIfNeeded(Customer $customer, ?int $actorId, string $reason): void
    {
        $profile=$this->ensureProfile($customer)->loadMissing('lifecycleStatus');
        if (! in_array($profile->lifecycleStatus?->code,['AT_RISK','CHURNED'],true)) $this->transition($customer,'AT_RISK',$reason,$actorId);
    }

    private function inferInitialStatus(Customer $customer): string
    {
        $latestStart=$customer->contracts()->where('status','active')->max('service_start_date');
        if (! $latestStart) return 'NEW';
        $date=\Illuminate\Support\Carbon::parse($latestStart);
        if ($date->isFuture()) return 'ACTIVATING';
        if ($date->diffInDays(today()) <= 60) return 'STABILIZING';
        return 'STABLE';
    }
}
