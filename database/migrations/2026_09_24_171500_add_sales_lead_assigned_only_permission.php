<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['code' => 'sales-leads.view-assigned-only'],
            [
                'name' => 'عرض العملاء المحتملين المسؤول عنهم فقط',
                'group_name' => 'sales-leads',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'sales-leads.view-assigned-only')->delete();
    }
};
