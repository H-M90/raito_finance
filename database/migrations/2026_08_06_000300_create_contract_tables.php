<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type', 20)->index();
            $table->date('contract_date')->index();
            $table->date('service_start_date')->index();
            $table->string('billing_cycle', 20)->default('one_time')->index();
            $table->string('currency', 3)->default('SAR')->index();
            $table->decimal('tax_rate', 7, 4)->default(15);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('maintenance_total', 15, 2)->default(0);
            $table->unsignedInteger('pts_count')->default(0);
            $table->unsignedInteger('sensor_count')->default(0);
            $table->foreignId('sales_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('intermediary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_value', 15, 4)->default(0);
            $table->decimal('commission_total', 15, 2)->default(0);
            $table->string('commission_due_basis', 20)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->date('calculation_start_date')->nullable()->index();
            $table->date('maintenance_paid_until')->nullable();
            $table->date('next_billing_date')->nullable()->index();
            $table->date('next_maintenance_date')->nullable()->index();
            $table->decimal('opening_receivable_balance', 15, 2)->default(0);
            $table->decimal('opening_maintenance_balance', 15, 2)->default(0);
            $table->decimal('previous_collections_total', 15, 2)->default(0);
            $table->boolean('is_imported')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'status']);
            $table->index(['billing_cycle', 'status', 'next_billing_date']);
            $table->index(['status', 'next_maintenance_date']);
        });

        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
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
        });

        Schema::create('contract_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('percentage', 7, 4)->nullable();
            $table->date('due_date')->index();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'due_date']);
        });

        Schema::create('contract_addendums', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->date('addendum_date');
            $table->date('service_start_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('maintenance_total', 15, 2)->default(0);
            $table->unsignedInteger('pts_count')->default(0);
            $table->unsignedInteger('sensor_count')->default(0);
            $table->date('next_maintenance_date')->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_addendum_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_addendum_id')->constrained()->cascadeOnDelete();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_addendum_items');
        Schema::dropIfExists('contract_addendums');
        Schema::dropIfExists('contract_installments');
        Schema::dropIfExists('contract_items');
        Schema::dropIfExists('contracts');
    }
};
