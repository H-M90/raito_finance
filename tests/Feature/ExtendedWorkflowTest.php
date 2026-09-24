<?php

namespace Tests\Feature;

use App\Jobs\CommitContractImport;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ImportBatch;
use App\Models\Intermediary;
use App\Models\Product;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AddendumService;
use App\Services\CollectionService;
use App\Services\ContractImportService;
use App\Services\ContractService;
use App\Services\CustomerStatementService;
use App\Services\DiscountVoucherService;
use App\Services\ExpenseService;
use App\Services\InstallationService;
use App\Services\IntermediaryCommissionService;
use App\Services\ProfitabilityService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtendedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Product $product;
    private Product $ptsProduct;
    private Product $sensorProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Extended Test Admin',
            'email' => 'extended-admin@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $this->customer = Customer::create([
            'code' => 'CUS-EXT',
            'name' => 'عميل الاختبارات الممتدة',
            'segment' => 'standard',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'code' => 'ERP-EXT',
            'name' => 'موديول ممتد',
            'type' => 'erp_module',
            'unit' => 'license',
            'default_sale_price' => 1000,
            'billing_cycle' => 'one_time',
            'supports_user_pricing' => true,
            'included_users_one_time' => 3,
            'extra_user_price_one_time' => 100,
            'user_price_monthly' => 10,
            'user_price_annual' => 100,
            'default_maintenance_rate' => 15,
            'is_active' => true,
        ]);
        $this->ptsProduct = Product::create([
            'code'=>'PTS-EXT','name'=>'جهاز PTS','type'=>'pts','unit'=>'device','default_sale_price'=>0,
            'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>0,
            'extra_user_price_one_time'=>0,'user_price_monthly'=>0,'user_price_annual'=>0,
            'default_maintenance_rate'=>0,'is_active'=>true,
        ]);
        $this->sensorProduct = Product::create([
            'code'=>'SENSOR-EXT','name'=>'حساس خزان','type'=>'sensor','unit'=>'sensor','default_sale_price'=>0,
            'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>0,
            'extra_user_price_one_time'=>0,'user_price_monthly'=>0,'user_price_annual'=>0,
            'default_maintenance_rate'=>0,'is_active'=>true,
        ]);
    }

    public function test_installation_capacity_includes_active_addendums(): void
    {
        $contractData = $this->oneTimeContractData();
        $contractData['activity_type'] = 'stations';
        $contract = app(ContractService::class)->create($contractData);

        app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,'quantity' => 1,'requested_users' => 0,'unit_price' => 500,
                'user_unit_price' => 100,'discount_value' => 0,'maintenance_rate' => 15,
            ],[
                'product_id' => $this->ptsProduct->id,'quantity' => 1,'requested_users' => 0,'unit_price' => 0,
                'user_unit_price' => 0,'discount_value' => 0,'maintenance_rate' => 0,
            ],[
                'product_id' => $this->sensorProduct->id,'quantity' => 2,'requested_users' => 0,'unit_price' => 0,
                'user_unit_price' => 0,'discount_value' => 0,'maintenance_rate' => 0,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق',
                'due_date' => today()->toDateString(),
                'net_amount' => 500,
            ]],
        ]);

        $station = Station::create([
            'customer_id' => $this->customer->id,
            'name' => 'محطة الاختبار',
            'is_active' => true,
        ]);
        $contract->stations()->attach($station->id, ['pts_count' => 1, 'sensor_count' => 2]);

        $installation = app(InstallationService::class)->create([
            'customer_id' => $this->customer->id,
            'contract_id' => $contract->id,
            'installation_date' => today()->toDateString(),
            'status' => 'completed',
            'stations' => [[
                'station_id' => $station->id,
                'pts_installed' => 1,
                'sensor_count' => 2,
                'installed_at' => today()->toDateString(),
                'status' => 'completed',
            ]],
        ]);

        $this->assertSame(1, $installation->stations()->where('pts_installed', true)->count());
        $this->assertSame(2, (int) $installation->stations()->sum('sensor_count'));
    }

    public function test_addendum_cannot_be_cancelled_while_its_device_capacity_is_installed(): void
    {
        $data = $this->oneTimeContractData();
        $data['activity_type'] = 'stations';
        $contract = app(ContractService::class)->create($data);
        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,'quantity' => 1,'requested_users' => 0,'unit_price' => 500,
                'user_unit_price' => 100,'discount_value' => 0,'maintenance_rate' => 15,
            ],[
                'product_id' => $this->ptsProduct->id,'quantity' => 1,'requested_users' => 0,'unit_price' => 0,
                'user_unit_price' => 0,'discount_value' => 0,'maintenance_rate' => 0,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق',
                'due_date' => today()->toDateString(),
                'net_amount' => 500,
            ]],
        ]);
        $station = Station::create(['customer_id'=>$this->customer->id,'name'=>'محطة سعة الملحق','is_active'=>true]);
        $contract->stations()->attach($station->id, ['pts_count'=>1,'sensor_count'=>0]);
        app(InstallationService::class)->create([
            'customer_id'=>$this->customer->id,
            'contract_id'=>$contract->id,
            'installation_date'=>today()->toDateString(),
            'status'=>'completed',
            'stations'=>[['station_id'=>$station->id,'pts_installed'=>1,'sensor_count'=>0,'installed_at'=>today()->toDateString(),'status'=>'completed']],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('تم تركيبه فعليًا');
        app(AddendumService::class)->cancel($addendum, 'إلغاء للاختبار');
    }

    public function test_cancelled_installation_cannot_reopen_after_its_addendum_capacity_is_cancelled(): void
    {
        $data = $this->oneTimeContractData();
        $data['activity_type'] = 'stations';
        $contract = app(ContractService::class)->create($data);
        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date'=>today()->toDateString(),'service_start_date'=>today()->toDateString(),
            'items'=>[
                ['product_id'=>$this->product->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>500,'user_unit_price'=>100,'discount_value'=>0,'maintenance_rate'=>15],
                ['product_id'=>$this->ptsProduct->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0],
            ],
            'installments'=>[['name'=>'دفعة الملحق','due_date'=>today()->toDateString(),'net_amount'=>500]],
        ]);
        $station = Station::create(['customer_id'=>$this->customer->id,'name'=>'محطة إعادة الفتح','is_active'=>true]);
        $contract->stations()->attach($station->id, ['pts_count'=>1,'sensor_count'=>0]);
        $installation = app(InstallationService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$contract->id,'installation_date'=>today()->toDateString(),'status'=>'completed',
            'stations'=>[['station_id'=>$station->id,'pts_installed'=>1,'sensor_count'=>0,'installed_at'=>today()->toDateString(),'status'=>'completed']],
        ]);
        app(InstallationService::class)->cancel($installation, 'إلغاء مؤقت');
        $contract->stations()->updateExistingPivot($station->id, ['pts_count'=>0]);
        app(AddendumService::class)->cancel($addendum, 'إلغاء السعة');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('غير مخصص لها PTS');
        app(InstallationService::class)->reopen($installation->fresh());
    }

    public function test_contract_device_capacity_is_derived_from_line_items(): void
    {
        $data=$this->oneTimeContractData();
        $data['activity_type']='stations';
        $data['items'][]=['product_id'=>$this->ptsProduct->id,'quantity'=>2,'requested_users'=>0,'unit_price'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0];
        $data['items'][]=['product_id'=>$this->sensorProduct->id,'quantity'=>5,'requested_users'=>0,'unit_price'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0];
        $contract=app(ContractService::class)->create($data);
        $this->assertSame(2,(int)$contract->pts_count);
        $this->assertSame(5,(int)$contract->sensor_count);
    }

    public function test_station_cannot_receive_pts_twice_even_when_contract_has_more_capacity(): void
    {
        $data=$this->oneTimeContractData();
        $data['activity_type']='stations';
        $data['items'][]=['product_id'=>$this->ptsProduct->id,'quantity'=>2,'requested_users'=>0,'unit_price'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0];
        $contract=app(ContractService::class)->create($data);
        $station=Station::create(['customer_id'=>$this->customer->id,'name'=>'محطة PTS واحد','is_active'=>true]);
        $contract->stations()->attach($station->id,['pts_count'=>1,'sensor_count'=>0]);

        app(InstallationService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$contract->id,'installation_date'=>today()->toDateString(),'status'=>'completed',
            'stations'=>[['station_id'=>$station->id,'pts_installed'=>1,'sensor_count'=>0,'installed_at'=>today()->toDateString(),'status'=>'completed']],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('المحطة الواحدة لا تحتوي إلا على PTS واحد');
        app(InstallationService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$contract->id,'installation_date'=>today()->toDateString(),'status'=>'completed',
            'stations'=>[['station_id'=>$station->id,'pts_installed'=>1,'sensor_count'=>0,'installed_at'=>today()->toDateString(),'status'=>'completed']],
        ]);
    }

    public function test_operational_contract_edit_does_not_rebuild_receivables(): void
    {
        $data=$this->oneTimeContractData();
        $contract=app(ContractService::class)->create($data);
        $receivableId=$contract->receivables()->value('id');
        $data['domain']='erp.example.test';

        app(ContractService::class)->update($contract,$data);

        $this->assertSame('erp.example.test',$contract->fresh()->domain);
        $this->assertDatabaseHas('receivables',['id'=>$receivableId,'contract_id'=>$contract->id]);
        $this->assertSame(1,$contract->receivables()->count());
    }

    public function test_cash_profitability_counts_only_commission_payments(): void
    {
        $intermediary=Intermediary::create(['name'=>'وسيط الربحية النقدية','is_active'=>true]);
        $data=$this->oneTimeContractData();
        $data['intermediary_id']=$intermediary->id;
        $data['commission_type']='percentage';
        $data['commission_value']=10;
        $data['commission_due_basis']='collection';
        $contract=app(ContractService::class)->create($data);
        $receivable=$contract->receivables()->firstOrFail();
        app(CollectionService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$contract->id,'collection_date'=>today()->toDateString(),'currency'=>'SAR','amount'=>1150,'payment_method'=>'transfer',
            'allocations'=>[['receivable_id'=>$receivable->id,'amount'=>1150]],
        ]);
        $commission=$contract->intermediary_id ? \App\Models\IntermediaryCommission::where('contract_id',$contract->id)->firstOrFail() : null;

        $before=app(ProfitabilityService::class)->contracts($this->customer->id,'SAR','cash',today()->toDateString(),today()->toDateString())->firstWhere('id',$contract->id);
        $this->assertSame(0.0,(float)$before->commission_cost);

        app(IntermediaryCommissionService::class)->pay($commission,['payment_date'=>today()->toDateString(),'amount'=>40,'payment_method'=>'transfer']);
        $after=app(ProfitabilityService::class)->contracts($this->customer->id,'SAR','cash',today()->toDateString(),today()->toDateString())->firstWhere('id',$contract->id);
        $this->assertSame(40.0,(float)$after->commission_cost);
    }

    public function test_contract_with_actual_installation_cannot_be_cancelled(): void
    {
        $data=$this->oneTimeContractData();
        $data['activity_type']='stations';
        $data['items'][]=['product_id'=>$this->ptsProduct->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0];
        $contract=app(ContractService::class)->create($data);
        $station=Station::create(['customer_id'=>$this->customer->id,'name'=>'محطة إلغاء العقد','is_active'=>true]);
        $contract->stations()->attach($station->id,['pts_count'=>1,'sensor_count'=>0]);
        app(InstallationService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$contract->id,'installation_date'=>today()->toDateString(),'status'=>'completed',
            'stations'=>[['station_id'=>$station->id,'pts_installed'=>1,'sensor_count'=>0,'installed_at'=>today()->toDateString(),'status'=>'completed']],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('عليه تركيب فعلي');
        app(ContractService::class)->cancel($contract,'اختبار');
    }

    public function test_purchase_is_confirmed_and_hard_deleted_with_allocations(): void
    {
        $contract = app(ContractService::class)->create($this->oneTimeContractData());
        $supplier = Supplier::create(['name' => 'مورد الاختبار', 'is_active' => true]);

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'description' => 'جهاز للاختبار',
                'quantity' => 1,
                'unit_cost' => 250,
            ]],
            'allocations' => [[
                'item_index' => 0,
                'customer_id' => $this->customer->id,
                'contract_id' => $contract->id,
                'amount' => 250,
            ]],
        ]);

        $this->assertSame('confirmed', $purchase->status);
        $itemId = $purchase->items()->value('id');
        $this->assertDatabaseHas('purchase_allocations', ['purchase_item_id' => $itemId, 'amount' => 250]);

        app(PurchaseService::class)->delete($purchase);

        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
        $this->assertDatabaseMissing('purchase_items', ['id' => $itemId]);
        $this->assertDatabaseMissing('purchase_allocations', ['purchase_item_id' => $itemId]);
    }

    public function test_statement_contains_due_installment_collection_receipt_and_discount_voucher(): void
    {
        $contract = app(ContractService::class)->create($this->oneTimeContractData());
        $receivable = $contract->receivables()->firstOrFail();

        app(DiscountVoucherService::class)->create([
            'customer_id' => $this->customer->id,
            'receivable_id' => $receivable->id,
            'voucher_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 150,
            'reason' => 'خصم اختبار',
        ]);

        app(CollectionService::class)->create([
            'customer_id' => $this->customer->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1000,
            'payment_method' => 'transfer',
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => 1000]],
        ]);

        $statement = app(CustomerStatementService::class)->generate(
            $this->customer,
            'SAR',
            today()->toDateString(),
            today()->toDateString(),
        );

        $labels = $statement['rows']->pluck('label');
        $this->assertTrue($labels->contains('دفعة عقد'));
        $this->assertTrue($labels->contains('سند قبض'));
        $this->assertTrue($labels->contains('سند خصم'));
        $this->assertSame(0.0, (float) $statement['closing']);
    }

    public function test_intermediary_commission_is_created_only_from_actual_collection_allocation(): void
    {
        $intermediary = Intermediary::create(['name' => 'وسيط الاختبار', 'is_active' => true]);
        $data = $this->oneTimeContractData();
        $data['intermediary_id'] = $intermediary->id;
        $data['commission_type'] = 'percentage';
        $data['commission_value'] = 10;
        $data['commission_due_basis'] = 'collection';
        $contract = app(ContractService::class)->create($data);
        $receivable = $contract->receivables()->firstOrFail();

        $this->assertDatabaseCount('intermediary_commissions', 0);

        $collection = app(CollectionService::class)->create([
            'customer_id' => $this->customer->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1150,
            'payment_method' => 'transfer',
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => 1150]],
        ]);

        $allocation = $collection->allocations()->firstOrFail();
        $this->assertDatabaseHas('intermediary_commissions', [
            'contract_id' => $contract->id,
            'collection_id' => $collection->id,
            'collection_allocation_id' => $allocation->id,
            'base_amount' => 1000,
            'amount' => 100,
            'status' => 'outstanding',
        ]);
    }

    public function test_maintenance_collection_does_not_create_base_contract_commission(): void
    {
        $intermediary = Intermediary::create(['name' => 'وسيط صيانة', 'is_active' => true]);
        $data = $this->oneTimeContractData();
        $data['intermediary_id'] = $intermediary->id;
        $data['commission_type'] = 'percentage';
        $data['commission_value'] = 10;
        $data['commission_due_basis'] = 'collection';
        $contract = app(ContractService::class)->create($data);

        $maintenance = \App\Models\Receivable::create([
            'number' => 'REC-MAINT-COM',
            'customer_id' => $this->customer->id,
            'contract_id' => $contract->id,
            'type' => 'maintenance',
            'name' => 'صيانة اختبارية',
            'due_date' => today(),
            'currency' => 'SAR',
            'net_amount' => 1000,
            'tax_amount' => 150,
            'total_amount' => 1150,
            'remaining_amount' => 1150,
            'status' => 'due',
        ]);

        app(CollectionService::class)->create([
            'customer_id' => $this->customer->id,
            'contract_id' => $contract->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1150,
            'payment_method' => 'transfer',
            'allocations' => [['receivable_id' => $maintenance->id, 'amount' => 1150]],
        ]);

        $this->assertDatabaseCount('intermediary_commissions', 0);
    }

    public function test_addendum_cannot_reopen_while_parent_contract_is_cancelled(): void
    {
        $contract = app(ContractService::class)->create($this->oneTimeContractData());
        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 1, 'requested_users' => 0,
                'unit_price' => 500, 'user_unit_price' => 100, 'discount_value' => 0, 'maintenance_rate' => 0,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق', 'due_date' => today()->toDateString(), 'net_amount' => 500,
            ]],
        ]);
        app(AddendumService::class)->cancel($addendum, 'إلغاء يدوي');
        app(ContractService::class)->cancel($contract, 'إلغاء العقد');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('العقد الأساسي ملغي');
        app(AddendumService::class)->reopen($addendum->fresh());
    }

    public function test_profitability_is_separated_by_currency_and_earned_mode_uses_period_receivables(): void
    {
        $monthly = app(ContractService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'monthly',
            'currency' => 'SAR',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 1000,
                'user_unit_price' => 10,
                'discount_value' => 0,
                'maintenance_rate' => 0,
            ]],
        ]);

        $usdData = $this->oneTimeContractData();
        $usdData['currency'] = 'USD';
        app(ContractService::class)->create($usdData);

        $rows = app(ProfitabilityService::class)->contracts(
            null,
            'SAR',
            'earned',
            today()->toDateString(),
            today()->toDateString(),
        );

        $this->assertCount(1, $rows);
        $this->assertSame($monthly->id, $rows->first()->id);
        $this->assertSame(1000.0, (float) $rows->first()->calculated_revenue);
    }

    public function test_expense_can_be_cancelled_and_reopened_without_deleting_financial_history(): void
    {
        $category = ExpenseCategory::create(['name'=>'مصروف اختبار','is_active'=>true]);
        $contract = app(ContractService::class)->create($this->oneTimeContractData());
        $service = app(ExpenseService::class);
        $expense = $service->create([
            'expense_date'=>today()->toDateString(),
            'expense_category_id'=>$category->id,
            'description'=>'مصروف مرتبط بالعقد',
            'amount'=>250,
            'payment_method'=>'transfer',
            'customer_id'=>$this->customer->id,
            'contract_id'=>$contract->id,
        ]);

        $service->cancel($expense, 'تصحيح المصروف');
        $this->assertSame('cancelled', $expense->fresh()->status);
        $this->assertDatabaseHas('expenses', ['id'=>$expense->id,'status'=>'cancelled','cancellation_reason'=>'تصحيح المصروف']);

        $service->reopen($expense->fresh());
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertDatabaseHas('expenses', ['id'=>$expense->id,'status'=>'approved']);
    }

    public function test_import_commit_is_queued_instead_of_running_in_browser_request(): void
    {
        Queue::fake();
        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'contracts',
            'original_filename' => 'contracts.xlsx',
            'stored_path' => 'imports/contracts.xlsx',
            'status' => 'reviewing',
            'total_rows' => 0,
            'processed_rows' => 0,
            'progress_percentage' => 100,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'created_by' => $this->user->id,
        ]);

        app(ContractImportService::class)->queueCommit($batch);

        $this->assertSame('commit_queued', $batch->fresh()->status);
        Queue::assertPushed(CommitContractImport::class, fn (CommitContractImport $job) => $job->batchId === $batch->id);
    }

    private function oneTimeContractData(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'contract_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'billing_cycle' => 'one_time',
            'currency' => 'SAR',
            'commission_due_basis' => 'collection',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 1000,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 15,
            ]],
            'installments' => [[
                'name' => 'الدفعة الأولى',
                'due_date' => today()->toDateString(),
                'net_amount' => 1000,
            ]],
        ];
    }
}
