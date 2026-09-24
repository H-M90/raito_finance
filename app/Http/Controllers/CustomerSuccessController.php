<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAttentionFlag;
use App\Models\CustomerCommercialSignal;
use App\Models\CustomerContact;
use App\Models\CustomerPlaybook;
use App\Models\CustomerPlaybookRun;
use App\Models\CustomerSuccessTask;
use App\Services\CustomerSuccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerSuccessController extends Controller
{
    public function show(Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $service->ensureProfile($customer);
        return $this->toCustomer($customer);
    }

    public function updateProfile(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate(['next_review_at'=>['nullable','date'],'notes'=>['nullable','string','max:5000']]);
        $service->ensureProfile($customer)->update($data);
        return $this->toCustomer($customer,'overview')->with('success','تم تحديث بيانات متابعة العميل.');
    }

    public function transition(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate(['status_code'=>['required','exists:customer_success_statuses,code'],'reason'=>['nullable','string','max:500']]);
        $before=$service->ensureProfile($customer)->loadMissing('lifecycleStatus')->lifecycleStatus?->code;
        $service->transition($customer,$data['status_code'],$data['reason']??null);
        $message=$before===$data['status_code']
            ? 'تمت إعادة تقييم العميل وتسجيلها في التاريخ دون تغيير الحالة.'
            : 'تم تغيير حالة العميل وتحديث المتابعة التلقائية المناسبة.';
        return $this->toCustomer($customer,'overview')->with('success',$message);
    }

    public function health(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate(['health'=>['required',Rule::in(CustomerSuccessService::HEALTH)],'note'=>['nullable','string','max:1000']]);
        $service->updateHealth($customer,$data['health'],$data['note']??null);
        return $this->toCustomer($customer,'overview')->with('success','تم تحديث مستوى رضا العميل.');
    }

    public function storeFollowUp(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate([
            'type'=>['required',Rule::in(array_keys(\App\Support\CustomerSuccessOptions::followUpTypes()))],
            'notes'=>['nullable','string','max:3000'],
            'due_at'=>['required','date'],
            'assigned_to'=>['nullable','exists:users,id'],
        ]);

        $profile=$service->ensureProfile($customer);
        $assignee=$data['assigned_to'] ?? auth()->id();
        $label=\App\Support\CustomerSuccessOptions::followUpTypes()[$data['type']];
        $priority=in_array($data['type'],['SUPPORT','PAYMENT','RENEWAL'],true)?'high':'normal';

        $taskType=match($data['type']){
            'MEETING'=>'meeting','TRAINING'=>'training','SUPPORT'=>'support','PAYMENT'=>'collection','RENEWAL'=>'renewal','SALES'=>'follow_up',default=>'follow_up',
        };
        $task=CustomerSuccessTask::create([
            'customer_id'=>$customer->id,
            'title'=>$label,
            'description'=>$data['notes']??null,
            'team'=>'account_management',
            'type'=>$taskType,
            'status'=>'open',
            'priority'=>$priority,
            'assigned_to'=>$assignee,
            'due_at'=>$data['due_at'],
            'created_by'=>auth()->id(),
        ]);
        $profile->update(['next_review_at'=>$data['due_at']]);
        $service->recordEvent($customer,'event','MANUAL_FOLLOW_UP_CREATED',$task,[
            'follow_up_type'=>$data['type'],
            'due_at'=>$data['due_at'],
            'assigned_to'=>$assignee,
            'notes'=>$data['notes']??null,
        ],null,false);

        return $this->toCustomer($customer)->with('success','تم تسجيل المتابعة القادمة. ستظهر تلقائيًا ضمن المطلوب الآن.');
    }

    public function storeFlag(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate(['flag_code'=>['required','exists:customer_attention_flag_types,code'],'notes'=>['nullable','string','max:3000']]);
        $service->openFlag($customer,$data['flag_code'],$data['notes']??null,'manual');
        return $this->toCustomer($customer,'alerts')->with('success','تم فتح تنبيه المتابعة وبدء خطة المعالجة المرتبطة.');
    }

    public function resolveFlag(Request $request, Customer $customer, CustomerAttentionFlag $flag, CustomerSuccessService $service): RedirectResponse
    {
        $this->assertOwnedByCustomer($customer,$flag->customer_id);
        $data=$request->validate(['notes'=>['nullable','string','max:3000']]);
        $service->resolveFlag($flag,$data['notes']??null);
        return $this->toCustomer($customer,'alerts')->with('success','تم إغلاق تنبيه المتابعة.');
    }

    public function storeSignal(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate([
            'code'=>['required',Rule::in(CustomerSuccessService::SIGNALS)],'owner_id'=>['nullable','exists:users,id'],
            'estimated_value'=>['nullable','numeric','min:0'],'currency'=>['required','string','size:3'],'due_at'=>['nullable','date'],'notes'=>['nullable','string','max:3000'],
        ]);
        $service->addSignal($customer,$data['code'],$data);
        return $this->toCustomer($customer,'opportunities')->with('success','تم تسجيل فرصة العميل وبدء خطة المتابعة المرتبطة إن وجدت.');
    }

    public function closeSignal(Request $request, Customer $customer, CustomerCommercialSignal $signal, CustomerSuccessService $service): RedirectResponse
    {
        $this->assertOwnedByCustomer($customer,$signal->customer_id);
        $data=$request->validate(['status'=>['required',Rule::in(['won','lost','deferred','closed'])],'notes'=>['nullable','string','max:3000']]);
        $service->closeSignal($signal,$data['status'],$data['notes']??null);
        return $this->toCustomer($customer,'opportunities')->with('success','تم تحديث نتيجة فرصة العميل.');
    }

    public function storeContact(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $roles=['ACCOUNTANT','FINANCE_MANAGER','DECISION_MAKER','KEY_USER','OWNER','OTHER'];
        $data=$request->validate([
            'name'=>['required','string','max:255'],'role_code'=>['nullable',Rule::in($roles)],'phone'=>['nullable','string','max:40'],
            'email'=>['nullable','email','max:255'],'is_primary'=>['nullable','boolean'],'notes'=>['nullable','string','max:2000'],
        ]);
        DB::transaction(function()use($customer,$data,$service){
            $changed=false;
            if(in_array($data['role_code']??null,['ACCOUNTANT','FINANCE_MANAGER','DECISION_MAKER'],true)){
                $old=CustomerContact::where('customer_id',$customer->id)->where('role_code',$data['role_code'])->where('is_active',true)->get();
                $changed=$old->isNotEmpty() && ! $old->contains(fn($c)=>mb_strtolower(trim($c->name))===mb_strtolower(trim($data['name'])));
                if($changed) CustomerContact::whereIn('id',$old->pluck('id'))->update(['is_active'=>false,'ended_at'=>today()]);
            }
            if(!empty($data['is_primary'])) CustomerContact::where('customer_id',$customer->id)->update(['is_primary'=>false]);
            $contact=CustomerContact::updateOrCreate(
                ['customer_id'=>$customer->id,'name'=>$data['name'],'phone'=>$data['phone']??null],
                $data+['is_active'=>true,'ended_at'=>null,'started_at'=>today(),'is_primary'=>!empty($data['is_primary'])]
            );
            $service->recordEvent($customer,'contact','CONTACT_ADDED',$contact,['role_code'=>$contact->role_code,'name'=>$contact->name]);
            if($changed){
                $flag=match($contact->role_code){'ACCOUNTANT'=>'ACCOUNTANT_CHANGED','FINANCE_MANAGER'=>'FINANCE_MANAGER_CHANGED','DECISION_MAKER'=>'MANAGEMENT_CHANGED',default=>null};
                if($flag)$service->openFlag($customer,$flag,"تم تسجيل {$contact->name} كمسؤول جديد.",'contacts',['contact_id'=>$contact->id]);
            }
        });
        return $this->toCustomer($customer,'contacts')->with('success','تم تحديث جهة الاتصال وربط أي تغيير مهم بمتابعة العميل.');
    }

    public function runPlaybook(Request $request, Customer $customer, CustomerSuccessService $service): RedirectResponse
    {
        $data=$request->validate(['playbook_id'=>['required','exists:customer_playbooks,id']]);
        $playbook=CustomerPlaybook::findOrFail($data['playbook_id']);
        $service->startPlaybook($customer,$playbook,'manual',$playbook->code);
        return $this->toCustomer($customer,'work')->with('success','تم بدء خطة المتابعة وإنشاء مهامها.');
    }

    public function completeTask(Request $request, Customer $customer, CustomerSuccessTask $task, CustomerSuccessService $service): RedirectResponse
    {
        $this->assertOwnedByCustomer($customer,$task->customer_id);
        $data=$request->validate(['completion_notes'=>['nullable','string','max:3000']]);
        $service->completeTask($task,$data['completion_notes']??null);
        return $this->toCustomer($customer)->with('success','تم إكمال المتابعة.');
    }

    public function assignTask(Request $request, Customer $customer, CustomerSuccessTask $task): RedirectResponse
    {
        $this->assertOwnedByCustomer($customer,$task->customer_id);
        $data=$request->validate(['assigned_to'=>['nullable','exists:users,id'],'due_at'=>['nullable','date'],'priority'=>['required',Rule::in(['normal','medium','high','critical'])]]);
        $task->update($data);
        return $this->toCustomer($customer)->with('success','تم تحديث المتابعة القادمة.');
    }

    public function completeRun(Request $request, Customer $customer, CustomerPlaybookRun $run, CustomerSuccessService $service): RedirectResponse
    {
        $this->assertOwnedByCustomer($customer,$run->customer_id);
        $data=$request->validate(['result_code'=>['nullable','string','max:60'],'notes'=>['nullable','string','max:3000']]);
        $service->completeRun($run,$data['result_code']??null,$data['notes']??null);
        return $this->toCustomer($customer,'work')->with('success','تم حسم خطة المتابعة.');
    }

    private function assertOwnedByCustomer(Customer $customer, int $customerId): void
    {
        abort_unless((int)$customer->id === (int)$customerId,404);
    }

    private function toCustomer(Customer $customer, string $tab = 'overview'): RedirectResponse
    {
        $anchor = in_array($tab,['alerts','opportunities','contacts','work'],true) ? '#customer-success-advanced' : '#customer-success';
        return redirect()->to(route('customers.show',$customer).$anchor);
    }
}
