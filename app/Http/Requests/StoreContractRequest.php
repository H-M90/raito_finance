<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\SalesQuotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Support\ProductDependencyRules;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $data = ['is_imported' => $this->boolean('is_imported')];

        // Accepted quotations are pricing snapshots. Client, activity, cycle,
        // currency, items and commission cannot be changed during conversion.
        if (! $this->route('contract') && $this->filled('sales_quotation_id')) {
            $quotation = SalesQuotation::with(['items', 'pricingOffers'])->find($this->integer('sales_quotation_id'));
            if ($quotation) {
                $data = array_merge($data, [
                    'customer_id' => $quotation->customer_id,
                    'activity_type' => $quotation->activity_type,
                    'billing_cycle' => $quotation->billing_cycle,
                    'currency' => $quotation->currency,
                    'sales_owner_id' => $quotation->sales_owner_id,
                    'intermediary_id' => $quotation->intermediary_id,
                    'commission_type' => $quotation->commission_type,
                    'commission_value' => $quotation->commission_value,
                    'commission_due_basis' => $quotation->intermediary_id ? 'collection' : null,
                    'pricing_offer_ids' => $quotation->pricingOffers->pluck('id')->all(),
                    'items' => $quotation->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'quantity' => (int) $item->quantity,
                        'requested_users' => (int) $item->requested_users,
                        'user_unit_price' => (float) $item->user_unit_price,
                        'unit_price' => (float) $item->unit_price,
                        'discount_value' => (float) $item->discount_value,
                        'maintenance_rate' => (float) $item->maintenance_rate,
                        'notes' => $item->notes,
                    ])->all(),
                ]);
            }
        }

        // If there is no intermediary, discard every commission field. This
        // keeps hidden inputs or stale browser values from creating commissions.
        $hasIntermediary = array_key_exists('intermediary_id', $data)
            ? filled($data['intermediary_id'])
            : ($this->has('has_intermediary') ? $this->boolean('has_intermediary') : $this->filled('intermediary_id'));
        if (! $hasIntermediary) {
            $data['intermediary_id'] = null;
            $data['commission_type'] = null;
            $data['commission_value'] = null;
            $data['commission_due_basis'] = null;
        } else {
            $data['commission_due_basis'] = 'collection';
        }
        $data['has_intermediary'] = $hasIntermediary;

        // ERP modules and user-priced products are not quantity-based items. Quantity is
        // always one; users/delegates are handled by requested_users.
        $items = $data['items'] ?? $this->input('items', []);
        if (is_array($items) && $items !== []) {
            $fixedQuantityIds = Product::whereIn('id', collect($items)->pluck('product_id')->filter())
                ->where(fn ($q) => $q->where('type', 'erp_module')->orWhere('supports_user_pricing', true))->pluck('id')->map(fn ($id) => (int) $id)->all();
            $items = collect($items)->map(function ($item) use ($fixedQuantityIds) {
                if (in_array((int) ($item['product_id'] ?? 0), $fixedQuantityIds, true)) $item['quantity'] = 1;
                return $item;
            })->all();
            $data['items'] = $items;
        }

        // Stations belong only to station contracts. Hidden fields from a
        // changed activity type must never keep stale station allocations.
        $activity = (string) ($data['activity_type'] ?? $this->input('activity_type', ''));
        if ($activity !== 'stations') $data['stations'] = [];

        $this->merge($data);
    }

    public function rules(): array
    {
        $id = $this->route('contract')?->id;

        return [
            // Contract number is intentionally absent: it is generated only by the backend.
            'customer_id' => 'required|exists:customers,id',
            'sales_quotation_id' => ['nullable','exists:sales_quotations,id',Rule::unique('contracts','sales_quotation_id')->ignore($id)],
            'activity_type' => 'required|in:erp,stations,other',
            'domain' => 'nullable|string|max:255',
            'contract_date' => 'required|date',
            'service_start_date' => 'required|date',
            'billing_cycle' => 'required|in:one_time,monthly,annual',
            'currency' => 'required|in:SAR,USD,EGP',
            'sales_owner_id' => 'nullable|exists:users,id',
            'has_intermediary' => 'boolean',
            'intermediary_id' => 'required_if:has_intermediary,1|nullable|exists:intermediaries,id',
            'commission_type' => 'required_if:has_intermediary,1|nullable|in:percentage,fixed',
            'commission_value' => 'required_if:has_intermediary,1|nullable|numeric|min:0',
            'commission_due_basis' => 'nullable|in:collection',
            'calculation_start_date' => 'nullable|date',
            'maintenance_paid_until' => 'nullable|date',
            'opening_receivable_balance' => 'nullable|numeric|min:0',
            'opening_maintenance_balance' => 'nullable|numeric|min:0',
            'previous_collections_total' => 'nullable|numeric|min:0',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'is_imported' => 'boolean',
            'manual_contract_total' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(fn () => $this->boolean('is_imported')),
            ],
            'notes' => 'nullable|string',
            'pricing_offer_ids' => 'nullable|array',
            'pricing_offer_ids.*' => 'integer|exists:pricing_offers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.requested_users' => 'nullable|integer|min:0',
            'items.*.user_unit_price' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.maintenance_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
            'installments' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('billing_cycle') === 'one_time' && ! $this->boolean('is_imported')),
            ],
            'installments.*.name' => ['nullable','string','max:255'],
            'installments.*.percentage' => 'nullable|numeric|min:0|max:100',
            'installments.*.due_date' => ['nullable','date'],
            'installments.*.net_amount' => ['nullable','numeric','min:0'],
            'installments.*.notes' => 'nullable|string',
            'stations' => 'nullable|array',
            'stations.*.id' => 'nullable|integer|exists:stations,id',
            'stations.*.name' => 'nullable|string|max:255',
            'stations.*.city' => 'nullable|string|max:100',
            'stations.*.location' => 'nullable|string|max:255',
            'stations.*.contact_name' => 'nullable|string|max:255',
            'stations.*.phone' => 'nullable|string|max:40',
            'stations.*.relationship_start_date' => 'nullable|date',
            'stations.*.notes' => 'nullable|string',
            'stations.*.pts_count' => 'nullable|integer|min:0|max:1',
            'stations.*.sensor_count' => 'nullable|integer|min:0',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $ids = collect($this->input('items', []))->pluck('product_id')->filter();
            if ($ids->duplicates()->isNotEmpty()) {
                $validator->errors()->add('items', 'لا يمكن تكرار نفس الموديول أو المنتج داخل العقد.');
            }
            if ($this->input('commission_type') === 'percentage' && (float) $this->input('commission_value', 0) > 100) {
                $validator->errors()->add('commission_value', 'نسبة عمولة الوسيط لا يمكن أن تتجاوز 100%.');
            }
            ProductDependencyRules::validateSelection($validator, $this->input('items', []));
        }];
    }
}
