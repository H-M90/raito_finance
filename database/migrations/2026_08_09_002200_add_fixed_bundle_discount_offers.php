<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pricing_offers', 'fixed_discount_amount')) {
            Schema::table('pricing_offers', function (Blueprint $table) {
                $table->decimal('fixed_discount_amount', 15, 2)->default(0)->after('discount_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pricing_offers', 'fixed_discount_amount')) {
            Schema::table('pricing_offers', function (Blueprint $table) {
                $table->dropColumn('fixed_discount_amount');
            });
        }
    }
};
