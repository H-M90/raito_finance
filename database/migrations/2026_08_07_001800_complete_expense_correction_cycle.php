<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('expenses')) return;

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'updated_by')) $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('expenses', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable()->after('updated_by');
            if (! Schema::hasColumn('expenses', 'cancelled_by')) $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('expenses', 'cancellation_reason')) $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            if (! Schema::hasColumn('expenses', 'reopened_at')) $table->timestamp('reopened_at')->nullable()->after('cancellation_reason');
            if (! Schema::hasColumn('expenses', 'reopened_by')) $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses')) return;
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['reopened_by','cancelled_by','updated_by'] as $column) {
                if (Schema::hasColumn('expenses', $column)) $table->dropConstrainedForeignId($column);
            }
            foreach (['reopened_at','cancellation_reason','cancelled_at'] as $column) {
                if (Schema::hasColumn('expenses', $column)) $table->dropColumn($column);
            }
        });
    }
};
