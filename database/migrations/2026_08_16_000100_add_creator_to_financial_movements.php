<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['receivables', 'customer_ledger_entries'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                });
            }
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['code' => 'transactions.view-own'],
                ['name' => 'عرض الحركات التي أدخلها المستخدم فقط', 'group_name' => 'transactions', 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        foreach (['customer_ledger_entries', 'receivables'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'created_by')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('created_by'));
            }
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('code', 'transactions.view-own')->delete();
        }
    }
};
