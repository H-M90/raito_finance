<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            DB::table('products')->where('supports_user_pricing', true)->update([
                'included_users_one_time' => 3,
            ]);
            DB::table('products')->where('supports_user_pricing', false)->update([
                'included_users_one_time' => 0,
                'extra_user_price_one_time' => 0,
                'user_price_monthly' => 0,
                'user_price_annual' => 0,
            ]);
            DB::table('products')->where('billing_cycle', '!=', 'one_time')->update([
                'default_maintenance_rate' => 0,
            ]);
        }

        if (Schema::hasTable('pricing_offers')) {
            DB::table('pricing_offers')->where('type', 'startup_discount')->update([
                'customer_segment' => 'startup',
                'billing_cycle' => 'all',
                'discount_percentage' => 50,
            ]);
        }
    }

    public function down(): void
    {
        // This migration normalizes current business rules. Historical prices remain preserved
        // in quotation/contract pricing snapshots, so the normalization is intentionally not reversed.
    }
};
