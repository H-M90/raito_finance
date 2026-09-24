<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One station can have at most one PTS. Keep the existing integer
        // pivot column for backwards compatibility, but normalize its value
        // to a boolean-like 0/1 allocation. Sensors remain quantity based.
        if (Schema::hasTable('contract_station') && Schema::hasColumn('contract_station', 'pts_count')) {
            DB::table('contract_station')->where('pts_count', '>', 1)->update(['pts_count' => 1]);
            DB::table('contract_station')->where('pts_count', '<', 0)->update(['pts_count' => 0]);
        }

        // Some pre-release builds briefly introduced installation_stations.pts_count.
        // Runtime continues to use pts_installed (boolean); normalize the temporary
        // column if it exists instead of depending on it.
        if (Schema::hasTable('installation_stations') && Schema::hasColumn('installation_stations', 'pts_count')) {
            DB::table('installation_stations')->where('pts_count', '>', 1)->update(['pts_count' => 1]);
        }
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible.
    }
};
