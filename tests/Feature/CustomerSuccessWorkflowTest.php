<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPlaybookRun;
use App\Models\CustomerSuccessTask;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractService;
use App\Services\CustomerSuccessService;
use Database\Seeders\CustomerSuccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSuccessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_contract_starts_new_customer_playbook_and_completion_moves_to_activation(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $user=User::create(['name'=>'CS Owner','email'=>'cs@example.test','password'=>'password123','is_active'=>true]);
        $this->actingAs($user);
        $customer=Customer::create(['code'=>'CUS-CS-1','name'=>'عميل نجاح','segment'=>'standard','status'=>'active','sales_owner_id'=>$user->id]);
        $product=Product::create([
            'code'=>'CS-ERP','name'=>'ERP','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,'billing_cycle'=>'one_time',
            'supports_user_pricing'=>false,'included_users_one_time'=>0,'extra_user_price_one_time'=>0,'user_price_monthly'=>0,'user_price_annual'=>0,
            'default_maintenance_rate'=>0,'is_active'=>true,
        ]);

        app(ContractService::class)->create([
            'customer_id'=>$customer->id,'activity_type'=>'erp','contract_date'=>today()->toDateString(),'service_start_date'=>today()->toDateString(),
            'billing_cycle'=>'one_time','currency'=>'SAR','items'=>[['product_id'=>$product->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>1000,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
            'installments'=>[['name'=>'دفعة','due_date'=>today()->toDateString(),'net_amount'=>1000]],
        ]);

        $profile=$customer->successProfile()->with('lifecycleStatus')->firstOrFail();
        $this->assertSame('NEW',$profile->lifecycleStatus->code);
        $run=CustomerPlaybookRun::with('tasks')->where('customer_id',$customer->id)->whereHas('playbook',fn($q)=>$q->where('code','PB_NEW_CUSTOMER'))->firstOrFail();
        $this->assertGreaterThan(0,$run->tasks()->count());

        $service=app(CustomerSuccessService::class);
        foreach($run->tasks as $task)$service->completeTask($task);

        $profile->refresh()->load('lifecycleStatus');
        $this->assertSame('ACTIVATING',$profile->lifecycleStatus->code);
        $this->assertTrue(CustomerPlaybookRun::where('customer_id',$customer->id)->whereHas('playbook',fn($q)=>$q->where('code','PB_ACTIVATION'))->exists());
    }

    public function test_critical_flag_moves_customer_to_at_risk_and_creates_tasks(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $customer=Customer::create(['code'=>'CUS-CS-2','name'=>'عميل خطر','segment'=>'standard','status'=>'active']);
        $service=app(CustomerSuccessService::class);
        $service->ensureProfile($customer);
        $flag=$service->openFlag($customer,'CANCELLATION_REQUESTED','طلب العميل الإلغاء.');

        $this->assertSame('open',$flag->status);
        $this->assertNotNull($flag->playbook_run_id);
        $profile=$customer->successProfile()->with('lifecycleStatus')->firstOrFail();
        $this->assertSame('AT_RISK',$profile->lifecycleStatus->code);
        $this->assertSame('critical',$profile->attention_level);
    }

    public function test_low_usage_requires_explicit_resolution_and_then_resolves_flag(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $customer=Customer::create(['code'=>'CUS-CS-3','name'=>'عميل استخدام منخفض','segment'=>'standard','status'=>'active']);
        $service=app(CustomerSuccessService::class);
        $service->ensureProfile($customer);
        $flag=$service->openFlag($customer,'LOW_USAGE','انخفض الاستخدام عن الحد المتوقع.');

        $profile=$customer->successProfile()->with('lifecycleStatus')->firstOrFail();
        $this->assertSame('LOW_ADOPTION',$profile->lifecycleStatus->code);

        $run=CustomerPlaybookRun::with('tasks')->findOrFail($flag->playbook_run_id);
        foreach($run->tasks as $task)$service->completeTask($task);
        $run->refresh();
        $this->assertSame('awaiting_resolution',$run->status);

        $service->completeRun($run,'AT_RISK','لم يتحسن الاستخدام.');
        $this->assertSame('resolved',$flag->fresh()->status);
        $profile->refresh()->load('lifecycleStatus');
        $this->assertSame('AT_RISK',$profile->lifecycleStatus->code);
    }

    public function test_deterministic_flag_playbook_closes_flag_after_all_tasks(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $customer=Customer::create(['code'=>'CUS-CS-4','name'=>'عميل تغيير محاسب','segment'=>'standard','status'=>'active']);
        $service=app(CustomerSuccessService::class);
        $flag=$service->openFlag($customer,'ACCOUNTANT_CHANGED','تم تغيير المحاسب.');
        $run=CustomerPlaybookRun::with('tasks')->findOrFail($flag->playbook_run_id);

        foreach($run->tasks as $task)$service->completeTask($task);

        $this->assertSame('completed',$run->fresh()->status);
        $this->assertSame('resolved',$flag->fresh()->status);
    }

    public function test_new_customer_gets_daily_automatic_follow_up_and_completion_schedules_next_day(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $customer=Customer::create(['code'=>'CUS-CS-5','name'=>'عميل جديد يومي','segment'=>'standard','status'=>'active']);
        $service=app(CustomerSuccessService::class);

        $task=$service->ensureAutomaticFollowUp($customer);

        $this->assertNotNull($task);
        $this->assertSame(CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX.'عميل جديد',$task->title);
        $this->assertSame('high',$task->priority);
        $this->assertTrue($task->due_at->lessThanOrEqualTo(now()->addMinute()));

        $service->completeTask($task,'تم التواصل مع العميل.');

        $next=CustomerSuccessTask::open()->where('customer_id',$customer->id)->where('title',$task->title)->firstOrFail();
        $this->assertTrue($next->due_at->between(now()->addHours(23),now()->addHours(25)));
    }

    public function test_cancellation_request_creates_daily_follow_up_with_reason_not_duplicate_status_wording(): void
    {
        $this->seed(CustomerSuccessSeeder::class);
        $customer=Customer::create(['code'=>'CUS-CS-6','name'=>'عميل طلب إلغاء','segment'=>'standard','status'=>'active']);
        $service=app(CustomerSuccessService::class);

        $service->openFlag($customer,'CANCELLATION_REQUESTED','طلب العميل إنهاء الخدمة.');

        $profile=$customer->successProfile()->with('lifecycleStatus')->firstOrFail();
        $this->assertSame('AT_RISK',$profile->lifecycleStatus->code);
        $task=CustomerSuccessTask::open()->where('customer_id',$customer->id)->where('title',CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX.'طلب إلغاء')->firstOrFail();
        $this->assertSame('critical',$task->priority);
    }

}
