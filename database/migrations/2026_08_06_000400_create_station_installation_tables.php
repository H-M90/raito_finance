<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->string('city', 100)->nullable();
            $table->string('location')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('relationship_start_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'name']);
        });

        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->date('planned_date')->nullable();
            $table->date('installation_date')->nullable()->index();
            $table->string('team_name')->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->string('handover_attachment')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('installation_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->boolean('pts_installed')->default(false);
            $table->unsignedInteger('sensor_count')->default(0);
            $table->date('installed_at')->nullable();
            $table->string('status', 30)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['installation_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_stations');
        Schema::dropIfExists('installations');
        Schema::dropIfExists('stations');
    }
};
