<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_items', function (Blueprint $table) {
            $table->date('active_from')->nullable()->after('product_id');
            $table->date('stopped_at')->nullable()->after('active_from');
            $table->string('status', 20)->default('active')->after('stopped_at');
            $table->text('stop_reason')->nullable()->after('status');
            $table->foreignId('stopped_by')->nullable()->after('stop_reason')->constrained('users')->nullOnDelete();
            $table->index(['contract_id', 'stopped_at'], 'contract_items_stop_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contract_items', function (Blueprint $table) {
            $table->dropIndex('contract_items_stop_date_idx');
            $table->dropConstrainedForeignId('stopped_by');
            $table->dropColumn(['active_from', 'stopped_at', 'status', 'stop_reason']);
        });
    }
};
