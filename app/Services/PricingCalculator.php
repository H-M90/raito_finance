<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PricingCalculator
{
    public function calculate(array $items, float $taxRate, string $billingCycle='one_time', ?Collection $offers=null, ?string $commissionType=null, float $commissionValue=0): array
    {
        if ($items === []) throw new InvalidArgumentException('يجب إضافة بند واحد على الأقل.');

        $productIds = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id);
        if ($productIds->duplicates()->isNotEmpty()) throw new InvalidArgumentException('لا يمكن تكرار نفس الموديول أو المنتج أكثر من مرة داخل المستند.');

        $offers ??= collect();
        $selectedProductIds = $productIds->unique()->values();

        $bundleOffers = $offers->where('type', 'bundle_fixed_discount')->sortBy('priority')->values();
        foreach ($bundleOffers as $offer) {
            if (! $offer->bundleQualifies($selectedProductIds)) {
                $requiredNames = $offer->relationLoaded('products')
                    ? $offer->products->pluck('name')->filter()->join(' + ')
                    : '';
                throw new InvalidArgumentException('عرض الباقة '.$offer->name.' يتطلب وجود كل منتجات الباقة معًا'.($requiredNames ? ': '.$requiredNames : '.'));
            }
        }

        $effectiveMonetaryOffers = $offers
            ->whereIn('type', ['startup_discount', 'seasonal_discount', 'bundle_fixed_discount'])
            ->filter(function ($offer) use ($selectedProductIds) {
                if ($offer->type === 'bundle_fixed_discount') return $offer->bundleQualifies($selectedProductIds);
                return $selectedProductIds->contains(fn ($productId) => $offer->appliesToProduct((int) $productId));
            })
            ->sortBy('priority')
            ->values();
        if ($effectiveMonetaryOffers->count() > 1 && $effectiveMonetaryOffers->contains(fn ($offer) => ! $offer->is_stackable)) {
            throw new InvalidArgumentException('لا يمكن الجمع بين أكثر من خصم مالي عند وجود عرض غير قابل للجمع. عدّل إعداد العرض أو اختر خصمًا واحدًا فقط.');
        }

        $calculatedItems = [];

        foreach ($items as $index => $item) {
            /** @var Product|null $product */
            $product = $item['product'] ?? null;
            $isModule = $product?->type === 'erp_module';
            $isSeatPriced = (bool) $product?->supports_user_pricing;
            $quantity = ($isModule || $isSeatPriced) ? 1 : (int) ($item['quantity'] ?? 0);
            if (! $isModule && ! $isSeatPriced && ($quantity < 1 || (float) ($item['quantity'] ?? 0) !== (float) $quantity)) {
                throw new InvalidArgumentException('كميات المنتجات والأجهزة يجب أن تكون أعدادًا صحيحة أكبر من صفر.');
            }

            $unitPrice = $product?->code === 'APP-DELEGATES' ? 0 : max(0, (float) ($item['unit_price'] ?? 0));
            $manualDiscount = max(0, (float) ($item['discount_value'] ?? 0));
            $maintenanceRate = $billingCycle === 'one_time' && $product?->code !== 'APP-DELEGATES'
                ? max(0, (float) ($item['maintenance_rate'] ?? 0))
                : 0;

            // requested_users means extra paid users requested by the customer, not total licensed users.
            $purchasedExtraUsers = $product?->supports_user_pricing ? max(0, (int) ($item['requested_users'] ?? 0)) : 0;
            $includedUsers = $product?->supports_user_pricing && $billingCycle === 'one_time'
                ? (int) $product->included_users_one_time
                : 0;

            $productOffers = $offers->filter(fn ($offer) => $offer->appliesToProduct((int) ($item['product_id'] ?? 0)));
            $freeOffers = $productOffers->where('type', 'free_users')
                ->filter(fn ($offer) => $purchasedExtraUsers >= (int) $offer->minimum_users)
                ->sortBy('priority')->values();
            if ($single = $freeOffers->first(fn ($offer) => ! $offer->is_stackable)) $freeOffers = collect([$single]);

            $promotionalFreeUsers = $freeOffers->sum(function ($offer) use ($purchasedExtraUsers) {
                $minimum = (int) $offer->minimum_users;
                if ($minimum <= 0) return 0;
                $multiplier = $offer->repeat_for_each_threshold ? intdiv($purchasedExtraUsers, $minimum) : 1;
                return (int) $offer->free_users * max(0, $multiplier);
            });

            $billableUsers = $purchasedExtraUsers;
            $totalLicensedUsers = $includedUsers + $billableUsers + $promotionalFreeUsers;

            $defaultUserPrice = $product?->supports_user_pricing ? $product->userPriceForCycle($billingCycle) : 0;
            $userUnitPrice = array_key_exists('user_unit_price', $item) && $item['user_unit_price'] !== '' && $item['user_unit_price'] !== null
                ? max(0, (float) $item['user_unit_price'])
                : $defaultUserPrice;
            $userTotal = round($billableUsers * $userUnitPrice, 2);
            $lineSubtotal = round(($quantity * $unitPrice) + $userTotal, 2);
            $manualDiscount = min($manualDiscount, $lineSubtotal);
            $afterManual = round($lineSubtotal - $manualDiscount, 2);

            $percentageOffers = $productOffers->whereIn('type', ['startup_discount', 'seasonal_discount'])->sortBy('priority')->values();
            $lineAfterOffers = $afterManual;
            $linePromotionalDiscount = 0.0;
            foreach ($percentageOffers as $offer) {
                $value = round($lineAfterOffers * ((float) $offer->discount_percentage / 100), 2);
                $linePromotionalDiscount += $value;
                $lineAfterOffers = round($lineAfterOffers - $value, 2);
            }

            $lineNet = max(0, round($lineAfterOffers, 2));
            $maintenanceAnnual = round($lineNet * ($maintenanceRate / 100), 2);

            unset($item['product']);
            $calculatedItems[] = array_merge($item, [
                'quantity' => $quantity,
                'requested_users' => $purchasedExtraUsers,
                'included_users' => $includedUsers,
                'promotional_free_users' => $promotionalFreeUsers,
                'billable_users' => $billableUsers,
                'total_licensed_users' => $totalLicensedUsers,
                'user_unit_price' => $userUnitPrice,
                'user_total' => $userTotal,
                'discount_value' => round($manualDiscount, 2),
                'promotional_discount_value' => round($linePromotionalDiscount, 2),
                'line_subtotal' => $lineSubtotal,
                'line_net' => $lineNet,
                'maintenance_rate' => $maintenanceRate,
                'maintenance_annual' => $maintenanceAnnual,
                'sort_order' => $index + 1,
            ]);
        }

        // Fixed bundle discounts are document-level rules. The fixed amount is
        // allocated proportionally across the required bundle lines so line net,
        // maintenance and historical pricing snapshots remain internally consistent.
        foreach ($bundleOffers as $offer) {
            $requiredIds = collect($offer->bundleProductIds());
            $eligibleIndexes = collect($calculatedItems)
                ->keys()
                ->filter(fn ($i) => $requiredIds->contains((int) $calculatedItems[$i]['product_id']) && (float) $calculatedItems[$i]['line_net'] > 0)
                ->sortBy(fn ($i) => (float) $calculatedItems[$i]['line_net'])
                ->values();

            $bundleNet = round($eligibleIndexes->sum(fn ($i) => (float) $calculatedItems[$i]['line_net']), 2);
            $fixedDiscount = min(max(0, (float) $offer->fixed_discount_amount), $bundleNet);
            if ($fixedDiscount <= 0 || $bundleNet <= 0 || $eligibleIndexes->isEmpty()) continue;

            $remaining = round($fixedDiscount, 2);
            $lastIndex = $eligibleIndexes->last();
            foreach ($eligibleIndexes as $i) {
                $currentNet = (float) $calculatedItems[$i]['line_net'];
                $allocation = $i === $lastIndex
                    ? $remaining
                    : round($fixedDiscount * ($currentNet / $bundleNet), 2);
                $allocation = min($allocation, $currentNet, $remaining);
                $remaining = round($remaining - $allocation, 2);

                $calculatedItems[$i]['promotional_discount_value'] = round((float) $calculatedItems[$i]['promotional_discount_value'] + $allocation, 2);
                $calculatedItems[$i]['line_net'] = round($currentNet - $allocation, 2);
                $calculatedItems[$i]['maintenance_annual'] = round(
                    (float) $calculatedItems[$i]['line_net'] * ((float) $calculatedItems[$i]['maintenance_rate'] / 100),
                    2
                );
            }
        }

        $rows = collect($calculatedItems);
        $subtotal = round($rows->sum(fn ($row) => (float) $row['line_subtotal']), 2);
        $manualDiscountTotal = round($rows->sum(fn ($row) => (float) $row['discount_value']), 2);
        $promotionalDiscountTotal = round($rows->sum(fn ($row) => (float) $row['promotional_discount_value']), 2);
        $maintenanceTotal = round($rows->sum(fn ($row) => (float) $row['maintenance_annual']), 2);
        $discountTotal = round($manualDiscountTotal + $promotionalDiscountTotal, 2);
        $netTotal = round($rows->sum(fn ($row) => (float) $row['line_net']), 2);
        $taxTotal = round($netTotal * ($taxRate / 100), 2);
        $grandTotal = round($netTotal + $taxTotal, 2);
        $commissionTotal = match ($commissionType) {
            'percentage' => round($netTotal * ($commissionValue / 100), 2),
            'fixed' => round(max(0, $commissionValue), 2),
            default => 0.0,
        };

        return compact(
            'calculatedItems', 'subtotal', 'manualDiscountTotal', 'promotionalDiscountTotal', 'discountTotal',
            'netTotal', 'taxTotal', 'grandTotal', 'maintenanceTotal', 'commissionTotal'
        );
    }

    public function snapshot(array $totals, Collection $offers, Collection $products, string $billingCycle, string $currency): array
    {
        return [
            'version' => 3,
            'calculated_at' => now()->toIso8601String(),
            'billing_cycle' => $billingCycle,
            'currency' => $currency,
            'offers' => $offers->map(fn ($o) => [
                'id' => $o->id,
                'code' => $o->code,
                'name' => $o->name,
                'type' => $o->type,
                'discount_percentage' => (float) $o->discount_percentage,
                'fixed_discount_amount' => (float) $o->fixed_discount_amount,
                'minimum_users' => (int) $o->minimum_users,
                'free_users' => (int) $o->free_users,
                'repeat_for_each_threshold' => (bool) $o->repeat_for_each_threshold,
                'is_stackable' => (bool) $o->is_stackable,
                'product_ids' => $o->relationLoaded('products') ? $o->products->pluck('id')->map(fn ($id) => (int) $id)->values()->all() : [],
            ])->values()->all(),
            'items' => collect($totals['calculatedItems'])->map(function ($row) use ($products) {
                $p = $products->get((int) $row['product_id']);
                return array_merge($row, ['product_code' => $p?->code, 'product_name' => $p?->name]);
            })->all(),
            'totals' => collect($totals)->except('calculatedItems')->all(),
        ];
    }
}
