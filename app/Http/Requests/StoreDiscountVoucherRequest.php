<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreDiscountVoucherRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['customer_id'=>'required|exists:customers,id','receivable_id'=>'required|exists:receivables,id','voucher_date'=>'required|date','currency'=>'required|in:SAR,USD,EGP','amount'=>'required|numeric|min:0.01','reason'=>'required|string|max:255','notes'=>'nullable|string','attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240']; }
}
