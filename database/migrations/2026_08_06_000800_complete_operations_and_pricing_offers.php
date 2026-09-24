<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('segment', 20)->default('standard')->after('sales_owner_id')->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('supports_user_pricing')->default(false)->after('billing_cycle')->index();
            $table->unsignedInteger('included_users_one_time')->default(3)->after('supports_user_pricing');
            $table->decimal('extra_user_price_one_time', 15, 2)->default(0)->after('included_users_one_time');
            $table->decimal('user_price_monthly', 15, 2)->default(0)->after('extra_user_price_one_time');
            $table->decimal('user_price_annual', 15, 2)->default(0)->after('user_price_monthly');
        });

        Schema::create('pricing_offers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('type', 30)->index(); // startup_discount, seasonal_discount, free_users
            $table->string('customer_segment', 20)->default('all')->index();
            $table->string('billing_cycle', 20)->default('all')->index();
            $table->decimal('discount_percentage', 7, 4)->default(0);
            $table->unsignedInteger('minimum_users')->default(0);
            $table->unsignedInteger('free_users')->default(0);
            $table->boolean('repeat_for_each_threshold')->default(false);
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->boolean('is_stackable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('pricing_offer_product', function (Blueprint $table) {
            $table->foreignId('pricing_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['pricing_offer_id', 'product_id']);
        });

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->decimal('promotional_discount_total', 15, 2)->default(0)->after('discount_total');
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('promotional_discount_total', 15, 2)->default(0)->after('discount_total');
        });
        Schema::table('contract_addendums', function (Blueprint $table) {
            $table->decimal('promotional_discount_total', 15, 2)->default(0)->after('discount_total');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        foreach (['sales_quotation_items', 'contract_items', 'contract_addendum_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('requested_users')->default(0)->after('quantity');
                $table->unsignedInteger('included_users')->default(0)->after('requested_users');
                $table->unsignedInteger('promotional_free_users')->default(0)->after('included_users');
                $table->unsignedInteger('billable_users')->default(0)->after('promotional_free_users');
                $table->decimal('user_unit_price', 15, 2)->default(0)->after('unit_price');
                $table->decimal('user_total', 15, 2)->default(0)->after('user_unit_price');
                $table->decimal('promotional_discount_value', 15, 2)->default(0)->after('discount_value');
            });
        }

        Schema::create('sales_quotation_pricing_offer', function (Blueprint $table) {
            $table->foreignId('sales_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_offer_id')->constrained()->restrictOnDelete();
            $table->primary(['sales_quotation_id', 'pricing_offer_id'], 'quotation_offer_primary');
        });
        Schema::create('contract_pricing_offer', function (Blueprint $table) {
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_offer_id')->constrained()->restrictOnDelete();
            $table->primary(['contract_id', 'pricing_offer_id']);
        });
        Schema::create('contract_addendum_pricing_offer', function (Blueprint $table) {
            $table->foreignId('contract_addendum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_offer_id')->constrained()->restrictOnDelete();
            $table->primary(['contract_addendum_id', 'pricing_offer_id'], 'addendum_offer_primary');
        });

        Schema::create('contract_addendum_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_addendum_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('percentage', 7, 4)->nullable();
            $table->date('due_date')->index();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('status', 20)->default('confirmed')->after('total_amount')->index();
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
        Schema::table('installations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('installations', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('status');
        });
        Schema::dropIfExists('contract_addendum_installments');
        Schema::dropIfExists('contract_addendum_pricing_offer');
        Schema::dropIfExists('contract_pricing_offer');
        Schema::dropIfExists('sales_quotation_pricing_offer');

        foreach (['sales_quotation_items', 'contract_items', 'contract_addendum_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['requested_users','included_users','promotional_free_users','billable_users','user_unit_price','user_total','promotional_discount_value']);
            });
        }

        Schema::table('contract_addendums', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('promotional_discount_total');
        });
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('promotional_discount_total'));
        Schema::table('sales_quotations', fn (Blueprint $table) => $table->dropColumn('promotional_discount_total'));
        Schema::dropIfExists('pricing_offer_product');
        Schema::dropIfExists('pricing_offers');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['supports_user_pricing','included_users_one_time','extra_user_price_one_time','user_price_monthly','user_price_annual']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('segment'));
    }
};
