<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);

        return response()->json(
            Customer::active()
                ->when($q, fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")))
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn ($customer) => [
                    'id' => $customer->id,
                    // Internal codes stay searchable but are not shown in entry controls.
                    'text' => $customer->name,
                    'segment' => $customer->segment,
                ])
        );
    }

    public function products(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);

        return response()->json(
            Product::active()->with('requiredProduct:id,name,code')
                ->when($q, fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")))
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'text' => $product->name,
                    'type' => $product->type,
                    'code' => $product->code,
                    'price' => $product->default_sale_price,
                    'maintenance' => $product->default_maintenance_rate,
                    'user_pricing' => $product->supports_user_pricing,
                    'included_users' => $product->included_users_one_time,
                    'user_one_time' => $product->extra_user_price_one_time,
                    'user_monthly' => $product->user_price_monthly,
                    'user_annual' => $product->user_price_annual,
                    'required_product_id' => $product->required_product_id,
                    'required_product_name' => $product->requiredProduct?->name,
                ])
        );
    }

    public function contracts(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);

        return response()->json(
            Contract::with('customer:id,name')
                ->where('status', 'active')
                ->when($request->customer_id, fn ($query, $customerId) => $query->where('customer_id', $customerId))
                ->when($request->activity_type, fn ($query, $activity) => $query->where('activity_type', $activity))
                ->when($q, fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$q}%"))))
                ->orderByDesc('contract_date')
                ->limit(20)
                ->get()
                ->map(fn ($contract) => [
                    'id' => $contract->id,
                    'text' => $contract->number.' · '.$contract->customer->name,
                    'customer_id' => $contract->customer_id,
                    'currency' => $contract->currency,
                    'activity_type' => $contract->activity_type,
                ])
        );
    }

    public function stations(Request $request): JsonResponse
    {
        $q = trim((string) $request->q);
        $stations = Station::where('is_active', true)
            ->when($request->customer_id, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($request->contract_id, fn ($query, $contractId) => $query->whereHas('contracts', fn ($contracts) => $contracts->where('contracts.id', $contractId)))
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($request->contract_id, fn ($query, $contractId) => $query->with(['contracts' => fn ($contracts) => $contracts->where('contracts.id', $contractId)]))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($stations->map(function ($station) use ($request) {
            $pivot = $request->contract_id ? $station->contracts->first()?->pivot : null;

            return [
                'id' => $station->id,
                'text' => $station->name,
                'customer_id' => $station->customer_id,
                'pts_allowed' => ! empty($pivot?->pts_count) ? 1 : 0,
                'sensors_allowed' => (int) ($pivot?->sensor_count ?? 0),
            ];
        }));
    }
}
