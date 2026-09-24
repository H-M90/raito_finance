<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\PricingOffer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, CustomerSuccessSeeder::class]);
        $erpUserPricing = [
            'supports_user_pricing' => true,
            'included_users_one_time' => 3,
            'extra_user_price_one_time' => 2000,
            'user_price_monthly' => 200,
            'user_price_annual' => 2000,
        ];

        $products = [
            array_merge(['code'=>'ERP-ACC','name'=>'موديول الحسابات','type'=>'erp_module','unit'=>'license','default_sale_price'=>15000,'billing_cycle'=>'one_time','default_maintenance_rate'=>15], $erpUserPricing),
            array_merge(['code'=>'ERP-INV','name'=>'موديول المخزون','type'=>'erp_module','unit'=>'license','default_sale_price'=>15000,'billing_cycle'=>'one_time','default_maintenance_rate'=>15], $erpUserPricing),
            array_merge(['code'=>'ERP-SALES','name'=>'موديول المبيعات','type'=>'erp_module','unit'=>'license','default_sale_price'=>15000,'billing_cycle'=>'one_time','default_maintenance_rate'=>15], $erpUserPricing),
            ['code'=>'PTS','name'=>'جهاز PTS','type'=>'pts','unit'=>'device','default_sale_price'=>0,'billing_cycle'=>'one_time','default_maintenance_rate'=>0,'supports_user_pricing'=>false],
            ['code'=>'SENSOR','name'=>'حساس خزان','type'=>'sensor','unit'=>'sensor','default_sale_price'=>0,'billing_cycle'=>'one_time','default_maintenance_rate'=>0,'supports_user_pricing'=>false],
            ['code'=>'INSTALL','name'=>'التركيب والتشغيل','type'=>'installation','unit'=>'service','default_sale_price'=>0,'billing_cycle'=>'one_time','default_maintenance_rate'=>0,'supports_user_pricing'=>false],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code'=>$product['code']], array_merge([
                'included_users_one_time'=>3,
                'extra_user_price_one_time'=>0,
                'user_price_monthly'=>0,
                'user_price_annual'=>0,
                'is_active'=>true,
            ], $product));
        }

        $inventory = Product::where('code', 'ERP-INV')->first();
        if ($inventory) {
            Product::updateOrCreate(['code'=>'APP-DELEGATES'], [
                'name'=>'تطبيق المناديب',
                'type'=>'application',
                'unit'=>'user',
                'default_sale_price'=>0,
                'billing_cycle'=>'one_time',
                'supports_user_pricing'=>true,
                'required_product_id'=>$inventory->id,
                'included_users_one_time'=>0,
                'extra_user_price_one_time'=>2400,
                'user_price_monthly'=>200,
                'user_price_annual'=>2000,
                'default_maintenance_rate'=>0,
                'is_active'=>true,
                'description'=>'تطبيق مرتبط بالمخزون. لا توجد قيمة أساسية للتطبيق؛ يتم التسعير حسب عدد المناديب.',
            ]);
        }

        PricingOffer::updateOrCreate(['code'=>'STARTUP50'], [
            'name'=>'خصم الشركات الناشئة 50%',
            'type'=>'startup_discount',
            'customer_segment'=>'startup',
            'billing_cycle'=>'all',
            'discount_percentage'=>50,
            'minimum_users'=>0,
            'free_users'=>0,
            'repeat_for_each_threshold'=>false,
            'is_stackable'=>false,
            'is_active'=>true,
            'priority'=>10,
            'notes'=>'يُطبّق تلقائيًا على العملاء المصنفين كشركات ناشئة، ولا يجتمع مع خصم موسمي آخر.',
        ]);

        PricingOffer::updateOrCreate(['code'=>'USERS4PLUS1'], [
            'name'=>'كل 4 مستخدمين + مستخدم مجاني',
            'type'=>'free_users',
            'customer_segment'=>'all',
            'billing_cycle'=>'all',
            'discount_percentage'=>0,
            'minimum_users'=>4,
            'free_users'=>1,
            'repeat_for_each_threshold'=>true,
            'is_stackable'=>true,
            'is_active'=>true,
            'priority'=>50,
            'notes'=>'قيمة ابتدائية قابلة للتعديل أو الإيقاف من شاشة عروض التسعير، وتعمل مع الشهري والسنوي والرخصة الدائمة.',
        ]);

        PricingOffer::updateOrCreate(['code'=>'SEASONAL30'], [
            'name'=>'خصم موسمي 30%',
            'type'=>'seasonal_discount',
            'customer_segment'=>'all',
            'billing_cycle'=>'all',
            'discount_percentage'=>30,
            'minimum_users'=>0,
            'free_users'=>0,
            'repeat_for_each_threshold'=>false,
            'is_stackable'=>false,
            'is_active'=>false,
            'priority'=>30,
            'notes'=>'نموذج جاهز: حدد فترة البداية والنهاية وفعّله وقت الموسم أو غيّر النسبة.',
        ]);

        foreach (['تركيب','سفر','إقامة','شحن','تسويق','عمولة وسيط','استضافة','تطوير وتخصيص','مصروف عام','أخرى'] as $name) {
            ExpenseCategory::firstOrCreate(['name'=>$name],['is_active'=>true]);
        }
    }
}
