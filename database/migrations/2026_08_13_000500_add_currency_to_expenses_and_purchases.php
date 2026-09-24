<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $defaultCurrency = config('finance.default_currency', 'SAR');

        if (! Schema::hasColumn('expenses', 'currency')) {
            Schema::table('expenses', function (Blueprint $table) use ($defaultCurrency) {
                $table->string('currency', 3)->default($defaultCurrency)->index()->after('amount');
            });
        }

        if (! Schema::hasColumn('purchases', 'currency')) {
            Schema::table('purchases', function (Blueprint $table) use ($defaultCurrency) {
                $table->string('currency', 3)->default($defaultCurrency)->index()->after('purchase_date');
            });
        }

        DB::table('expenses')
            ->join('contracts', 'contracts.id', '=', 'expenses.contract_id')
            ->select('expenses.id', 'contracts.currency')
            ->orderBy('expenses.id')
            ->get()
            ->each(fn ($row) => DB::table('expenses')->where('id', $row->id)->update(['currency' => $row->currency]));

        DB::table('purchase_allocations')
            ->join('purchase_items', 'purchase_items.id', '=', 'purchase_allocations.purchase_item_id')
            ->join('contracts', 'contracts.id', '=', 'purchase_allocations.contract_id')
            ->select('purchase_items.purchase_id', 'contracts.currency')
            ->distinct()
            ->orderBy('purchase_items.purchase_id')
            ->get()
            ->groupBy('purchase_id')
            ->each(function ($rows, $purchaseId) {
                $currency = $rows->pluck('currency')->unique()->first();
                if ($currency) {
                    DB::table('purchases')->where('id', $purchaseId)->update(['currency' => $currency]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'currency')) {
            Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('currency'));
        }
        if (Schema::hasColumn('purchases', 'currency')) {
            Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('currency'));
        }
    }
};
