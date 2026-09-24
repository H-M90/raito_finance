<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Support\ProductDependencyRules;
class StoreAddendumRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (!is_array($items) || $items === []) return;
        $fixedQuantityIds = \App\Models\Product::whereIn('id', collect($items)->pluck('product_id')->filter())->where(fn ($q) => $q->where('type', 'erp_module')->orWhere('supports_user_pricing', true))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $items = collect($items)->map(function ($item) use ($fixedQuantityIds) {
            if (in_array((int) ($item['product_id'] ?? 0), $fixedQuantityIds, true)) $item['quantity'] = 1;
            return $item;
        })->all();
        $this->merge(['items' => $items]);
    }
    public function rules(): array { return ['addendum_date'=>'required|date','service_start_date'=>'required|date','notes'=>'nullable|string','attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240','pricing_offer_ids'=>'nullable|array','pricing_offer_ids.*'=>'integer|exists:pricing_offers,id','items'=>'required|array|min:1','items.*.product_id'=>'required|exists:products,id','items.*.quantity'=>'required|integer|min:1','items.*.requested_users'=>'nullable|integer|min:0','items.*.user_unit_price'=>'nullable|numeric|min:0','items.*.unit_price'=>'required|numeric|min:0','items.*.discount_value'=>'nullable|numeric|min:0','items.*.maintenance_rate'=>'nullable|numeric|min:0|max:100','items.*.notes'=>'nullable|string','installments'=>'nullable|array','installments.*.name'=>'required_with:installments|string|max:255','installments.*.percentage'=>'nullable|numeric|min:0|max:100','installments.*.due_date'=>'required_with:installments|date','installments.*.net_amount'=>'required_with:installments|numeric|min:0.01','installments.*.notes'=>'nullable|string']; }
    public function after(): array { return [function(Validator $v){$ids=collect($this->input('items',[]))->pluck('product_id')->filter();if($ids->duplicates()->isNotEmpty())$v->errors()->add('items','لا يمكن تكرار نفس الموديول أو المنتج داخل الملحق.');$contract=$this->route('contract');$addendum=$this->route('addendum');$available=$contract?ProductDependencyRules::activeContractProductIds($contract,$this->input('service_start_date'),$addendum?->id):[];ProductDependencyRules::validateSelection($v,$this->input('items',[]),$available);}]; }
}
