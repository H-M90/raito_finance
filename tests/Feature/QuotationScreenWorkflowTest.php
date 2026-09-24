<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Intermediary;
use App\Models\Role;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationScreenWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_quotation_can_be_created_from_http_form_and_saves_commission_from_net_before_tax(): void
    {
        $role = Role::create(['name'=>'مدير النظام','code'=>'admin','is_system'=>true]);
        $user = User::create(['name'=>'Admin','email'=>'quote-admin@example.test','password'=>'password123','is_active'=>true,'role_id'=>$role->id]);
        $customer = Customer::create(['code'=>'C-QT','name'=>'Quotation Customer','segment'=>'standard','status'=>'active']);
        $intermediary = Intermediary::create(['name'=>'Test Intermediary','is_active'=>true]);
        $product = Product::create([
            'code'=>'ERP-QT','name'=>'Quotation Module','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,
            'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>3,'default_maintenance_rate'=>15,'is_active'=>true,
        ]);

        $response = $this->actingAs($user)->post(route('quotations.store'), [
            'customer_id'=>$customer->id,'activity_type'=>'erp','quotation_date'=>today()->toDateString(),
            'valid_until'=>today()->addDays(15)->toDateString(),'currency'=>'SAR','billing_cycle'=>'one_time','status'=>'draft',
            'has_intermediary'=>1,'intermediary_id'=>$intermediary->id,'commission_type'=>'percentage','commission_value'=>10,
            'items'=>[[
                'product_id'=>$product->id,'unit_price'=>1000,'requested_users'=>0,'user_unit_price'=>0,
                'discount_value'=>100,'maintenance_rate'=>15,
            ]],
        ]);

        $quotation = SalesQuotation::firstOrFail();
        $response->assertRedirect(route('quotations.show',$quotation));
        $this->assertSame(900.0,(float)$quotation->net_total);
        $this->assertSame(90.0,(float)$quotation->commission_total);
        $this->assertSame(135.0,(float)$quotation->tax_total);
        $this->assertNotEmpty($quotation->pricing_snapshot);
    }


    public function test_create_form_cannot_bypass_status_workflow(): void
    {
        $role = Role::create(['name'=>'منشئ عروض','code'=>'sales-create','is_system'=>false]);
        $user = User::create(['name'=>'Sales Creator','email'=>'quote-status-guard@example.test','password'=>'password123','is_active'=>true,'role_id'=>$role->id]);
        $customer = Customer::create(['code'=>'C-QT-STATUS','name'=>'Status Guard Customer','segment'=>'standard','status'=>'active']);
        $product = Product::create(['code'=>'ERP-QT-STATUS','name'=>'Status Guard Module','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>3,'default_maintenance_rate'=>0,'is_active'=>true]);

        // Route permission is deliberately bypassed here by using admin-like role assignment in the test setup
        // only to verify the request itself never trusts a posted accepted status.
        $role->permissions()->attach(\App\Models\Permission::firstOrCreate(['code'=>'quotations.create'], ['name'=>'Create quotations','group_name'=>'quotations'])->id);

        $response = $this->actingAs($user)->post(route('quotations.store'), [
            'customer_id'=>$customer->id,'activity_type'=>'erp','quotation_date'=>today()->toDateString(),'currency'=>'SAR','billing_cycle'=>'one_time','status'=>'accepted',
            'items'=>[['product_id'=>$product->id,'unit_price'=>1000,'requested_users'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('draft', SalesQuotation::firstOrFail()->status);
    }

    public function test_quotation_without_intermediary_discards_commission_fields(): void
    {
        $role = Role::create(['name'=>'مدير النظام','code'=>'admin','is_system'=>true]);
        $user = User::create(['name'=>'Admin','email'=>'quote-no-intermediary@example.test','password'=>'password123','is_active'=>true,'role_id'=>$role->id]);
        $customer = Customer::create(['code'=>'C-QT2','name'=>'No Intermediary Customer','segment'=>'standard','status'=>'active']);
        $product = Product::create(['code'=>'ERP-QT2','name'=>'Module','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>3,'default_maintenance_rate'=>0,'is_active'=>true]);

        $response=$this->actingAs($user)->post(route('quotations.store'),[
            'customer_id'=>$customer->id,'activity_type'=>'erp','quotation_date'=>today()->toDateString(),'currency'=>'SAR','billing_cycle'=>'one_time','status'=>'draft',
            'has_intermediary'=>0,'commission_type'=>'percentage','commission_value'=>25,
            'items'=>[['product_id'=>$product->id,'unit_price'=>1000,'requested_users'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
        ]);

        $quotation=SalesQuotation::firstOrFail();
        $response->assertRedirect(route('quotations.show',$quotation));
        $this->assertNull($quotation->intermediary_id);
        $this->assertNull($quotation->commission_type);
        $this->assertSame(0.0,(float)$quotation->commission_total);
    }
    public function test_sent_quotation_content_is_locked_and_requires_new_version(): void
    {
        $role=Role::create(['name'=>'مدير النظام','code'=>'admin','is_system'=>true]);
        $user=User::create(['name'=>'Admin','email'=>'quote-version@example.test','password'=>'password123','is_active'=>true,'role_id'=>$role->id]);
        $customer=Customer::create(['code'=>'C-QT3','name'=>'Versioned Customer','segment'=>'standard','status'=>'active']);
        $product=Product::create(['code'=>'ERP-QT3','name'=>'Versioned Module','type'=>'erp_module','unit'=>'license','default_sale_price'=>1000,'billing_cycle'=>'one_time','supports_user_pricing'=>false,'included_users_one_time'=>3,'default_maintenance_rate'=>0,'is_active'=>true]);

        $quotation=app(\App\Services\QuotationService::class)->create([
            'customer_id'=>$customer->id,'activity_type'=>'erp','quotation_date'=>today()->toDateString(),'currency'=>'SAR','billing_cycle'=>'one_time','status'=>'draft',
            'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>1000,'requested_users'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
        ]);
        $quotation->update(['status'=>'sent','sent_at'=>now()]);

        $response=$this->actingAs($user)->put(route('quotations.update',$quotation),[
            'customer_id'=>$customer->id,'activity_type'=>'erp','quotation_date'=>today()->toDateString(),'currency'=>'SAR','billing_cycle'=>'one_time','status'=>'sent',
            'items'=>[['product_id'=>$product->id,'unit_price'=>2000,'requested_users'=>0,'user_unit_price'=>0,'discount_value'=>0,'maintenance_rate'=>0]],
        ]);

        $response->assertSessionHasErrors('quotation');
        $this->assertSame(1000.0,(float)$quotation->fresh()->net_total);
        $this->actingAs($user)->get(route('quotations.edit',$quotation))->assertRedirect(route('quotations.show',$quotation));
    }

}
