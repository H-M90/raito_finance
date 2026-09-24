<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

final class ProductDependencyRules
{
    public static function validateSelection(Validator $validator, array $items, array $extraAvailableProductIds = []): void
    {
        $rows = collect($items)->filter(fn ($row) => filled($row['product_id'] ?? null));
        $ids = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->values();
        if ($ids->isEmpty()) return;

        $products = Product::with('requiredProduct:id,name,code')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $available = $ids->merge($extraAvailableProductIds)->map(fn ($id) => (int) $id)->unique();

        foreach ($rows as $index => $row) {
            $product = $products->get((int) $row['product_id']);
            if (! $product) continue;

            if ($product->required_product_id && ! $available->contains((int) $product->required_product_id)) {
                $requiredName = $product->requiredProduct?->name ?: 'المنتج المرتبط';
                $validator->errors()->add("items.$index.product_id", "{$product->name} يتطلب وجود {$requiredName} في العقد.");
            }

            if ($product->code === 'APP-DELEGATES' && (int) ($row['requested_users'] ?? 0) < 1) {
                $validator->errors()->add("items.$index.requested_users", 'أدخل عدد المناديب. تطبيق المناديب لا يُباع بسعر أساسي؛ التحصيل يكون لكل مندوب.');
            }
        }
    }

    public static function activeContractProductIds(Contract $contract, \DateTimeInterface|string|null $date = null, ?int $excludeAddendumId = null): array
    {
        $date = Carbon::parse($date ?: today())->toDateString();
        $baseIds = $contract->items()->activeOn($date)->pluck('product_id');
        $addendumIds = DB::table('contract_addendum_items as cai')
            ->join('contract_addendums as ca', 'ca.id', '=', 'cai.contract_addendum_id')
            ->where('ca.contract_id', $contract->id)
            ->where('ca.status', 'active')
            ->whereDate('ca.service_start_date', '<=', $date)
            ->when($excludeAddendumId, fn ($q) => $q->where('ca.id', '!=', $excludeAddendumId))
            ->pluck('cai.product_id');

        return $baseIds->merge($addendumIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
