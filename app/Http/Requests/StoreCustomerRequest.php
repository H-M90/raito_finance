<?php
namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $id = is_object($customer) ? $customer->id : $customer;
        return [
            'name'=>'required|string|max:255',
            'country'=>'nullable|string|max:100','city'=>'nullable|string|max:100','address'=>'nullable|string|max:255',
            'commercial_registration_no'=>['nullable','string','max:80',Rule::unique('customers','commercial_registration_no')->ignore($id)->withoutTrashed()],
            'tax_no'=>['nullable','string','max:80',Rule::unique('customers','tax_no')->ignore($id)->withoutTrashed()],
            'contact_name'=>'nullable|string|max:255','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255',
            'sales_owner_id'=>'nullable|exists:users,id','segment'=>'required|in:standard,startup','status'=>'nullable|in:active,inactive',
            'inactive_reason'=>'nullable|string','notes'=>'nullable|string','source_type'=>'nullable|string|max:30','source_id'=>'nullable|integer',
            'attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:10240',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;

            $customer = $this->route('customer');
            $id = is_object($customer) ? $customer->id : $customer;
            foreach ([
                'name' => 'يوجد عميل مسجل بنفس الاسم.',
                'phone' => 'يوجد عميل مسجل بنفس رقم الهاتف.',
                'email' => 'يوجد عميل مسجل بنفس البريد الإلكتروني.',
            ] as $field => $message) {
                $value = trim((string) $this->input($field, ''));
                if ($value === '') continue;
                $query = Customer::query()->where($field, $value);
                if ($id) $query->whereKeyNot($id);
                if ($query->exists()) $validator->errors()->add($field, $message);
            }
        });
    }

    public function messages(): array
    {
        return [
            'commercial_registration_no.unique'=>'يوجد عميل بنفس رقم السجل التجاري.',
            'tax_no.unique'=>'يوجد عميل بنفس الرقم الضريبي.',
        ];
    }
}
