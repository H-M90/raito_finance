<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'required_product_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('required_product_id')->nullable()->after('supports_user_pricing')->constrained('products')->nullOnDelete();
            });
        }

        $inventoryId = DB::table('products')->where('code', 'ERP-INV')->value('id');
        if (! $inventoryId) return;

        $now = now();
        $delegate = DB::table('products')->where('code', 'APP-DELEGATES')->first();
        $payload = [
            'name' => 'تطبيق المناديب',
            'type' => 'application',
            'unit' => 'user',
            // التطبيق نفسه مجاني؛ التحصيل على عدد المناديب فقط.
            'default_sale_price' => 0,
            'billing_cycle' => 'one_time',
            'supports_user_pricing' => true,
            'required_product_id' => $inventoryId,
            'included_users_one_time' => 0,
            'extra_user_price_one_time' => 2400,
            'user_price_monthly' => 200,
            'user_price_annual' => 2000,
            'default_maintenance_rate' => 0,
            'is_active' => true,
            'description' => 'تطبيق مرتبط بالمخزون. لا توجد قيمة أساسية للتطبيق؛ يتم التسعير حسب عدد المناديب.',
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($delegate) {
            DB::table('products')->where('id', $delegate->id)->update($payload);
        } else {
            DB::table('products')->insert(array_merge($payload, [
                'code' => 'APP-DELEGATES',
                'created_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'required_product_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('required_product_id');
            });
        }
    }
};
