<?php

namespace App\Http\Requests;

use App\Models\Receivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReceivablePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $receivable = $this->route('receivable');
            if (! $receivable instanceof Receivable) {
                return;
            }

            if (! $receivable->contract_id) {
                $validator->errors()->add('receivable', 'لا يمكن إنشاء سند قبض سريع لاستحقاق غير مرتبط بعقد.');
            }

            if ((float) $receivable->remaining_amount <= 0) {
                $validator->errors()->add('receivable', 'هذا الاستحقاق مسدد بالكامل.');
            }

            if (in_array($receivable->status, ['cancelled', 'waived'], true)) {
                $validator->errors()->add('receivable', 'لا يمكن سداد استحقاق ملغى أو معفى.');
            }

            if ((float) $this->input('amount', 0) > (float) $receivable->remaining_amount) {
                $validator->errors()->add('amount', 'قيمة السداد أكبر من الرصيد المتبقي للاستحقاق.');
            }
        }];
    }
}
