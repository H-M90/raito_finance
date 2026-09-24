<?php

namespace Tests\Unit;

use App\Models\PricingOffer;
use App\Models\Product;
use App\Services\PricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    private function userProduct(): Product
    {
        return new Product([
            'type'=>'erp_module',
            'supports_user_pricing'=>true,
            'included_users_one_time'=>3,
            'extra_user_price_one_time'=>2000,
            'user_price_monthly'=>200,
            'user_price_annual'=>2000,
        ]);
    }

    private function freeUserOffer(): PricingOffer
    {
        return new PricingOffer([
            'type'=>'free_users','minimum_users'=>4,'free_users'=>1,
            'repeat_for_each_threshold'=>true,'is_stackable'=>true,'priority'=>50,
        ]);
    }

    public function test_one_time_license_keeps_three_included_users_and_adds_offer_user_on_top_of_four_paid_users(): void
    {
        $startup = new PricingOffer([
            'type'=>'startup_discount','discount_percentage'=>50,'is_stackable'=>false,'priority'=>10,
        ]);

        $result = (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$this->userProduct(),'quantity'=>1,'unit_price'=>15000,'requested_users'=>4,'user_unit_price'=>2000,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect([$startup,$this->freeUserOffer()]));

        $line = $result['calculatedItems'][0];
        $this->assertSame(3, $line['included_users']);
        $this->assertSame(1, $line['promotional_free_users']);
        $this->assertSame(4, $line['billable_users']);
        $this->assertSame(8, $line['total_licensed_users']);
        $this->assertSame(8000.0, $line['user_total']);
        $this->assertSame(23000.0, $result['subtotal']);
        $this->assertSame(11500.0, $result['promotionalDiscountTotal']);
        $this->assertSame(11500.0, $result['netTotal']);
        $this->assertSame(1725.0, $result['taxTotal']);
        $this->assertSame(1725.0, $result['maintenanceTotal']);
        $this->assertSame(13225.0, $result['grandTotal']);
    }

    public function test_module_has_no_quantity_and_keeps_three_included_users(): void
    {
        $result = (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$this->userProduct(),'quantity'=>99,'unit_price'=>15000,'requested_users'=>8,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect());

        $line = $result['calculatedItems'][0];
        $this->assertSame(1, $line['quantity']);
        $this->assertSame(3, $line['included_users']);
        $this->assertSame(8, $line['billable_users']);
        $this->assertSame(11, $line['total_licensed_users']);
        $this->assertSame(16000.0, $line['user_total']);
        $this->assertSame(31000.0, $result['subtotal']);
    }

    public function test_monthly_offer_grants_free_users_on_top_of_paid_users_and_has_no_maintenance(): void
    {
        $seasonal = new PricingOffer([
            'type'=>'seasonal_discount','discount_percentage'=>30,'is_stackable'=>false,'priority'=>30,
        ]);

        $result = (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$this->userProduct(),'quantity'=>1,'unit_price'=>0,'requested_users'=>8,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'monthly', collect([$this->freeUserOffer(),$seasonal]));

        $line = $result['calculatedItems'][0];
        $this->assertSame(0, $line['included_users']);
        $this->assertSame(2, $line['promotional_free_users']);
        $this->assertSame(8, $line['billable_users']);
        $this->assertSame(10, $line['total_licensed_users']);
        $this->assertSame(1600.0, $result['subtotal']);
        $this->assertSame(480.0, $result['promotionalDiscountTotal']);
        $this->assertSame(1120.0, $result['netTotal']);
        $this->assertSame(168.0, $result['taxTotal']);
        $this->assertSame(0.0, $result['maintenanceTotal']);
    }

    public function test_delegate_app_has_no_base_price_or_maintenance_and_charges_every_delegate(): void
    {
        $delegateApp = new Product([
            'code'=>'APP-DELEGATES',
            'type'=>'application',
            'supports_user_pricing'=>true,
            'included_users_one_time'=>0,
            'extra_user_price_one_time'=>2400,
            'user_price_monthly'=>200,
            'user_price_annual'=>2000,
        ]);

        $result = (new PricingCalculator)->calculate([
            ['product_id'=>7,'product'=>$delegateApp,'quantity'=>9,'unit_price'=>9999,'requested_users'=>5,'user_unit_price'=>2400,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect());

        $line = $result['calculatedItems'][0];
        $this->assertSame(1, $line['quantity']);
        $this->assertSame(0, $line['included_users']);
        $this->assertSame(5, $line['billable_users']);
        $this->assertSame(12000.0, $line['user_total']);
        $this->assertSame(12000.0, $line['line_subtotal']);
        $this->assertSame(0.0, $line['maintenance_annual']);
        $this->assertSame(12000.0, $result['netTotal']);
        $this->assertSame(0.0, $result['maintenanceTotal']);
    }

    public function test_delegate_app_uses_monthly_per_delegate_price_without_base_price(): void
    {
        $delegateApp = new Product([
            'code'=>'APP-DELEGATES',
            'type'=>'application',
            'supports_user_pricing'=>true,
            'included_users_one_time'=>0,
            'extra_user_price_one_time'=>2400,
            'user_price_monthly'=>200,
            'user_price_annual'=>2000,
        ]);

        $result = (new PricingCalculator)->calculate([
            ['product_id'=>7,'product'=>$delegateApp,'quantity'=>1,'unit_price'=>5000,'requested_users'=>4,'discount_value'=>0,'maintenance_rate'=>0],
        ], 15, 'monthly', collect());

        $this->assertSame(800.0, $result['subtotal']);
        $this->assertSame(800.0, $result['netTotal']);
    }

    public function test_it_rejects_duplicate_products(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$this->userProduct(),'quantity'=>1,'unit_price'=>1000],
            ['product_id'=>1,'product'=>$this->userProduct(),'quantity'=>1,'unit_price'=>1000],
        ], 15);
    }

    public function test_it_rejects_fractional_device_quantity(): void
    {
        $device = new Product(['type'=>'pts','supports_user_pricing'=>false]);
        $this->expectException(InvalidArgumentException::class);
        (new PricingCalculator)->calculate([
            ['product_id'=>2,'product'=>$device,'quantity'=>1.5,'unit_price'=>1000],
        ], 15);
    }

    public function test_fixed_bundle_discount_applies_once_when_all_required_products_exist_and_reduces_maintenance(): void
    {
        $inventory = (new Product(['name'=>'المخزون','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>1]);
        $manufacturing = (new Product(['name'=>'الإنتاج والتصنيع','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>2]);
        $bundle = new PricingOffer([
            'name'=>'باقة المخزون + التصنيع',
            'type'=>'bundle_fixed_discount',
            'fixed_discount_amount'=>5000,
            'is_stackable'=>false,
            'priority'=>20,
        ]);
        $bundle->setRelation('products', collect([$inventory, $manufacturing]));

        $result = (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$inventory,'quantity'=>1,'unit_price'=>15000,'discount_value'=>0,'maintenance_rate'=>15],
            ['product_id'=>2,'product'=>$manufacturing,'quantity'=>1,'unit_price'=>15000,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect([$bundle]));

        $this->assertSame(30000.0, $result['subtotal']);
        $this->assertSame(5000.0, $result['promotionalDiscountTotal']);
        $this->assertSame(25000.0, $result['netTotal']);
        $this->assertSame(3750.0, $result['maintenanceTotal']);
        $this->assertSame(28750.0, $result['grandTotal']);
        $this->assertSame(2500.0, $result['calculatedItems'][0]['promotional_discount_value']);
        $this->assertSame(2500.0, $result['calculatedItems'][1]['promotional_discount_value']);
    }

    public function test_fixed_bundle_discount_is_rejected_when_a_required_product_is_missing(): void
    {
        $inventory = (new Product(['name'=>'المخزون','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>1]);
        $manufacturing = (new Product(['name'=>'الإنتاج والتصنيع','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>2]);
        $bundle = new PricingOffer([
            'name'=>'باقة المخزون + التصنيع',
            'type'=>'bundle_fixed_discount',
            'fixed_discount_amount'=>5000,
            'is_stackable'=>false,
            'priority'=>20,
        ]);
        $bundle->setRelation('products', collect([$inventory, $manufacturing]));

        $this->expectException(InvalidArgumentException::class);
        (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$inventory,'quantity'=>1,'unit_price'=>15000,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect([$bundle]));
    }

    public function test_non_stackable_bundle_cannot_be_combined_with_another_monetary_discount(): void
    {
        $inventory = (new Product(['name'=>'المخزون','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>1]);
        $manufacturing = (new Product(['name'=>'الإنتاج والتصنيع','type'=>'erp_module','supports_user_pricing'=>false]))->forceFill(['id'=>2]);
        $bundle = new PricingOffer([
            'name'=>'باقة المخزون + التصنيع',
            'type'=>'bundle_fixed_discount',
            'fixed_discount_amount'=>5000,
            'is_stackable'=>false,
            'priority'=>20,
        ]);
        $bundle->setRelation('products', collect([$inventory, $manufacturing]));
        $seasonal = new PricingOffer([
            'name'=>'عرض موسمي',
            'type'=>'seasonal_discount',
            'discount_percentage'=>30,
            'is_stackable'=>true,
            'priority'=>30,
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new PricingCalculator)->calculate([
            ['product_id'=>1,'product'=>$inventory,'quantity'=>1,'unit_price'=>15000,'discount_value'=>0,'maintenance_rate'=>15],
            ['product_id'=>2,'product'=>$manufacturing,'quantity'=>1,'unit_price'=>15000,'discount_value'=>0,'maintenance_rate'=>15],
        ], 15, 'one_time', collect([$bundle, $seasonal]));
    }

}
