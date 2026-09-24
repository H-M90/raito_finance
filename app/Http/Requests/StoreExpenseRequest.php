<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['expense_date'=>'required|date','expense_category_id'=>'required|exists:expense_categories,id','description'=>'required|string|max:255','beneficiary'=>'nullable|string|max:255','amount'=>'required|numeric|min:0.01','currency'=>'required|in:SAR,USD,EGP','payment_method'=>'nullable|in:cash,transfer,cheque,card,other','customer_id'=>'nullable|exists:customers,id','contract_id'=>'nullable|exists:contracts,id','attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240','notes'=>'nullable|string']; }
}
