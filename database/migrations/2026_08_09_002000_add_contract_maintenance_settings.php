<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_maintenance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->string('mode', 20)->default('auto');
            $table->decimal('annual_amount', 18, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['contract_id', 'effective_from'], 'contract_maintenance_setting_effective_idx');
            $table->index(['contract_id', 'effective_from', 'mode'], 'contract_maintenance_setting_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_maintenance_settings');
    }
};
