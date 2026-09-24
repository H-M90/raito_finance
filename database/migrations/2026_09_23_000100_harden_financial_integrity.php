<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('collection_follow_ups')) {
            Schema::create('collection_follow_ups', function (Blueprint $table) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->date('start_date')->index();
                $table->date('end_date')->nullable()->index();
                $table->string('currency', 3)->default('SAR')->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('collector_name')->nullable();
                $table->string('follow_up_status')->nullable()->index();
                $table->string('claim_status')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id', 'start_date']);
            });
        }

        if (Schema::hasTable('timeline_events') && ! Schema::hasColumn('timeline_events', 'created_by')) {
            Schema::table('timeline_events', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('url')->constrained('users')->nullOnDelete()->index();
            });
        }

        // Defensive schema repair for databases created from the older SQL dump.
        if (Schema::hasTable('receivables') && ! Schema::hasColumn('receivables', 'created_by')) {
            Schema::table('receivables', fn (Blueprint $table) => $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->index());
        }
        if (Schema::hasTable('customer_ledger_entries') && ! Schema::hasColumn('customer_ledger_entries', 'created_by')) {
            Schema::table('customer_ledger_entries', fn (Blueprint $table) => $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->index());
        }

        // Move legacy non-financial collection follow-ups out of the cash table.
        if (Schema::hasTable('collections') && Schema::hasTable('collection_follow_ups')) {
            DB::table('collections')
                ->where('status', 'follow_up')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('collection_allocations')->whereColumn('collection_allocations.collection_id', 'collections.id'))
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('collection_follow_ups')->updateOrInsert(
                            ['number' => 'CFU-LEGACY-'.$row->id],
                            [
                                'customer_id' => $row->customer_id,
                                'start_date' => $row->collection_date,
                                'end_date' => null,
                                'currency' => $row->currency,
                                'amount' => $row->amount,
                                'collector_name' => $row->collector_name,
                                'follow_up_status' => 'legacy_import',
                                'claim_status' => null,
                                'notes' => $row->notes,
                                'created_by' => $row->created_by,
                                'updated_by' => $row->updated_by,
                                'created_at' => $row->created_at,
                                'updated_at' => $row->updated_at,
                            ]
                        );
                    }
                }, 'id');
            DB::table('collections')
                ->where('status', 'follow_up')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('collection_allocations')->whereColumn('collection_allocations.collection_id', 'collections.id'))
                ->delete();
        }

        DB::table('receivables')
            ->where('type', 'legacy_import_due')
            ->where('total_amount', 0)
            ->where('collected_amount', 0)
            ->where('discounted_amount', 0)
            ->update(['status' => 'needs_review', 'remaining_amount' => 0]);

        // A source object must generate at most one receivable. NULL source values remain unrestricted.
        if (Schema::hasTable('receivables')) {
            $duplicates = DB::table('receivables')
                ->select('source_type', 'source_id')
                ->whereNotNull('source_type')->whereNotNull('source_id')
                ->groupBy('source_type', 'source_id')->havingRaw('COUNT(*) > 1')->exists();
            if (! $duplicates) {
                try {
                    Schema::table('receivables', fn (Blueprint $table) => $table->unique(['source_type', 'source_id'], 'receivables_source_unique'));
                } catch (Throwable) {
                    // Index already exists on an upgraded database.
                }
            }
        }

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                "ALTER TABLE collections ADD CONSTRAINT chk_collections_amounts CHECK (amount >= 0 AND allocated_amount >= 0 AND unallocated_amount >= 0 AND ROUND(allocated_amount + unallocated_amount,2) = ROUND(amount,2))",
                "ALTER TABLE collection_allocations ADD CONSTRAINT chk_collection_allocation_amount CHECK (amount > 0)",
                "ALTER TABLE receivables ADD CONSTRAINT chk_receivable_amounts CHECK (net_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND collected_amount >= 0 AND discounted_amount >= 0 AND remaining_amount >= 0)",
            ] as $sql) {
                try { DB::statement($sql); } catch (Throwable) {}
            }
        }
    }

    public function down(): void
    {
        // This migration is an integrity/data repair. Destructive rollback is intentionally limited.
        if (Schema::hasTable('collection_follow_ups')) Schema::dropIfExists('collection_follow_ups');
    }
};
