<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Support\ProductDependencyRules;
class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $data = ['status' => 'draft'];
        $hasIntermediary = $this->has('has_intermediary') ? $this->boolean('has_intermediary') : $this->filled('intermediary_id');
        if (! $hasIntermediary) {
            $data['intermediary_id'] = null;
            $data['commission_type'] = null;
            $data['commission_value'] = null;
        }
        $data['has_intermediary'] = $hasIntermediary;

        // Station count is only a quotation summary for station activity.
        // PTS and sensors always come from line items, never from this summary.
        $activity = (string) $this->input('activity_type', '');
        if ($activity !== 'stations') $data['station_count'] = 0;

        $items = $this->input('items', []);
        if (is_array($items) && $items !== []) {
            $fixedQuantityIds = \App\Models\Product::whereIn('id', collect($items)->pluck('product_id')->filter())->where(fn ($q) => $q->where('type', 'erp_module')->orWhere('supports_user_pricing', true))->pluck('id')->map(fn ($id) => (int) $id)->all();
            $items = collect($items)->map(function ($item) use ($fixedQuantityIds) {
                if (in_array((int) ($item['product_id'] ?? 0), $fixedQuantityIds, true)) $item['quantity'] = 1;
                return $item;
            })->all();
            $data['items'] = $items;
        }

        $this->merge($data);
    }
    public function rules(): array { return ['customer_id'=>'required|exists:customers,id','activity_type'=>'required|in:erp,stations,other','quotation_date'=>'required|date','valid_until'=>'nullable|date|after_or_equal:quotation_date','currency'=>'required|in:SAR,USD,EGP','sales_owner_id'=>'nullable|exists:users,id','lead_source'=>'nullable|string|max:40','has_intermediary'=>'boolean','intermediary_id'=>'required_if:has_intermediary,1|nullable|exists:intermediaries,id','commission_type'=>'required_if:has_intermediary,1|nullable|in:percentage,fixed','commission_value'=>'required_if:has_intermediary,1|nullable|numeric|min:0','billing_cycle'=>'required|in:one_time,monthly,annual','station_count'=>'nullable|integer|min:0','status'=>'required|in:draft,sent,negotiation,accepted,rejected,expired,cancelled','attachment'=>'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240','payment_terms'=>'nullable|string','expected_execution_period'=>'nullable|string|max:255','notes'=>'nullable|string','rejection_reason'=>'nullable|string','lost_to_competitor'=>'nullable|string|max:255','pricing_offer_ids'=>'nullable|array','pricing_offer_ids.*'=>'integer|exists:pricing_offers,id','items'=>'required|array|min:1','items.*.product_id'=>'required|exists:products,id','items.*.quantity'=>'required|integer|min:1','items.*.requested_users'=>'nullable|integer|min:0','items.*.user_unit_price'=>'nullable|numeric|min:0','items.*.unit_price'=>'required|numeric|min:0','items.*.discount_value'=>'nullable|numeric|min:0','items.*.maintenance_rate'=>'nullable|numeric|min:0|max:100','items.*.notes'=>'nullable|string']; }
    public function after(): array { return [function(Validator $v){$ids=collect($this->input('items',[]))->pluck('product_id')->filter();if($ids->duplicates()->isNotEmpty())$v->errors()->add('items','لا يمكن تكرار نفس الموديول أو المنتج داخل عرض المبيعات.');if($this->input('commission_type')==='percentage'&&(float)$this->input('commission_value',0)>100)$v->errors()->add('commission_value','نسبة عمولة الوسيط لا يمكن أن تتجاوز 100%.');ProductDependencyRules::validateSelection($v,$this->input('items',[]));}]; }
}
