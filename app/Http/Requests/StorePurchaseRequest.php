<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['supplier_id'=>'required|exists:suppliers,id','supplier_invoice_no'=>'nullable|string|max:100','purchase_date'=>'required|date','currency'=>'required|in:SAR,USD,EGP','attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240','notes'=>'nullable|string','items'=>'required|array|min:1','items.*.product_id'=>'nullable|exists:products,id','items.*.description'=>'required|string|max:255','items.*.quantity'=>'required|numeric|min:0.001','items.*.unit_cost'=>'required|numeric|min:0','allocations'=>'nullable|array','allocations.*.item_index'=>'required|integer|min:0','allocations.*.customer_id'=>'nullable|exists:customers,id','allocations.*.contract_id'=>'nullable|exists:contracts,id','allocations.*.station_id'=>'nullable|exists:stations,id','allocations.*.quantity'=>'nullable|numeric|min:0','allocations.*.amount'=>'required|numeric|min:0','allocations.*.notes'=>'nullable|string']; }
}
