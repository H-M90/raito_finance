<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'name', 'phone']);
        });

        Schema::create('contract_item_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('active_quantity', 12, 3)->default(0);
            $table->decimal('inactive_quantity', 12, 3)->default(0);
            $table->decimal('total_quantity', 12, 3)->default(0);
            $table->date('captured_at')->nullable();
            $table->timestamps();
            $table->unique('contract_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_item_usage_snapshots');
        Schema::dropIfExists('customer_contacts');
    }
};
