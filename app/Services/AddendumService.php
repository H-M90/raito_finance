<?php
namespace App\Services;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
class AddendumService
{
    public function __construct(private PricingCalculator $pricing,private PricingOfferResolver $offerResolver,private NumberGenerator $numbers,private ContractDateService $dates,private ReceivableService $receivables, private RecurringReceivableGenerator $recurringReceivables, private TimelineService $timeline, private DeviceQuantityService $deviceQuantities) {}
    public function create(Contract $contract,array $data): ContractAddendum { return DB::transaction(fn()=>$this->persist(new ContractAddendum,$contract,$data)); }
    public function update(ContractAddendum $addendum,array $data): ContractAddendum
    {
        if($addendum->status==='cancelled') throw new \DomainException('أعد فتح الملحق أولًا قبل تعديله.');
        if($addendum->receivables()->where(fn($q)=>$q->where('collected_amount','>',0)->orWhere('discounted_amount','>',0))->exists()) throw new \DomainException('لا يمكن تعديل ملحق عليه تحصيل أو سند خصم.');
        $contract=$addendum->contract;
        $base=$this->deviceQuantities->contractBase($contract);
        $other=$this->deviceQuantities->activeAddendums($contract,$addendum->id);
        $new=$this->deviceQuantities->fromPayload($data['items']??[]);
        $installedPts=(int)DB::table('installation_stations')->join('installations','installations.id','=','installation_stations.installation_id')->where('installations.contract_id',$contract->id)->where('installations.status','!=','cancelled')->where('installation_stations.status','!=','cancelled')->sum('installation_stations.pts_installed');
        $installedSensors=(int)DB::table('installation_stations')->join('installations','installations.id','=','installation_stations.installation_id')->where('installations.contract_id',$contract->id)->where('installations.status','!=','cancelled')->where('installation_stations.status','!=','cancelled')->sum('installation_stations.sensor_count');
        $newPtsCapacity=$base['pts']+$other['pts']+$new['pts'];$newSensorCapacity=$base['sensor']+$other['sensor']+$new['sensor'];
        if($installedPts>$newPtsCapacity||$installedSensors>$newSensorCapacity) throw new \DomainException('لا يمكن تقليل كميات PTS أو الحساسات في بنود الملحق عن الكميات المركبة فعليًا.');
        $allocatedPts=(int)DB::table('contract_station')->where('contract_id',$contract->id)->sum('pts_count');
        $allocatedSensors=(int)DB::table('contract_station')->where('contract_id',$contract->id)->sum('sensor_count');
        if($allocatedPts>$newPtsCapacity||$allocatedSensors>$newSensorCapacity) throw new \DomainException('لا يمكن تقليل كميات الملحق عن الأجهزة الموزعة على المحطات. عدّل توزيع المحطات أولًا.');
        return DB::transaction(function()use($addendum,$data){\App\Models\CustomerLedgerEntry::whereIn('source_id',$addendum->receivables()->pluck('id'))->where('source_type',\App\Models\Receivable::class)->delete();$addendum->receivables()->forceDelete();$addendum->installments()->delete();$addendum->items()->delete();$addendum->pricingOffers()->detach();return $this->persist($addendum,$addendum->contract,$data);});
    }
    private function persist(ContractAddendum $addendum,Contract $contract,array $data): ContractAddendum
    {
        abort_if($contract->status==='cancelled',422,'لا يمكن إضافة أو تعديل ملحق لعقد ملغي.'); $contract->loadMissing('customer');
        $offers=$this->offerResolver->resolve($data['pricing_offer_ids']??[],$contract->customer,$contract->billing_cycle,$data['addendum_date']);
        $products=Product::whereIn('id',collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        $items=collect($data['items'])->map(function($i)use($products){$i['product']=$products->get((int)$i['product_id']);return $i;})->all();
        $deviceCounts=$this->deviceQuantities->fromPayload($data['items']);
        $totals=$this->pricing->calculate($items,(float)$contract->tax_rate,$contract->billing_cycle,$offers);
        $snapshot=$this->pricing->snapshot($totals,$offers,$products,$contract->billing_cycle,$contract->currency);
        $installments=collect($data['installments']??[])->filter(fn($r)=>filled($r['name']??null)&&filled($r['due_date']??null));
        $sum=round($installments->sum(fn($r)=>(float)($r['net_amount']??0)),2);
        if($contract->billing_cycle==='one_time'&&empty($data['is_imported'])&&($installments->isEmpty()||abs($sum-(float)$totals['netTotal'])>0.01)) throw new \DomainException('الملحق يجب أن يحتوي على دفعات تغطي صافي قيمته بالكامل.');
        $addendum->fill(array_merge(Arr::except($data,['items','installments','pricing_offer_ids','attachment','status','is_imported','pts_count','sensor_count']),[
            'number'=>$addendum->exists?$addendum->number:$this->numbers->unique('contract_addendums','ADD'),'contract_id'=>$contract->id,'status'=>'active','pts_count'=>$deviceCounts['pts'],'sensor_count'=>$deviceCounts['sensor'],
            'subtotal'=>$totals['subtotal'],'discount_total'=>$totals['discountTotal'],'promotional_discount_total'=>$totals['promotionalDiscountTotal'],'net_total'=>$totals['netTotal'],
            'tax_total'=>$totals['taxTotal'],'grand_total'=>$totals['grandTotal'],'maintenance_total'=>$contract->billing_cycle==='one_time'?$totals['maintenanceTotal']:0,
            'pricing_snapshot'=>$snapshot,'next_maintenance_date'=>$contract->billing_cycle==='one_time'&&$totals['maintenanceTotal']>0?$this->dates->nextMaintenanceDate($data['service_start_date']):null,
            'created_by'=>$addendum->created_by?:auth()->id(),'updated_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,
        ]));$addendum->save();$addendum->items()->createMany($totals['calculatedItems']);$addendum->pricingOffers()->sync($offers->pluck('id'));
        foreach($installments->values() as $index=>$row){$net=round((float)$row['net_amount'],2);$tax=round($net*((float)$contract->tax_rate/100),2);$inst=$addendum->installments()->create(['name'=>$row['name'],'percentage'=>$row['percentage']??null,'due_date'=>$row['due_date'],'net_amount'=>$net,'tax_amount'=>$tax,'total_amount'=>$net+$tax,'sort_order'=>$index+1,'notes'=>$row['notes']??null]);$this->receivables->fromAddendumInstallment($contract,$addendum,$inst);}
        $this->recurringReceivables->generateForAddendum($addendum);
        $this->timeline->record($contract->customer_id,'addendum',$addendum->wasRecentlyCreated?'إنشاء ملحق عقد':'تعديل ملحق عقد',"{$addendum->number} · ".number_format((float)$addendum->grand_total,2).' '.$contract->currency,$addendum,$contract->id,route('addendums.show',[$contract,$addendum]),$addendum->addendum_date);
        return $addendum->load('contract.customer','items.product','installments','pricingOffers','receivables');
    }
    public function cancel(ContractAddendum $addendum,string $reason): ContractAddendum
    {
        if($addendum->receivables()->where(fn($q)=>$q->where('collected_amount','>',0)->orWhere('discounted_amount','>',0))->exists()) throw new \DomainException('لا يمكن إلغاء ملحق عليه تحصيل أو سند خصم.');
        $contract=$addendum->contract;
        $remaining=$this->deviceQuantities->totalCapacity($contract,$addendum->id);
        $remainingPts=$remaining['pts'];
        $remainingSensors=$remaining['sensor'];
        $installedPts=(int)DB::table('installation_stations')->join('installations','installations.id','=','installation_stations.installation_id')->where('installations.contract_id',$contract->id)->where('installations.status','!=','cancelled')->where('installation_stations.status','!=','cancelled')->sum('installation_stations.pts_installed');
        $installedSensors=(int)DB::table('installation_stations')->join('installations','installations.id','=','installation_stations.installation_id')->where('installations.contract_id',$contract->id)->where('installations.status','!=','cancelled')->where('installation_stations.status','!=','cancelled')->sum('installation_stations.sensor_count');
        if($installedPts>$remainingPts||$installedSensors>$remainingSensors) throw new \DomainException('لا يمكن إلغاء الملحق لأن جزءًا من كمياته تم تركيبه فعليًا. عدّل أو ألغِ عمليات التركيب أولًا.');
        $allocatedPts=(int)DB::table('contract_station')->where('contract_id',$contract->id)->sum('pts_count');
        $allocatedSensors=(int)DB::table('contract_station')->where('contract_id',$contract->id)->sum('sensor_count');
        if($allocatedPts>$remainingPts||$allocatedSensors>$remainingSensors) throw new \DomainException('لا يمكن إلغاء الملحق لأن كمياته موزعة على محطات العقد. عدّل توزيع المحطات أولًا.');
        return DB::transaction(function()use($addendum,$reason){$addendum->receivables()->get()->each(fn($receivable)=>$receivable->update(['status'=>'cancelled']));\App\Models\CustomerLedgerEntry::where('contract_id',$addendum->contract_id)->whereIn('source_id',$addendum->receivables()->pluck('id'))->where('source_type',\App\Models\Receivable::class)->get()->each(fn($entry)=>$entry->update(['is_reversed'=>true]));$addendum->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason,'updated_by'=>auth()->id()]);$this->timeline->record($addendum->contract->customer_id,'addendum','إلغاء ملحق عقد',"{$addendum->number} · {$reason}",$addendum,$addendum->contract_id,route('addendums.show',[$addendum->contract,$addendum]));return $addendum;});
    }
    public function reopen(ContractAddendum $addendum): ContractAddendum
    {
        $addendum->loadMissing('contract');
        if ($addendum->contract?->status !== 'active') throw new \DomainException('لا يمكن إعادة فتح ملحق بينما العقد الأساسي ملغي. أعد فتح العقد أولًا.');
        return DB::transaction(function()use($addendum){$addendum->update(['status'=>'active','reopened_at'=>now(),'reopened_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id()]);$addendum->receivables()->get()->each->refreshStatus();\App\Models\CustomerLedgerEntry::where('contract_id',$addendum->contract_id)->whereIn('source_id',$addendum->receivables()->pluck('id'))->where('source_type',\App\Models\Receivable::class)->get()->each(fn($entry)=>$entry->update(['is_reversed'=>false]));$this->recurringReceivables->generateForAddendum($addendum->fresh());$this->timeline->record($addendum->contract->customer_id,'addendum','إعادة فتح ملحق عقد',$addendum->number,$addendum,$addendum->contract_id,route('addendums.show',[$addendum->contract,$addendum]));return $addendum;});
    }
}
