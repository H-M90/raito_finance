<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $supports = $this->boolean('supports_user_pricing');
        $cycle = (string) $this->input('billing_cycle', 'one_time');
        $defaultIncluded = (int) config('finance.included_users_per_module_one_time', 3);

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'supports_user_pricing' => $supports,
            // بعض المنتجات (مثل تطبيق المناديب) تُباع بالكامل لكل مستخدم ولا تحتوي مستخدمين مجانيين.
            'included_users_one_time' => $supports ? (int) $this->input('included_users_one_time', $defaultIncluded) : 0,
            'extra_user_price_one_time' => $supports ? $this->input('extra_user_price_one_time', 0) : 0,
            'user_price_monthly' => $supports ? $this->input('user_price_monthly', 0) : 0,
            'user_price_annual' => $supports ? $this->input('user_price_annual', 0) : 0,
            'default_maintenance_rate' => $cycle === 'one_time' ? (int) $this->input('default_maintenance_rate', 0) : 0,
            'required_product_id' => $this->filled('required_product_id') ? $this->integer('required_product_id') : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:40',
            'unit' => 'required|string|max:30',
            'default_sale_price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:one_time,monthly,annual',
            'supports_user_pricing' => 'boolean',
            'required_product_id' => ['nullable','integer','exists:products,id',Rule::notIn(array_filter([$id]))],
            'included_users_one_time' => 'required|integer|min:0|max:100000',
            'extra_user_price_one_time' => 'required|numeric|min:0',
            'user_price_monthly' => 'required|numeric|min:0',
            'user_price_annual' => 'required|numeric|min:0',
            'default_maintenance_rate' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ];
    }
}
