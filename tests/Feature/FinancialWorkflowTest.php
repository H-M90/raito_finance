<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\User;
use App\Services\AddendumService;
use App\Services\CollectionService;
use App\Services\ContractService;
use App\Services\DiscountVoucherService;
use App\Services\QuotationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
            'password' => 'password123',
            'is_active' => true,
        ]);
        $this->actingAs($this->user);

        $this->customer = Customer::create([
            'code' => 'CUS-TEST',
            'name' => 'عميل الاختبار',
            'segment' => 'standard',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'code' => 'ERP-TEST',
            'name' => 'موديول اختبار',
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
    }

    public function test_one_time_contract_requires_installments_covering_net_value(): void
    {
        $this->expectException(DomainException::class);
        app(ContractService::class)->create($this->contractData([]));
    }

    public function test_contract_is_active_and_creates_receivable_and_ledger_entry(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));

        $this->assertSame('active', $contract->status);
        $this->assertSame(1, $contract->installments()->count());
        $this->assertSame(1, $contract->receivables()->count());
        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $this->customer->id,
            'contract_id' => $contract->id,
            'entry_type' => 'receivable',
            'debit' => 1150.00,
            'credit' => 0,
            'is_reversed' => false,
        ]);
    }

    public function test_collection_must_be_fully_allocated_and_cannot_exceed_receivable(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));
        $receivable = $contract->receivables()->firstOrFail();

        $this->expectException(DomainException::class);
        app(CollectionService::class)->create([
            'customer_id' => $this->customer->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1200,
            'payment_method' => 'transfer',
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => 1200]],
        ]);
    }

    public function test_collection_cannot_allocate_receivable_from_another_contract(): void
    {
        $first = app(ContractService::class)->create($this->contractData([
            ['name'=>'دفعة العقد الأول','due_date'=>today()->toDateString(),'net_amount'=>1000],
        ]));
        $second = app(ContractService::class)->create($this->contractData([
            ['name'=>'دفعة العقد الثاني','due_date'=>today()->toDateString(),'net_amount'=>1000],
        ]));
        $foreignReceivable=$second->receivables()->firstOrFail();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('عقد آخر');
        app(CollectionService::class)->create([
            'customer_id'=>$this->customer->id,'contract_id'=>$first->id,'collection_date'=>today()->toDateString(),
            'currency'=>'SAR','amount'=>1150,'payment_method'=>'transfer',
            'allocations'=>[['receivable_id'=>$foreignReceivable->id,'amount'=>1150]],
        ]);
    }

    public function test_collection_is_confirmed_immediately_and_cancel_reopen_reverses_effect(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));
        $receivable = $contract->receivables()->firstOrFail();

        $service = app(CollectionService::class);
        $collection = $service->create([
            'customer_id' => $this->customer->id,
            'collection_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 1150,
            'payment_method' => 'transfer',
            'reference_no' => 'TRX-1',
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => 1150]],
        ]);

        $this->assertSame('confirmed', $collection->status);
        $this->assertSame(0.0, (float) $receivable->fresh()->remaining_amount);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'entry_type' => 'collection_allocation',
            'credit' => 1150.00,
            'is_reversed' => false,
        ]);

        $service->cancel($collection, 'خطأ في السند');
        $this->assertSame(1150.0, (float) $receivable->fresh()->remaining_amount);
        $this->assertSame('cancelled', $collection->fresh()->status);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'entry_type' => 'collection_allocation',
            'is_reversed' => true,
        ]);

        $service->reopen($collection->fresh());
        $this->assertSame(0.0, (float) $receivable->fresh()->remaining_amount);
        $this->assertSame('confirmed', $collection->fresh()->status);
    }

    public function test_discount_voucher_reduces_receivable_and_appears_in_ledger(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));
        $receivable = $contract->receivables()->firstOrFail();

        $voucher = app(DiscountVoucherService::class)->create([
            'customer_id' => $this->customer->id,
            'receivable_id' => $receivable->id,
            'voucher_date' => today()->toDateString(),
            'currency' => 'SAR',
            'amount' => 150,
            'reason' => 'خصم تجاري',
        ]);

        $this->assertSame(1000.0, (float) $receivable->fresh()->remaining_amount);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'source_type' => $voucher::class,
            'source_id' => $voucher->id,
            'entry_type' => 'discount_voucher',
            'credit' => 150.00,
            'is_reversed' => false,
        ]);
    }

    public function test_updating_contract_does_not_delete_addendum_receivables(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));
        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 500,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 15,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق',
                'due_date' => today()->toDateString(),
                'net_amount' => 500,
            ]],
        ]);
        $addendumReceivable = $addendum->receivables()->firstOrFail();

        app(ContractService::class)->update($contract, $this->contractData([
            ['name' => 'الدفعة الأولى المعدلة', 'due_date' => today()->addDay()->toDateString(), 'net_amount' => 1000],
        ]));

        $this->assertDatabaseHas('receivables', [
            'id' => $addendumReceivable->id,
            'contract_addendum_id' => $addendum->id,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, $addendum->fresh()->receivables()->count());
    }

    public function test_cancelling_contract_temporarily_cancels_active_addendums_and_reopen_restores_them(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));
        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->toDateString(),
            'service_start_date' => today()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 500,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 15,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق',
                'due_date' => today()->toDateString(),
                'net_amount' => 500,
            ]],
        ]);

        $service = app(ContractService::class);
        $service->cancel($contract, 'تصحيح الاختبار');
        $this->assertSame('cancelled', $addendum->fresh()->status);
        $this->assertStringStartsWith('إلغاء تلقائي بسبب إلغاء العقد:', (string) $addendum->fresh()->cancellation_reason);

        $service->reopen($contract->fresh());
        $this->assertSame('active', $addendum->fresh()->status);
        $this->assertNull($addendum->fresh()->cancellation_reason);
    }

    public function test_accepted_current_quotation_can_convert_only_once_using_saved_snapshot(): void
    {
        $quotation = app(QuotationService::class)->create([
            'customer_id' => $this->customer->id,
            'activity_type' => 'erp',
            'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addDays(15)->toDateString(),
            'currency' => 'SAR',
            'billing_cycle' => 'one_time',
            'status' => 'accepted',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 1000,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 15,
            ]],
        ]);
        $this->assertNotEmpty($quotation->pricing_snapshot);
        $quotedTaxRate = (float) $quotation->tax_rate;
        config(['finance.tax_rate' => 20]);

        $data = $this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]);
        $data['sales_quotation_id'] = $quotation->id;
        $contract = app(ContractService::class)->create($data);

        $this->assertSame($quotation->id, $contract->sales_quotation_id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame(1000.0, (float) $contract->net_total);
        $this->assertSame($quotedTaxRate, (float) $contract->tax_rate);
        $this->assertSame((float) $quotation->tax_total, (float) $contract->tax_total);
        $this->assertSame((float) $quotation->grand_total, (float) $contract->grand_total);
        $this->assertSame(1000 * ($quotedTaxRate / 100), (float) $contract->installments()->firstOrFail()->tax_amount);

        $this->expectException(DomainException::class);
        app(ContractService::class)->create($data);
    }


    public function test_monthly_subscription_creates_due_receivable_immediately_and_scheduler_does_not_duplicate_it(): void
    {
        $data = $this->contractData([]);
        $data['billing_cycle'] = 'monthly';
        $data['items'][0]['maintenance_rate'] = 0;

        $contract = app(ContractService::class)->create($data);

        $this->assertDatabaseHas('receivables', [
            'contract_id' => $contract->id,
            'type' => 'monthly',
            'due_date' => today()->toDateString(),
            'net_amount' => 1000.00,
        ]);
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $contract->fresh()->next_billing_date?->toDateString());

        $this->artisan('finance:generate-receivables')->assertSuccessful();
        $this->assertSame(1, $contract->receivables()->where('type', 'monthly')->count());
    }

    public function test_annual_subscription_creates_due_receivable_immediately_and_advances_one_year(): void
    {
        $data = $this->contractData([]);
        $data['billing_cycle'] = 'annual';
        $data['items'][0]['maintenance_rate'] = 0;

        $contract = app(ContractService::class)->create($data);

        $this->assertDatabaseHas('receivables', [
            'contract_id' => $contract->id,
            'type' => 'annual',
            'due_date' => today()->toDateString(),
            'net_amount' => 1000.00,
        ]);
        $this->assertSame(today()->addYear()->toDateString(), $contract->fresh()->next_billing_date?->toDateString());
    }

    public function test_maintenance_is_generated_automatically_only_after_the_free_first_year(): void
    {
        $data = $this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->subYear()->toDateString(), 'net_amount' => 1000],
        ]);
        $data['contract_date'] = today()->subYear()->toDateString();
        $data['service_start_date'] = today()->subYear()->toDateString();
        $data['calculation_start_date'] = today()->subYear()->toDateString();

        $contract = app(ContractService::class)->create($data);

        $maintenance = $contract->receivables()->where('type', 'maintenance')->first();
        $this->assertNotNull($maintenance);
        $this->assertSame(today()->toDateString(), $maintenance->due_date->toDateString());
        $this->assertSame(150.0, (float) $maintenance->net_amount);
        $this->assertSame(today()->addYear()->toDateString(), $contract->fresh()->next_maintenance_date?->toDateString());
    }

    public function test_future_maintenance_is_not_generated_before_its_due_date(): void
    {
        $contract = app(ContractService::class)->create($this->contractData([
            ['name' => 'الدفعة الأولى', 'due_date' => today()->toDateString(), 'net_amount' => 1000],
        ]));

        $this->assertSame(0, $contract->receivables()->where('type', 'maintenance')->count());
        $this->assertSame(today()->addYear()->toDateString(), $contract->fresh()->next_maintenance_date?->toDateString());
    }


    public function test_backdated_addendum_maintenance_is_generated_automatically_after_its_free_year(): void
    {
        $contractData = $this->contractData([
            ['name' => 'دفعة العقد', 'due_date' => today()->subYears(2)->toDateString(), 'net_amount' => 1000],
        ]);
        $contractData['contract_date'] = today()->subYears(2)->toDateString();
        $contractData['service_start_date'] = today()->subYears(2)->toDateString();
        $contractData['calculation_start_date'] = today()->subYears(2)->toDateString();
        $contract = app(ContractService::class)->create($contractData);

        $addendum = app(AddendumService::class)->create($contract, [
            'addendum_date' => today()->subYear()->toDateString(),
            'service_start_date' => today()->subYear()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'requested_users' => 0,
                'unit_price' => 500,
                'user_unit_price' => 100,
                'discount_value' => 0,
                'maintenance_rate' => 10,
            ]],
            'installments' => [[
                'name' => 'دفعة الملحق',
                'due_date' => today()->subYear()->toDateString(),
                'net_amount' => 500,
            ]],
        ]);

        $maintenance = $addendum->receivables()->where('type', 'maintenance')->first();
        $this->assertNotNull($maintenance);
        $this->assertSame(today()->toDateString(), $maintenance->due_date->toDateString());
        $this->assertSame(50.0, (float) $maintenance->net_amount);
    }

    private function contractData(array $installments): array
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
            'installments' => $installments,
        ];
    }
}
