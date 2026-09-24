<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Models\SalesLeadTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesLeadService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function log(SalesLead $lead, string $type, string $description, ?int $userId = null): SalesLeadActivity
    {
        $event = $lead->activities()->create([
            'type'=>$type,
            'description'=>$description,
            'occurred_at'=>now(),
            'created_by'=>$userId ?? auth()->id(),
        ]);
        $lead->forceFill(['last_activity_at'=>now()])->saveQuietly();
        return $event;
    }

    public function syncFollowUp(SalesLead $lead): void
    {
        $open = $lead->tasks()->where('is_follow_up', true)->where('status', 'open')->orderBy('due_at')->first();
        if (! $lead->next_follow_up_at) {
            if ($open) $open->update(['status'=>'cancelled']);
            return;
        }

        $payload = [
            'title'=>$lead->next_follow_up_title ?: 'متابعة العميل المحتمل',
            'team'=>'sales',
            'type'=>$lead->next_follow_up_type ?: 'call',
            'priority'=>$lead->next_follow_up_priority ?: 'medium',
            'due_at'=>$lead->next_follow_up_at,
            'assigned_to'=>$lead->owner_id,
        ];
        if ($open) {
            $open->update($payload);
        } else {
            $lead->tasks()->create($payload + [
                'status'=>'open','is_follow_up'=>true,'created_by'=>auth()->id(),
            ]);
        }
    }

    public function completeTask(SalesLead $lead, SalesLeadTask $task, ?int $userId = null): void
    {
        if ((int)$task->sales_lead_id !== (int)$lead->id || $task->status !== 'open') return;
        $task->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>$userId ?? auth()->id()]);
        if ($task->is_follow_up && $lead->next_follow_up_at && $task->due_at?->equalTo($lead->next_follow_up_at)) {
            $lead->update([
                'next_follow_up_at'=>null,'next_follow_up_type'=>null,'next_follow_up_priority'=>null,'next_follow_up_title'=>null,
                'updated_by'=>$userId ?? auth()->id(),
            ]);
        }
        $this->log($lead, 'متابعة مكتملة', sprintf('تمت متابعة العميل (%s): %s', SalesLead::TASK_TYPES[$task->type] ?? 'متابعة', $task->title), $userId);
    }

    public function convertToCustomer(SalesLead $lead, ?int $userId = null): Customer
    {
        return DB::transaction(function () use ($lead, $userId) {
            /** @var SalesLead $locked */
            $locked = SalesLead::query()->lockForUpdate()->findOrFail($lead->id);
            if ($locked->customer_id) return $locked->customer()->firstOrFail();
            if ($locked->qualification !== 'qualified') {
                throw ValidationException::withMessages(['lead'=>'لا يمكن التحويل قبل اكتمال تأهيل العميل المحتمل.']);
            }

            $dupe = Customer::query()->where(function ($q) use ($locked) {
                $q->where('name',$locked->company_name);
                if ($locked->phone) $q->orWhere('phone',$locked->phone);
                if ($locked->email) $q->orWhere('email',$locked->email);
            })->first();
            if ($dupe) {
                throw ValidationException::withMessages(['lead'=>"يوجد عميل مسجل بالفعل ببيانات مطابقة ({$dupe->name} - {$dupe->code}). راجع العميل قبل التحويل لتجنب التكرار."]);
            }

            $customer = Customer::create([
                'code'=>$this->numbers->uniqueCode('customers','CUS'),
                'name'=>$locked->company_name,
                'city'=>$locked->city,
                'contact_name'=>$locked->contact_name,
                'phone'=>$locked->phone,
                'email'=>$locked->email,
                'sales_owner_id'=>$locked->owner_id,
                'segment'=>'standard',
                'status'=>'active',
                'notes'=>'تم إنشاء العميل من رحلة المبيعات '.$locked->code,
                'source_type'=>'sales_lead',
                'source_id'=>$locked->id,
            ]);

            $locked->update([
                'customer_id'=>$customer->id,
                'converted_at'=>now(),
                'stage'=>'won',
                'responded'=>true,
                'next_follow_up_at'=>null,
                'next_follow_up_type'=>null,
                'next_follow_up_priority'=>null,
                'next_follow_up_title'=>null,
                'updated_by'=>$userId ?? auth()->id(),
            ]);
            $locked->tasks()->where('status','open')->update(['status'=>'cancelled']);
            $this->log($locked, 'تحويل إلى عميل', "تم اعتماد العميل المحتمل وتحويله إلى عميل برقم {$customer->code}.", $userId);
            return $customer;
        });
    }
}
