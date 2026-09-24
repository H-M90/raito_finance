<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('type');
        $data = [
            'is_active' => $this->boolean('is_active'),
            'is_stackable' => $this->boolean('is_stackable'),
            'repeat_for_each_threshold' => $this->boolean('repeat_for_each_threshold'),
        ];

        if ($type === 'startup_discount') {
            $data['customer_segment'] = 'startup';
            $data['billing_cycle'] = 'all';
            $data['discount_percentage'] = (float) config('finance.startup_discount_percentage', 50);
        }

        if (! in_array($type, ['startup_discount', 'seasonal_discount'], true)) {
            $data['discount_percentage'] = 0;
        }

        if ($type !== 'bundle_fixed_discount') {
            $data['fixed_discount_amount'] = 0;
        }

        if ($type !== 'free_users') {
            $data['minimum_users'] = 0;
            $data['free_users'] = 0;
            $data['repeat_for_each_threshold'] = false;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $isPercentage = in_array($this->input('type'), ['startup_discount', 'seasonal_discount'], true);
        $isBundle = $this->input('type') === 'bundle_fixed_discount';
        $isFreeUsers = $this->input('type') === 'free_users';

        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:startup_discount,seasonal_discount,free_users,bundle_fixed_discount',
            'customer_segment' => 'required|in:all,standard,startup',
            'billing_cycle' => 'required|in:all,one_time,monthly,annual',
            'discount_percentage' => [Rule::requiredIf($isPercentage), 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_discount_amount' => [Rule::requiredIf($isBundle), 'nullable', 'numeric', 'min:0.01'],
            'minimum_users' => [Rule::requiredIf($isFreeUsers), 'nullable', 'integer', 'min:0'],
            'free_users' => [Rule::requiredIf($isFreeUsers), 'nullable', 'integer', 'min:0'],
            'repeat_for_each_threshold' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'required|integer|min:1|max:1000',
            'product_ids' => [Rule::requiredIf($isBundle), 'nullable', 'array', $isBundle ? 'min:2' : 'min:0'],
            'product_ids.*' => 'integer|distinct|exists:products,id',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'fixed_discount_amount.required' => 'قيمة الخصم الثابت مطلوبة لعرض الباقة.',
            'product_ids.required' => 'حدد منتجات الباقة التي يجب أن تكون موجودة معًا.',
            'product_ids.min' => 'عرض الباقة يجب أن يحتوي على منتجين على الأقل.',
        ];
    }
}
