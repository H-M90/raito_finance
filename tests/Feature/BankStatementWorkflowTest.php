<?php
namespace Tests\Feature;

use App\Models\BankStatementBatch;
use App\Models\BankStatementRow;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\BankStatementService;
use App\Services\ContractService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankStatementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_bank_rows_create_expense_and_collection_only_on_approval(): void
    {
        $this->assertTrue(Schema::hasTable('bank_statement_batches'));
        $this->assertTrue(Schema::hasTable('bank_statement_rows'));
        $user=User::create(['name'=>'Admin','email'=>'bank@example.test','password'=>'password123','is_active'=>true]);$this->actingAs($user);
        $customer=Customer::create(['code'=>'CUS-BANK','name'=>'عميل البنك','segment'=>'standard','status'=>'active']);
        $product=Product::create(['code'=>'ERP-BANK','name'=>'موديول','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,'billing_cycle'=>'one_time','default_maintenance_rate'=>0,'supports_user_pricing'=>false,'is_active'=>true]);
        $contract=app(ContractService::class)->create([
            'customer_id'=>$customer->id,'activity_type'=>'erp','contract_date'=>today()->toDateString(),'service_start_date'=>today()->toDateString(),'billing_cycle'=>'one_time','currency'=>'SAR','commission_due_basis'=>'collection',
            'items'=>[['product_id'=>$product->id,'quantity'=>1,'requested_users'=>0,'unit_price'=>1000,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
            'installments'=>[['name'=>'الدفعة الأولى','due_date'=>today()->toDateString(),'net_amount'=>1000]],
        ]);
        $receivable=$contract->receivables()->firstOrFail();
        $category=ExpenseCategory::create(['name'=>'رسوم بنكية','is_active'=>true]);
        $batch=BankStatementBatch::create(['uuid'=>(string)Str::uuid(),'original_filename'=>'bank.xlsx','stored_path'=>'test/bank.xlsx','currency'=>'SAR','status'=>'reviewing','total_rows'=>2,'total_debit'=>250,'total_credit'=>1150,'created_by'=>$user->id]);
        $expenseRow=BankStatementRow::create(['bank_statement_batch_id'=>$batch->id,'row_number'=>2,'transaction_date'=>today(),'description'=>'رسوم تشغيل','debit'=>250,'credit'=>0,'amount'=>250,'direction'=>'out','classification'=>'expense','status'=>'draft']);
        $collectionRow=BankStatementRow::create(['bank_statement_batch_id'=>$batch->id,'row_number'=>3,'transaction_date'=>today(),'description'=>'تحويل العميل','reference_no'=>'BANK-REF-1','debit'=>0,'credit'=>1150,'amount'=>1150,'direction'=>'in','classification'=>'collection','status'=>'draft']);

        $service=app(BankStatementService::class);
        $service->saveRow($expenseRow,['classification'=>'expense','expense_category_id'=>$category->id,'expense_description'=>'رسوم تشغيل البنك']);
        $service->saveRow($collectionRow,['classification'=>'collection','collection_customer_id'=>$customer->id,'allocations'=>[['receivable_id'=>$receivable->id,'amount'=>1150]]]);

        $this->assertDatabaseCount('expenses',0);$this->assertDatabaseCount('collections',0);
        $service->approve($batch);

        $this->assertDatabaseHas('expenses',['bank_statement_row_id'=>$expenseRow->id,'amount'=>250,'status'=>'approved']);
        $this->assertDatabaseHas('collections',['bank_statement_row_id'=>$collectionRow->id,'amount'=>1150,'status'=>'confirmed','reference_no'=>'BANK-REF-1']);
        $this->assertSame(0.0,(float)$receivable->fresh()->remaining_amount);
        $this->assertSame('approved',$batch->fresh()->status);
        $this->assertSame('posted',$expenseRow->fresh()->status);
        $this->assertSame('posted',$collectionRow->fresh()->status);
    }

    public function test_batch_cannot_approve_while_a_row_is_not_saved(): void
    {
        $user=User::create(['name'=>'Admin','email'=>'bank2@example.test','password'=>'password123','is_active'=>true]);$this->actingAs($user);
        $batch=BankStatementBatch::create(['uuid'=>(string)Str::uuid(),'original_filename'=>'bank.xlsx','stored_path'=>'test/bank.xlsx','currency'=>'SAR','status'=>'reviewing','total_rows'=>1,'created_by'=>$user->id]);
        BankStatementRow::create(['bank_statement_batch_id'=>$batch->id,'row_number'=>2,'transaction_date'=>today(),'description'=>'حركة','debit'=>100,'credit'=>0,'amount'=>100,'direction'=>'out','classification'=>'expense','status'=>'draft']);
        $this->expectException(DomainException::class);
        app(BankStatementService::class)->approve($batch);
    }
}
