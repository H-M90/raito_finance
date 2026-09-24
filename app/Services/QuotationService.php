<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(private PricingCalculator $pricing, private PricingOfferResolver $offerResolver, private NumberGenerator $numbers, private TimelineService $timeline, private DeviceQuantityService $deviceQuantities) {}

    public function create(array $data): SalesQuotation { return DB::transaction(fn()=>$this->persist(new SalesQuotation,$data)); }
    public function update(SalesQuotation $quotation,array $data): SalesQuotation
    {
        if ($quotation->status === 'converted') throw new \DomainException('لا يمكن تعديل عرض تم تحويله إلى عقد.');
        if ($quotation->status !== 'draft') throw new \DomainException('لا يمكن تعديل محتوى عرض تم إرساله أو التفاوض عليه. أنشئ إصدارًا جديدًا للحفاظ على النسخة السابقة.');
        return DB::transaction(fn()=>$this->persist($quotation,$data));
    }

    private function persist(SalesQuotation $quotation,array $data): SalesQuotation
    {
        if (empty($data['intermediary_id'])) { $data['commission_type']=null; $data['commission_value']=0; $data['commission_due_basis']=null; }
        $customer=Customer::findOrFail($data['customer_id']);
        $offers=$this->offerResolver->resolve($data['pricing_offer_ids']??[],$customer,$data['billing_cycle'],$data['quotation_date']);
        $products=Product::whereIn('id',collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        if($products->count()!==collect($data['items'])->pluck('product_id')->unique()->count()) throw new \DomainException('يوجد منتج غير صحيح أو مكرر داخل العرض.');
        $items=collect($data['items'])->map(function($item)use($products){$item['product']=$products->get((int)$item['product_id']);return $item;})->all();
        $taxRate=(float)config('finance.tax_rate',15);
        $totals=$this->pricing->calculate($items,$taxRate,$data['billing_cycle'],$offers,$data['commission_type']??null,(float)($data['commission_value']??0));
        $snapshot=$this->pricing->snapshot($totals,$offers,$products,$data['billing_cycle'],$data['currency']);
        $deviceCounts=$this->deviceQuantities->fromPayload($data['items']);
        $quotation->fill(array_merge(Arr::except($data,['items','pricing_offer_ids','pts_count','sensor_count','has_intermediary','commission_due_basis']),[
            'number'=>$quotation->exists?$quotation->number:$this->numbers->unique('sales_quotations','QT'),'tax_rate'=>$taxRate,'pts_count'=>$deviceCounts['pts'],'sensor_count'=>$deviceCounts['sensor'],
            'subtotal'=>$totals['subtotal'],'discount_total'=>$totals['discountTotal'],'promotional_discount_total'=>$totals['promotionalDiscountTotal'],
            'net_total'=>$totals['netTotal'],'tax_total'=>$totals['taxTotal'],'grand_total'=>$totals['grandTotal'],
            'maintenance_total'=>$data['billing_cycle']==='one_time'?$totals['maintenanceTotal']:0,'commission_total'=>$totals['commissionTotal'],
            'pricing_snapshot'=>$snapshot,'created_by'=>$quotation->created_by?:auth()->id(),'updated_by'=>auth()->id(),
        ]));
        $quotation->save(); $quotation->items()->delete(); $quotation->items()->createMany($totals['calculatedItems']); $quotation->pricingOffers()->sync($offers->pluck('id'));
        $this->timeline->record($quotation->customer_id,'quotation',$quotation->wasRecentlyCreated?'إنشاء عرض مبيعات':'تعديل عرض مبيعات',"{$quotation->number} · ".number_format((float)$quotation->grand_total,2).' '.$quotation->currency,$quotation,null,route('quotations.show',$quotation),$quotation->quotation_date);
        return $quotation->load('customer','items.product','pricingOffers');
    }

    public function revise(SalesQuotation $quotation): SalesQuotation
    {
        if($quotation->status==='converted') throw new \DomainException('لا يمكن إنشاء إصدار من عرض تم تحويله.');
        if (! $quotation->is_current_version) throw new \DomainException('أنشئ الإصدار الجديد من آخر إصدار فقط للحفاظ على تسلسل النسخ.');
        return DB::transaction(function()use($quotation){
            $quotation->loadMissing('items','pricingOffers'); $rootId=$quotation->parent_quotation_id?:$quotation->id;
            SalesQuotation::where(fn($q)=>$q->whereKey($rootId)->orWhere('parent_quotation_id',$rootId))->get()->each(fn($version)=>$version->update(['is_current_version'=>false,'updated_by'=>auth()->id()]));
            $new=$quotation->replicate(['number','status','sent_at','converted_at','converted_by','rejection_reason','lost_to_competitor']);
            $new->number=$this->numbers->unique('sales_quotations','QT'); $new->parent_quotation_id=$rootId;
            $new->version_number=SalesQuotation::where(fn($q)=>$q->whereKey($rootId)->orWhere('parent_quotation_id',$rootId))->max('version_number')+1;
            $new->is_current_version=true; $new->status='draft'; $new->created_by=auth()->id(); $new->updated_by=auth()->id(); $new->save();
            $new->items()->createMany($quotation->items->map(fn($i)=>Arr::except($i->getAttributes(),['id','sales_quotation_id','created_at','updated_at']))->all());
            $new->pricingOffers()->sync($quotation->pricingOffers->pluck('id'));
            $this->timeline->record($new->customer_id,'quotation','إنشاء إصدار جديد من العرض',"{$new->number} · الإصدار {$new->version_number}",$new,null,route('quotations.show',$new));
            return $new;
        });
    }
}
