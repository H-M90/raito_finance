<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_addendum_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('type', 30)->index();
            $table->string('name');
            $table->date('due_date')->index();
            $table->string('currency', 3)->default('SAR')->index();
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('collected_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('future')->index();
            $table->boolean('is_opening_balance')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'currency', 'due_date']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('collection_date')->index();
            $table->string('currency', 3)->default('SAR')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('unallocated_amount', 15, 2)->default(0);
            $table->string('payment_method', 30);
            $table->string('reference_no')->nullable()->index();
            $table->string('attachment')->nullable();
            $table->string('collector_name')->nullable();
            $table->string('status', 20)->default('confirmed')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'collection_date']);
        });

        Schema::create('collection_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['collection_id', 'receivable_id']);
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date')->index();
            $table->string('currency', 3)->default('SAR')->index();
            $table->string('entry_type', 30)->index();
            $table->nullableMorphs('source');
            $table->string('reference_no', 40)->nullable();
            $table->string('description');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->boolean('is_reversed')->default(false)->index();
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
            $table->index(['customer_id', 'currency', 'entry_date', 'id'], 'ledger_statement_idx');
            $table->unique(['source_type', 'source_id', 'entry_type'], 'ledger_source_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
        Schema::dropIfExists('collection_allocations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('receivables');
    }
};
