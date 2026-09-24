<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_quotation_id')->nullable()->constrained('sales_quotations')->nullOnDelete();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->boolean('is_current_version')->default(true)->index();
            $table->string('activity_type', 20)->index();
            $table->date('quotation_date')->index();
            $table->date('valid_until')->nullable()->index();
            $table->string('currency', 3)->default('SAR');
            $table->foreignId('sales_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lead_source', 40)->nullable()->index();
            $table->foreignId('intermediary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_value', 15, 4)->default(0);
            $table->decimal('commission_total', 15, 2)->default(0);
            $table->string('billing_cycle', 20)->default('one_time');
            $table->unsignedInteger('station_count')->default(0);
            $table->unsignedInteger('pts_count')->default(0);
            $table->unsignedInteger('sensor_count')->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(15);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('maintenance_total', 15, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->text('payment_terms')->nullable();
            $table->string('expected_execution_period')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('lost_to_competitor')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'status']);
            $table->index(['quotation_date', 'status']);
        });

        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('line_net', 15, 2)->default(0);
            $table->decimal('maintenance_rate', 7, 4)->default(0);
            $table->decimal('maintenance_annual', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['sales_quotation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_items');
        Schema::dropIfExists('sales_quotations');
    }
};
