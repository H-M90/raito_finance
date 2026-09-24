<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\ContractMaintenanceService;
use App\Services\ContractService;
use App\Services\CustomerContractDueReportService;
use App\Services\RecurringReceivableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceOverrideAndDueReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-09 09:00:00');
        $this->actingAs(User::create([
            'name' => 'Finance Admin', 'email' => 'finance-report@example.test', 'password' => 'password123', 'is_active' => true,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manual_maintenance_overrides_item_sum_and_auto_mode_can_restore_item_calculation(): void
    {
        [$customer, $product] = $this->masterData('MAINT');
        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id, 'activity_type' => 'erp', 'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(), 'billing_cycle' => 'one_time', 'currency' => 'SAR',
            'items' => [['product_id'=>$product->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>1000,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>15]],
            'installments' => [['name'=>'دفعة العقد','due_date'=>today()->toDateString(),'net_amount'=>1000]],
        ]);

        $generator = app(RecurringReceivableGenerator::class);
        $this->assertSame(150.0, $generator->maintenanceNetAt($contract->fresh(), today()));

        app(ContractMaintenanceService::class)->update($contract->fresh(), 'manual', 500, today()->toDateString(), 'اتفاق خاص');
        $this->assertSame(500.0, $generator->maintenanceNetAt($contract->fresh(), today()));
        $this->assertDatabaseHas('contract_maintenance_settings', ['contract_id'=>$contract->id,'mode'=>'manual','annual_amount'=>500.00]);

        app(ContractMaintenanceService::class)->update($contract->fresh(), 'auto', null, today()->addDay()->toDateString(), 'عودة للحساب التلقائي');
        $this->assertSame(150.0, $generator->maintenanceNetAt($contract->fresh(), today()->addDay()));
    }

    public function test_due_report_uses_scheduled_annual_date_even_when_receivable_is_not_generated_yet(): void
    {
        [$customer, $product] = $this->masterData('REPORT');
        $contract = app(ContractService::class)->create([
            'customer_id' => $customer->id, 'activity_type' => 'erp', 'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(), 'billing_cycle' => 'annual', 'currency' => 'SAR',
            'items' => [['product_id'=>$product->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>1200,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
            'installments' => [],
        ]);

        $first = $contract->receivables()->where('type','annual')->firstOrFail();
        $first->forceFill(['collected_amount'=>$first->total_amount,'remaining_amount'=>0,'status'=>'paid'])->save();

        $rows = app(CustomerContractDueReportService::class)->report(Request::create('/reports/customer-products-due', 'GET'));
        $row = $rows->getCollection()->firstWhere('id', $contract->id);

        $this->assertNotNull($row);
        $this->assertSame(today()->addYear()->toDateString(), $row->next_due_date_report->toDateString());
        $this->assertSame(1380.0, (float) $row->next_due_amount_report);
        $this->assertFalse((bool) $row->next_due_generated_report);
        $this->assertTrue($row->report_products->contains('id', $product->id));
    }

    private function masterData(string $suffix): array
    {
        $customer = Customer::create(['code'=>'C-'.$suffix,'name'=>'Customer '.$suffix,'segment'=>'standard','status'=>'active']);
        $product = Product::create([
            'code'=>'P-'.$suffix,'name'=>'Product '.$suffix,'type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,
            'billing_cycle'=>'one_time','default_maintenance_rate'=>15,'supports_user_pricing'=>false,'included_users_one_time'=>0,'is_active'=>true,
        ]);
        return [$customer, $product];
    }
}
