<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$required = ['customers', 'products', 'contracts', 'contract_items', 'receivables', 'collections', 'collection_allocations'];
foreach ($required as $table) {
    if (! Schema::hasTable($table)) {
        fwrite(STDERR, "Missing table: {$table}\n");
        exit(1);
    }
}

$checks = [
    'contract_items_without_contract' => DB::table('contract_items as items')->leftJoin('contracts as contracts', 'contracts.id', '=', 'items.contract_id')->whereNull('contracts.id')->count(),
    'contract_items_without_product' => DB::table('contract_items as items')->leftJoin('products as products', 'products.id', '=', 'items.product_id')->whereNull('products.id')->count(),
    'receivables_without_customer' => DB::table('receivables as dues')->leftJoin('customers as customers', 'customers.id', '=', 'dues.customer_id')->whereNull('customers.id')->count(),
    'allocations_without_collection' => DB::table('collection_allocations as allocations')->leftJoin('collections as collections', 'collections.id', '=', 'allocations.collection_id')->whereNull('collections.id')->count(),
    'allocations_without_receivable' => DB::table('collection_allocations as allocations')->leftJoin('receivables as dues', 'dues.id', '=', 'allocations.receivable_id')->whereNull('dues.id')->count(),
    'receivable_balance_mismatch' => DB::table('receivables')->whereNull('deleted_at')->whereRaw('ABS((total_amount - collected_amount - discounted_amount) - remaining_amount) > 0.01')->count(),
    'allocation_currency_mismatch' => DB::table('collection_allocations as allocations')->join('collections as collections', 'collections.id', '=', 'allocations.collection_id')->join('receivables as dues', 'dues.id', '=', 'allocations.receivable_id')->whereColumn('collections.currency', '!=', 'dues.currency')->count(),
];

foreach ($checks as $name => $count) {
    echo $name.': '.$count."\n";
}

exit(array_sum($checks) === 0 ? 0 : 1);
