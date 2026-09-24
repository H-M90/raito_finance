<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceQuantityService
{
    public function fromPayload(array $items): array
    {
        $ids = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $types = Product::whereIn('id', $ids)->pluck('type', 'id');
        $counts = ['pts' => 0, 'sensor' => 0];

        foreach ($items as $item) {
            $type = $types->get((int) ($item['product_id'] ?? 0));
            if (! array_key_exists($type, $counts)) continue;
            $counts[$type] += (int) ($item['quantity'] ?? 0);
        }

        return $counts;
    }

    public function fromItemCollection(Collection $items): array
    {
        $counts = ['pts' => 0, 'sensor' => 0];
        foreach ($items as $item) {
            $type = $item->product?->type;
            if (! array_key_exists($type, $counts)) continue;
            $counts[$type] += (int) $item->quantity;
        }
        return $counts;
    }

    public function contractBase(Contract $contract): array
    {
        return $this->sumTable('contract_items', 'contract_id', $contract->id);
    }

    public function addendum(ContractAddendum $addendum): array
    {
        return $this->sumTable('contract_addendum_items', 'contract_addendum_id', $addendum->id);
    }

    public function activeAddendums(Contract $contract, ?int $excludeAddendumId = null): array
    {
        $query = DB::table('contract_addendum_items')
            ->join('contract_addendums', 'contract_addendums.id', '=', 'contract_addendum_items.contract_addendum_id')
            ->join('products', 'products.id', '=', 'contract_addendum_items.product_id')
            ->where('contract_addendums.contract_id', $contract->id)
            ->where('contract_addendums.status', 'active');

        if ($excludeAddendumId) $query->where('contract_addendums.id', '!=', $excludeAddendumId);

        return $this->sumByProductType($query);
    }

    public function totalCapacity(Contract $contract, ?int $excludeAddendumId = null): array
    {
        $base = $this->contractBase($contract);
        $addendums = $this->activeAddendums($contract, $excludeAddendumId);
        return [
            'pts' => $base['pts'] + $addendums['pts'],
            'sensor' => $base['sensor'] + $addendums['sensor'],
        ];
    }

    private function sumTable(string $table, string $foreignKey, int $id): array
    {
        $query = DB::table($table)
            ->join('products', 'products.id', '=', "$table.product_id")
            ->where("$table.$foreignKey", $id);

        return $this->sumByProductType($query, $table);
    }

    private function sumByProductType($query, string $table = 'contract_addendum_items'): array
    {
        $rows = (clone $query)
            ->whereIn('products.type', ['pts', 'sensor'])
            ->select('products.type as product_type', DB::raw("SUM($table.quantity) as qty"))
            ->groupBy('products.type')
            ->pluck('qty', 'product_type');

        return [
            'pts' => (int) round((float) ($rows['pts'] ?? 0)),
            'sensor' => (int) round((float) ($rows['sensor'] ?? 0)),
        ];
    }
}
