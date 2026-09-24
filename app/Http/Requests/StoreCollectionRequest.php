<?php
namespace App\Http\Requests;

use App\Models\Contract;
use App\Models\Receivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('contract_id')) return;

        $contract = Contract::find($this->integer('contract_id'));
        if (! $contract) return;

        // The selected contract is the source of truth for customer/currency.
        $this->merge([
            'customer_id' => $contract->customer_id,
            'currency' => $contract->currency,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id'=>'required|exists:customers,id',
            'contract_id'=>'required|exists:contracts,id',
            'collection_date'=>'required|date',
            'currency'=>'required|in:SAR,USD,EGP',
            'amount'=>'required|numeric|min:0.01',
            'payment_method'=>'required|in:cash,transfer,cheque,card,other',
            'reference_no'=>'nullable|string|max:255',
            'collector_name'=>'nullable|string|max:255',
            'attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'notes'=>'nullable|string',
            'allocations'=>'required|array|min:1',
            'allocations.*.receivable_id'=>'required|exists:receivables,id',
            'allocations.*.amount'=>'required|numeric|min:0',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $contractId = $this->integer('contract_id');
            if (! $contractId) return;

            $receivableIds = collect($this->input('allocations', []))
                ->filter(fn ($row) => (float) ($row['amount'] ?? 0) > 0)
                ->pluck('receivable_id')->filter()->map(fn ($id) => (int) $id)->unique();

            if ($receivableIds->isEmpty()) return;

            $invalid = Receivable::whereIn('id', $receivableIds)
                ->where('contract_id', '!=', $contractId)
                ->exists();

            if ($invalid) {
                $validator->errors()->add('allocations', 'كل بنود التحصيل يجب أن تخص العقد المختار.');
            }
        }];
    }
}
