<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PricingOffer;
use Illuminate\Support\Collection;

class PricingOfferResolver
{
    public function resolve(array $requestedIds, Customer $customer, string $billingCycle, \DateTimeInterface|string|null $date = null): Collection
    {
        $requestedIds = collect($requestedIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $automaticStartupIds = PricingOffer::available($date)
            ->where('type', 'startup_discount')
            ->whereIn('customer_segment', ['all', $customer->segment])
            ->pluck('id');

        $allIds = $requestedIds->merge($automaticStartupIds)->unique()->values();
        if ($allIds->isEmpty()) return collect();

        $offers = PricingOffer::with('products:id')->available($date)->whereIn('id', $allIds)->orderBy('priority')->orderBy('id')->get();

        $missing = $requestedIds->diff($offers->pluck('id'));
        if ($missing->isNotEmpty()) {
            throw new \DomainException('أحد عروض التسعير المختارة غير نشط أو خارج فترة الصلاحية.');
        }

        foreach ($offers as $offer) {
            if (! $offer->appliesToCycle($billingCycle)) {
                if ($requestedIds->contains($offer->id)) throw new \DomainException("العرض {$offer->name} لا ينطبق على دورية الفوترة المختارة.");
                continue;
            }
            if (! $offer->appliesToSegment($customer->segment)) {
                if ($requestedIds->contains($offer->id)) throw new \DomainException("العميل غير مؤهل للعرض {$offer->name}.");
            }
        }

        return $offers->filter(fn ($offer) => $offer->appliesToCycle($billingCycle) && $offer->appliesToSegment($customer->segment))->values();
    }
}
