<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_station', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['contract_id', 'station_id']);
            $table->index(['station_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_station');
    }
};
