<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 50)->unique();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 100)->unique();
                $table->string('group_name', 80)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->primary(['role_id','permission_id']);
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('email')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event', 30)->index();
                $table->nullableMorphs('auditable');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        foreach (['sales_quotation_items','contract_items','contract_addendum_items'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'total_licensed_users')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedInteger('total_licensed_users')->default(0)->after('billable_users');
                });
            }
        }

        $this->addColumnIfMissing('sales_quotations', 'pricing_snapshot', fn (Blueprint $table) => $table->json('pricing_snapshot')->nullable()->after('maintenance_total'));
        $this->addColumnIfMissing('sales_quotations', 'converted_at', fn (Blueprint $table) => $table->timestamp('converted_at')->nullable()->after('sent_at'));
        $this->addColumnIfMissing('sales_quotations', 'converted_by', fn (Blueprint $table) => $table->foreignId('converted_by')->nullable()->after('converted_at')->constrained('users')->nullOnDelete());
        $this->addColumnIfMissing('sales_quotations', 'updated_by', fn (Blueprint $table) => $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());

        $this->addColumnIfMissing('contracts', 'pricing_snapshot', fn (Blueprint $table) => $table->json('pricing_snapshot')->nullable()->after('maintenance_total'));
        $this->addColumnIfMissing('contracts', 'updated_by', fn (Blueprint $table) => $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());
        $this->addColumnIfMissing('contracts', 'cancelled_at', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable());
        $this->addColumnIfMissing('contracts', 'cancelled_by', fn (Blueprint $table) => $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete());
        $this->addColumnIfMissing('contracts', 'cancellation_reason', fn (Blueprint $table) => $table->text('cancellation_reason')->nullable());
        $this->addColumnIfMissing('contracts', 'reopened_at', fn (Blueprint $table) => $table->timestamp('reopened_at')->nullable());
        $this->addColumnIfMissing('contracts', 'reopened_by', fn (Blueprint $table) => $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete());
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'sales_quotation_id') && ! $this->hasIndex('contracts', 'contracts_one_quotation_unique')) {
            Schema::table('contracts', fn (Blueprint $table) => $table->unique('sales_quotation_id', 'contracts_one_quotation_unique'));
        }
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'status')) {
            DB::table('contracts')->where('status', '!=', 'cancelled')->update(['status' => 'active']);
        }

        foreach (['contract_addendums','collections','installations'] as $tableName) {
            $this->addColumnIfMissing($tableName, 'updated_by', fn (Blueprint $table) => $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());
            $this->addColumnIfMissing($tableName, 'cancelled_at', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable());
            $this->addColumnIfMissing($tableName, 'cancelled_by', fn (Blueprint $table) => $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete());
            $this->addColumnIfMissing($tableName, 'cancellation_reason', fn (Blueprint $table) => $table->text('cancellation_reason')->nullable());
            $this->addColumnIfMissing($tableName, 'reopened_at', fn (Blueprint $table) => $table->timestamp('reopened_at')->nullable());
            $this->addColumnIfMissing($tableName, 'reopened_by', fn (Blueprint $table) => $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete());
        }
        $this->addColumnIfMissing('contract_addendums', 'pricing_snapshot', fn (Blueprint $table) => $table->json('pricing_snapshot')->nullable()->after('maintenance_total'));

        $this->addColumnIfMissing('receivables', 'discounted_amount', fn (Blueprint $table) => $table->decimal('discounted_amount', 15, 2)->default(0)->after('collected_amount'));

        if (! Schema::hasTable('discount_vouchers')) {
            Schema::create('discount_vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('receivable_id')->constrained()->restrictOnDelete();
                $table->date('voucher_date')->index();
                $table->string('currency', 3)->default('SAR')->index();
                $table->decimal('amount', 15, 2);
                $table->string('reason');
                $table->string('status', 20)->default('active')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['customer_id','currency','voucher_date']);
            });
        }

        $this->addColumnIfMissing('purchases', 'updated_by', fn (Blueprint $table) => $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());
        $this->addColumnIfMissing('expenses', 'updated_by', fn (Blueprint $table) => $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());

        if (! Schema::hasTable('intermediary_commissions')) {
            Schema::create('intermediary_commissions', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('intermediary_id')->constrained()->restrictOnDelete();
                $table->foreignId('contract_id')->constrained()->restrictOnDelete();
                $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('collection_allocation_id')->nullable()->unique()->constrained()->nullOnDelete();
                $table->date('due_date')->index();
                $table->string('currency', 3)->default('SAR')->index();
                $table->string('basis', 20)->default('collection');
                $table->decimal('base_amount', 15, 2)->default(0);
                $table->string('commission_type', 20);
                $table->decimal('commission_value', 15, 4)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);
                $table->string('status', 20)->default('outstanding')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['intermediary_id','status','due_date']);
            });
        }

        if (! Schema::hasTable('intermediary_commission_payments')) {
            Schema::create('intermediary_commission_payments', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('intermediary_commission_id')->constrained()->cascadeOnDelete();
                $table->date('payment_date')->index();
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 30)->nullable();
                $table->string('reference_no')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('timeline_events')) {
            Schema::create('timeline_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
                $table->dateTime('event_at')->index();
                $table->string('event_type', 40)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->nullableMorphs('source');
                $table->string('url')->nullable();
                $table->timestamps();
                $table->index(['customer_id','event_at','id'], 'timeline_customer_event_idx');
            });
        }

        $this->addColumnIfMissing('import_batches', 'processed_rows', fn (Blueprint $table) => $table->unsignedInteger('processed_rows')->default(0)->after('total_rows'));
        $this->addColumnIfMissing('import_batches', 'progress_percentage', fn (Blueprint $table) => $table->unsignedTinyInteger('progress_percentage')->default(0)->after('processed_rows'));
        $this->addColumnIfMissing('import_batches', 'error_file_path', fn (Blueprint $table) => $table->string('error_file_path')->nullable()->after('stored_path'));
        $this->addColumnIfMissing('import_batches', 'failure_message', fn (Blueprint $table) => $table->text('failure_message')->nullable());

        if (Schema::hasTable('import_rows')) {
            // MySQL may use the old composite UNIQUE index to satisfy the
            // foreign key on import_batch_id. Create a dedicated supporting
            // index first so the UNIQUE index can be safely replaced.
            if ($this->hasIndex('import_rows', 'import_rows_import_batch_id_row_number_unique')) {
                if (! $this->hasIndex('import_rows', 'import_rows_import_batch_id_index')) {
                    Schema::table('import_rows', fn (Blueprint $table) => $table->index('import_batch_id', 'import_rows_import_batch_id_index'));
                }

                Schema::table('import_rows', fn (Blueprint $table) => $table->dropUnique('import_rows_import_batch_id_row_number_unique'));
            }
            $this->addColumnIfMissing('import_rows', 'sheet_name', fn (Blueprint $table) => $table->string('sheet_name', 80)->nullable()->after('row_number'));
            $this->addColumnIfMissing('import_rows', 'row_type', fn (Blueprint $table) => $table->string('row_type', 40)->default('contract_item')->after('sheet_name')->index());
            $this->addColumnIfMissing('import_rows', 'imported_model_type', fn (Blueprint $table) => $table->string('imported_model_type')->nullable()->after('errors'));
            $this->addColumnIfMissing('import_rows', 'imported_model_id', fn (Blueprint $table) => $table->unsignedBigInteger('imported_model_id')->nullable()->after('imported_model_type'));
            if (! $this->hasIndex('import_rows', 'import_rows_imported_model_idx')) {
                Schema::table('import_rows', fn (Blueprint $table) => $table->index(['imported_model_type','imported_model_id'],'import_rows_imported_model_idx'));
            }
            if (! $this->hasIndex('import_rows', 'import_rows_batch_sheet_row_unique')) {
                Schema::table('import_rows', fn (Blueprint $table) => $table->unique(['import_batch_id','sheet_name','row_number'],'import_rows_batch_sheet_row_unique'));
            }
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    private function addColumnIfMissing(string $tableName, string $columnName, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        try {
            return collect(Schema::getIndexes($tableName))
                ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
        } catch (\Throwable) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('intermediary_commission_payments');
        Schema::dropIfExists('intermediary_commissions');
        Schema::dropIfExists('discount_vouchers');
        Schema::dropIfExists('audit_logs');
    }
};
