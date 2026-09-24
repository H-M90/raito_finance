<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Models\Station;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(private PricingCalculator $pricing, private PricingOfferResolver $offerResolver, private NumberGenerator $numbers, private ContractDateService $dates, private ReceivableService $receivables, private RecurringReceivableGenerator $recurringReceivables, private TimelineService $timeline, private DeviceQuantityService $deviceQuantities, private CustomerSuccessService $customerSuccess) {}

    public function create(array $data): Contract
    {
        return DB::transaction(function()use($data){
            $quotation=null;
            if(!empty($data['sales_quotation_id'])){
                $quotation=SalesQuotation::with('items.product','pricingOffers')->lockForUpdate()->findOrFail($data['sales_quotation_id']);
                if($quotation->status!=='accepted'||!$quotation->is_current_version) throw new \DomainException('لا يمكن تحويل إلا آخر إصدار مقبول من عرض المبيعات.');
                if((int)$quotation->customer_id!==(int)$data['customer_id']) throw new \DomainException('عرض المبيعات لا يخص العميل المختار.');
                if($quotation->contract()->exists()||$quotation->converted_at) throw new \DomainException('تم تحويل عرض المبيعات إلى عقد من قبل.');
            }
            $customer=Customer::findOrFail($data['customer_id']);
            $this->customerSuccess->ensureProfile($customer);
            $contract=$this->persist(new Contract,$data,$quotation);
            $otherContracts=Contract::where('customer_id',$contract->customer_id)->where('id','!=',$contract->id)->where('status','!=','cancelled')->exists();
            if(! $otherContracts){$this->customerSuccess->recordEvent($customer,'contract','FIRST_CONTRACT',$contract,['contract_id'=>$contract->id]);}
            $this->customerSuccess->ensureAutomaticFollowUp($customer);
            if($quotation){$quotation->update(['status'=>'converted','converted_at'=>now(),'converted_by'=>auth()->id()]);}
            return $contract;
        });
    }

    public function update(Contract $contract, array $data): Contract
    {
        if ($contract->status === 'cancelled') throw new \DomainException('أعد فتح العقد أولًا قبل تعديله.');
        if ((int) $contract->customer_id !== (int) $data['customer_id']) throw new \DomainException('لا يمكن تغيير عميل العقد بعد إنشائه. أنشئ عقدًا جديدًا للعميل الصحيح.');
        $data['sales_quotation_id'] = $contract->sales_quotation_id;

        return DB::transaction(function () use ($contract, $data) {
            $contract->loadMissing(['items.product','installments','pricingOffers','addendums','stations']);
            $financialChanged = $this->financialFingerprintFromData($data) !== $this->financialFingerprintFromContract($contract);

            // Operational edits (domain, salesperson, notes and station distribution)
            // never rebuild line items or receivables.
            if (! $financialChanged) {
                $capacity = $this->deviceQuantities->totalCapacity($contract);
                $activityType = (string) ($data['activity_type'] ?? $contract->activity_type);
                $this->assertActivityChangeAllowed($contract, $activityType);
                $contract->update([
                    'activity_type' => $activityType,
                    'domain' => $data['domain'] ?? null,
                    'sales_owner_id' => $data['sales_owner_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
                $this->syncStations($contract, $activityType, $data['stations'] ?? [], $capacity);
                $this->timeline->record($contract->customer_id, 'contract', 'تعديل بيانات تشغيلية للعقد', $contract->number, $contract, $contract->id, route('contracts.show', $contract));
                return $contract->fresh()->load('customer','items.product','installments','receivables','pricingOffers','stations');
            }

            $activityType = (string) ($data['activity_type'] ?? $contract->activity_type);
            $this->assertActivityChangeAllowed($contract, $activityType);

            $newBase = $this->deviceQuantities->fromPayload($data['items'] ?? []);
            $activeAddendums = $this->deviceQuantities->activeAddendums($contract);
            $newCapacity = [
                'pts' => $newBase['pts'] + $activeAddendums['pts'],
                'sensor' => $newBase['sensor'] + $activeAddendums['sensor'],
            ];

            $installedPts = (int) DB::table('installation_stations')
                ->join('installations','installations.id','=','installation_stations.installation_id')
                ->where('installations.contract_id',$contract->id)
                ->where('installations.status','!=','cancelled')
                ->where('installation_stations.status','!=','cancelled')
                ->sum('installation_stations.pts_installed');
            $installedSensors = (int) DB::table('installation_stations')
                ->join('installations','installations.id','=','installation_stations.installation_id')
                ->where('installations.contract_id',$contract->id)
                ->where('installations.status','!=','cancelled')
                ->where('installation_stations.status','!=','cancelled')
                ->sum('installation_stations.sensor_count');
            if ($installedPts > $newCapacity['pts'] || $installedSensors > $newCapacity['sensor']) {
                throw new \DomainException('لا يمكن تقليل بنود PTS أو الحساسات عن الكميات المركبة فعليًا.');
            }

            if ($activityType === 'stations') {
                $requestedStations = collect($data['stations'] ?? []);
                $allocatedPts = (int) $requestedStations->sum(fn ($row) => ! empty($row['pts_count']) ? 1 : 0);
                $allocatedSensors = (int) $requestedStations->sum(fn ($row) => max(0, (int) ($row['sensor_count'] ?? 0)));
                if ($allocatedPts > $newCapacity['pts'] || $allocatedSensors > $newCapacity['sensor']) {
                    throw new \DomainException('لا يمكن أن يتجاوز توزيع المحطات كميات بنود PTS والحساسات بعد التعديل.');
                }
            }

            $ownReceivables = $contract->receivables()->whereNull('contract_addendum_id');
            $hasMovements = (clone $ownReceivables)
                ->where(fn ($q) => $q->where('collected_amount','>',0)->orWhere('discounted_amount','>',0))
                ->exists();
            if ($hasMovements) throw new \DomainException('لا يمكن تعديل القيم المالية لعقد عليه تحصيل أو سند خصم. يمكنك تعديل الدومين والمندوب والمحطات والملاحظات فقط.');

            $hasGeneratedRecurring = (clone $ownReceivables)->whereIn('type', ['maintenance','monthly','annual'])->exists();
            if ($hasGeneratedRecurring) throw new \DomainException('لا يمكن إعادة تسعير عقد تم توليد استحقاقات صيانة أو اشتراك له. استخدم ملحق عقد أو صحح الاستحقاقات أولًا.');
            if ($contract->items->contains(fn ($item) => $item->stopped_at !== null)) {
                throw new \DomainException('لا يمكن إعادة بناء بنود عقد يحتوي على بنود موقوفة لأن ذلك يمسح تاريخ الإيقاف. استخدم دورة إيقاف البنود أو ملحق عقد بدلًا من إعادة التسعير.');
            }

            $ownReceivableIds = (clone $ownReceivables)->pluck('id');
            \App\Models\CustomerLedgerEntry::where('contract_id',$contract->id)
                ->where('source_type',\App\Models\Receivable::class)
                ->whereIn('source_id',$ownReceivableIds)
                ->delete();
            (clone $ownReceivables)->forceDelete();
            $contract->installments()->delete();
            $contract->items()->delete();
            $contract->pricingOffers()->detach();

            return $this->persist($contract, $data, null);
        });
    }

    private function financialFingerprintFromData(array $data): string
    {
        $norm = static fn ($value, int $decimals = 2) => number_format((float) ($value ?? 0), $decimals, '.', '');
        $date = static fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->toDateString() : null;
        $items = collect($data['items'] ?? [])->map(fn ($row) => [
            'product_id' => (int) ($row['product_id'] ?? 0),
            'quantity' => $norm($row['quantity'] ?? 1, 3),
            'requested_users' => (int) ($row['requested_users'] ?? 0),
            'user_unit_price' => $norm($row['user_unit_price'] ?? 0),
            'unit_price' => $norm($row['unit_price'] ?? 0),
            'discount_value' => $norm($row['discount_value'] ?? 0),
            'maintenance_rate' => $norm($row['maintenance_rate'] ?? 0, 4),
            'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
        ])->values()->all();
        $installments = collect($data['installments'] ?? [])->filter(fn ($row) => filled($row['name'] ?? null) || filled($row['due_date'] ?? null))->map(fn ($row) => [
            'name' => trim((string) ($row['name'] ?? '')),
            'percentage' => filled($row['percentage'] ?? null) ? $norm($row['percentage'], 4) : null,
            'due_date' => $date($row['due_date'] ?? null),
            'net_amount' => $norm($row['net_amount'] ?? 0),
            'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
        ])->values()->all();
        $offerIds = collect($data['pricing_offer_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();

        return hash('sha256', json_encode([
            'contract_date' => $date($data['contract_date'] ?? null),
            'service_start_date' => $date($data['service_start_date'] ?? null),
            'billing_cycle' => (string) ($data['billing_cycle'] ?? ''),
            'currency' => (string) ($data['currency'] ?? ''),
            'intermediary_id' => filled($data['intermediary_id'] ?? null) ? (int) $data['intermediary_id'] : null,
            'commission_type' => filled($data['commission_type'] ?? null) ? (string) $data['commission_type'] : null,
            'commission_value' => $norm($data['commission_value'] ?? 0, 4),
            'calculation_start_date' => $date($data['calculation_start_date'] ?? $data['service_start_date'] ?? null),
            'maintenance_paid_until' => $date($data['maintenance_paid_until'] ?? null),
            'opening_receivable_balance' => $norm($data['opening_receivable_balance'] ?? 0),
            'opening_maintenance_balance' => $norm($data['opening_maintenance_balance'] ?? 0),
            'previous_collections_total' => $norm($data['previous_collections_total'] ?? 0),
            'is_imported' => ! empty($data['is_imported']),
            'manual_contract_total' => ! empty($data['is_imported']) ? $norm($data['manual_contract_total'] ?? 0) : null,
            'items' => $items,
            'installments' => $installments,
            'pricing_offer_ids' => $offerIds,
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function financialFingerprintFromContract(Contract $contract): string
    {
        return $this->financialFingerprintFromData([
            'contract_date' => $contract->contract_date,
            'service_start_date' => $contract->service_start_date,
            'billing_cycle' => $contract->billing_cycle,
            'currency' => $contract->currency,
            'intermediary_id' => $contract->intermediary_id,
            'commission_type' => $contract->commission_type,
            'commission_value' => $contract->commission_value,
            'calculation_start_date' => $contract->calculation_start_date,
            'maintenance_paid_until' => $contract->maintenance_paid_until,
            'opening_receivable_balance' => $contract->opening_receivable_balance,
            'opening_maintenance_balance' => $contract->opening_maintenance_balance,
            'previous_collections_total' => $contract->previous_collections_total,
            'is_imported' => $contract->is_imported,
            'manual_contract_total' => $contract->is_imported ? $contract->grand_total : null,
            'items' => $contract->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->product?->type === 'erp_module' ? 1 : $item->quantity,
                'requested_users' => $item->requested_users,
                'user_unit_price' => $item->user_unit_price,
                'unit_price' => $item->unit_price,
                'discount_value' => $item->discount_value,
                'maintenance_rate' => $item->maintenance_rate,
                'notes' => $item->notes,
            ])->all(),
            'installments' => $contract->installments->map(fn ($row) => [
                'name' => $row->name,
                'percentage' => $row->percentage,
                'due_date' => $row->due_date,
                'net_amount' => $row->net_amount,
                'notes' => $row->notes,
            ])->all(),
            'pricing_offer_ids' => $contract->pricingOffers->pluck('id')->all(),
        ]);
    }

    private function persist(Contract $contract,array $data,?SalesQuotation $quotation): Contract
    {
        $wasExisting = $contract->exists;
        if (empty($data['intermediary_id'])) { $data['commission_type']=null; $data['commission_value']=0; $data['commission_due_basis']=null; }
        $customer=Customer::findOrFail($data['customer_id']);
        $taxRate=$quotation ? (float)$quotation->tax_rate : (float)config('finance.tax_rate',15);
        if($quotation){
            $snapshot=$quotation->pricing_snapshot?:[]; $totals=$snapshot['totals']??[];
            $calculatedItems=$quotation->items->map(fn($i)=>Arr::except($i->getAttributes(),['id','sales_quotation_id','created_at','updated_at']))->all();
            $offers=$quotation->pricingOffers;
            $deviceCounts=$this->deviceQuantities->fromItemCollection($quotation->items);
            foreach(['subtotal','discountTotal','promotionalDiscountTotal','netTotal','taxTotal','grandTotal','maintenanceTotal','commissionTotal'] as $key) if(!array_key_exists($key,$totals)) throw new \DomainException('نسخة تسعير عرض المبيعات غير مكتملة. أعد حفظ العرض قبل التحويل.');
        } else {
            $offers=!empty($data['is_imported'])?collect():$this->offerResolver->resolve($data['pricing_offer_ids']??[],$customer,$data['billing_cycle'],$data['contract_date']);
            $products=Product::whereIn('id',collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
            $items=collect($data['items'])->map(function($item)use($products){$item['product']=$products->get((int)$item['product_id']);return $item;})->all();
            $totals=$this->pricing->calculate($items,$taxRate,$data['billing_cycle'],$offers,$data['commission_type']??null,(float)($data['commission_value']??0));
            $calculatedItems=$totals['calculatedItems']; $deviceCounts=$this->deviceQuantities->fromPayload($data['items']);
            if (! empty($data['is_imported']) && filled($data['manual_contract_total'] ?? null)) {
                $grandTotal = round((float) $data['manual_contract_total'], 2);
                $netTotal = round($grandTotal / (1 + ($taxRate / 100)), 2);
                $taxTotal = round($grandTotal - $netTotal, 2);
                $commissionTotal = match ($data['commission_type'] ?? null) {
                    'percentage' => round($netTotal * ((float) ($data['commission_value'] ?? 0) / 100), 2),
                    'fixed' => round(max(0, (float) ($data['commission_value'] ?? 0)), 2),
                    default => 0.0,
                };
                $totals = array_merge($totals, [
                    'subtotal' => $netTotal,
                    'manualDiscountTotal' => 0.0,
                    'promotionalDiscountTotal' => 0.0,
                    'discountTotal' => 0.0,
                    'netTotal' => $netTotal,
                    'taxTotal' => $taxTotal,
                    'grandTotal' => $grandTotal,
                    'commissionTotal' => $commissionTotal,
                ]);
            }
            $snapshot=$this->pricing->snapshot($totals,$offers,$products,$data['billing_cycle'],$data['currency']);
            if (! empty($data['is_imported']) && filled($data['manual_contract_total'] ?? null)) {
                $snapshot['pricing_mode'] = 'legacy_manual_total';
                $snapshot['manual_contract_total'] = (float) $data['manual_contract_total'];
            }
        }
        $calculatedItems=collect($calculatedItems)->map(fn($row)=>array_merge($row,[
            'active_from'=>$data['service_start_date'],
            'stopped_at'=>null,
            'status'=>'active',
            'stop_reason'=>null,
            'stopped_by'=>null,
        ]))->all();
        $installments=collect($data['installments']??[])->filter(fn($row)=>filled($row['name']??null)&&filled($row['due_date']??null)&&(float)($row['net_amount']??0)>0);
        $installmentTotal=round($installments->sum(fn($row)=>(float)($row['net_amount']??0)),2);
        if($data['billing_cycle']==='one_time'&&empty($data['is_imported'])&&($installments->isEmpty()||abs($installmentTotal-(float)$totals['netTotal'])>0.01)) throw new \DomainException('العقد ذو الدفع مرة واحدة يجب أن يحتوي على دفعات تغطي صافي قيمة العقد بالكامل.');
        $calculationStart=$data['calculation_start_date']??$data['service_start_date'];
        if (\Illuminate\Support\Carbon::parse($calculationStart)->startOfDay()->lt(\Illuminate\Support\Carbon::parse($data['service_start_date'])->startOfDay())) {
            throw new \DomainException('تاريخ بداية الاحتساب لا يمكن أن يسبق تاريخ بداية خدمة العقد.');
        }
        $nextMaintenance=$data['billing_cycle']==='one_time'&&(float)$totals['maintenanceTotal']>0?$this->dates->nextMaintenanceDate($data['service_start_date'],$calculationStart,$data['maintenance_paid_until']??null):null;
        $nextBilling=$this->dates->nextBillingDate($data['billing_cycle'],$data['service_start_date'],$calculationStart);
        $contract->fill(array_merge(Arr::except($data,['number','items','installments','pricing_offer_ids','attachment','status','pts_count','sensor_count','stations','has_intermediary','manual_contract_total']),[
            'number'=>$contract->exists?$contract->number:(!empty($data['is_imported'])&&filled($data['number']??null)?$data['number']:$this->numbers->unique('contracts','CON')),'status'=>'active','tax_rate'=>$taxRate,'pts_count'=>$deviceCounts['pts'],'sensor_count'=>$deviceCounts['sensor'],
            'subtotal'=>$totals['subtotal'],'discount_total'=>$totals['discountTotal'],'promotional_discount_total'=>$totals['promotionalDiscountTotal'],
            'net_total'=>$totals['netTotal'],'tax_total'=>$totals['taxTotal'],'grand_total'=>$totals['grandTotal'],'maintenance_total'=>$data['billing_cycle']==='one_time'?$totals['maintenanceTotal']:0,
            'commission_total'=>$totals['commissionTotal'],'commission_due_basis'=>!empty($data['intermediary_id'])&&$totals['commissionTotal']>0?'collection':null,'pricing_snapshot'=>$snapshot,'calculation_start_date'=>$calculationStart,'next_maintenance_date'=>$nextMaintenance,'next_billing_date'=>$nextBilling,
            'created_by'=>$contract->created_by?:auth()->id(),'updated_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,
        ])); $contract->save();
        $contract->items()->createMany($calculatedItems); $contract->pricingOffers()->sync($offers->pluck('id'));
        $stationCapacity = $deviceCounts;
        if ($wasExisting) {
            $addendumCapacity = $this->deviceQuantities->activeAddendums($contract);
            $stationCapacity = ['pts' => $deviceCounts['pts'] + $addendumCapacity['pts'], 'sensor' => $deviceCounts['sensor'] + $addendumCapacity['sensor']];
        }
        $this->syncStations($contract, (string) $data['activity_type'], $data['stations'] ?? [], $stationCapacity);
        foreach($installments->values() as $index=>$row){$net=round((float)$row['net_amount'],2);$tax=round($net*($taxRate/100),2);$inst=$contract->installments()->create(['name'=>$row['name'],'percentage'=>$row['percentage']??null,'due_date'=>$row['due_date'],'net_amount'=>$net,'tax_amount'=>$tax,'total_amount'=>$net+$tax,'sort_order'=>$index+1,'notes'=>$row['notes']??null]);$this->receivables->fromInstallment($contract,$inst);}
        $this->receivables->openingBalance($contract,'opening_contract',(float)($contract->opening_receivable_balance??0)); $this->receivables->openingBalance($contract,'opening_maintenance',(float)($contract->opening_maintenance_balance??0));
        // Any monthly/annual subscription or maintenance inside the configured
        // lead window is created immediately on save. The scheduler keeps future periods current.
        $this->recurringReceivables->generateForContract($contract);
        $this->timeline->record($contract->customer_id,'contract',$contract->wasRecentlyCreated?'إنشاء عقد':'تعديل عقد',"{$contract->number} · ".number_format((float)$contract->grand_total,2).' '.$contract->currency,$contract,$contract->id,route('contracts.show',$contract),$contract->contract_date);
        return $contract->load('customer','items.product','installments','receivables','pricingOffers','stations');
    }

    public function generateHistoricalReceivables(Contract $contract, string $fromDate, ?string $untilDate = null): int
    {
        if ($contract->status !== 'active') throw new \DomainException('لا يمكن توليد استحقاقات لعقد ملغي.');

        $from = \Illuminate\Support\Carbon::parse($fromDate)->startOfDay();
        $until = \Illuminate\Support\Carbon::parse($untilDate ?: today())->startOfDay();
        if ($from->lt($contract->service_start_date->copy()->startOfDay())) {
            throw new \DomainException('بداية التوليد لا يمكن أن تسبق تاريخ بداية خدمة العقد.');
        }
        if ($until->gt(today())) throw new \DomainException('التوليد التاريخي لا يمكن أن يتجاوز تاريخ اليوم.');
        if ($until->lt($from)) throw new \DomainException('تاريخ نهاية التوليد يجب أن يكون بعد أو مساويًا لتاريخ البداية.');

        return DB::transaction(function () use ($contract, $from, $until) {
            $locked = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $currentStart = $locked->calculation_start_date?->copy()->startOfDay();
            if (! $currentStart || $from->lt($currentStart)) {
                $locked->update(['calculation_start_date' => $from->toDateString(), 'updated_by' => auth()->id()]);
            }

            $created = $this->recurringReceivables->generateHistoricalForContract($locked->fresh('items'), $from, $until);
            $this->timeline->record(
                $locked->customer_id,
                'contract',
                'توليد استحقاقات تاريخية',
                $locked->number.' · من '.$from->toDateString().' إلى '.$until->toDateString().' · '.$created.' استحقاق',
                $locked,
                $locked->id,
                route('contracts.show', $locked),
                $until,
            );

            return $created;
        });
    }

    public function cancel(Contract $contract,string $reason): Contract
    {
        if($contract->status==='cancelled') return $contract;
        if($contract->receivables()->where(fn($q)=>$q->where('collected_amount','>',0)->orWhere('discounted_amount','>',0))->exists()) throw new \DomainException('لا يمكن إلغاء عقد عليه تحصيلات أو سندات خصم. ألغِ الحركات أولًا.');
        $hasActualInstallation = DB::table('installation_stations')
            ->join('installations','installations.id','=','installation_stations.installation_id')
            ->where('installations.contract_id',$contract->id)
            ->where('installations.status','!=','cancelled')
            ->where('installation_stations.status','!=','cancelled')
            ->where(fn($query)=>$query->where('installation_stations.pts_installed',true)->orWhere('installation_stations.sensor_count','>',0))
            ->exists();
        if($hasActualInstallation) throw new \DomainException('لا يمكن إلغاء عقد عليه تركيب فعلي. ألغِ عمليات التركيب أولًا للحفاظ على تطابق السعة والتشغيل.');
        return DB::transaction(function()use($contract,$reason){
            $autoReason = 'إلغاء تلقائي بسبب إلغاء العقد: '.$reason;
            $contract->addendums()->where('status','active')->get()->each(fn($addendum) => $addendum->update([
                'status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),
                'cancellation_reason'=>$autoReason,'updated_by'=>auth()->id(),
            ]));
            $contract->receivables()->get()->each(fn($receivable) => $receivable->update(['status'=>'cancelled']));
            $contract->ledgerEntries()->get()->each(fn($entry) => $entry->update(['is_reversed'=>true]));
            $contract->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason,'updated_by'=>auth()->id()]);
            $this->timeline->record($contract->customer_id,'contract','إلغاء عقد',"{$contract->number} · {$reason}",$contract,$contract->id,route('contracts.show',$contract));
            return $contract;
        });
    }
    public function reopen(Contract $contract): Contract
    {
        if($contract->status!=='cancelled') return $contract;
        return DB::transaction(function()use($contract){
            $contract->update(['status'=>'active','reopened_at'=>now(),'reopened_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id()]);
            $contract->addendums()->where('status','cancelled')->where('cancellation_reason','like','إلغاء تلقائي بسبب إلغاء العقد:%')->get()->each(fn($addendum) => $addendum->update([
                'status'=>'active','reopened_at'=>now(),'reopened_by'=>auth()->id(),
                'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id(),
            ]));
            $contract->ledgerEntries()->get()->each(fn($entry) => $entry->update(['is_reversed'=>false]));
            $contract->receivables()->get()->each->refreshStatus();
            $this->recurringReceivables->generateForContract($contract->fresh());
            $contract->addendums()->where('status','active')->get()->each(fn($addendum)=>$this->recurringReceivables->generateForAddendum($addendum));
            $this->timeline->record($contract->customer_id,'contract','إعادة فتح عقد',$contract->number,$contract,$contract->id,route('contracts.show',$contract));
            return $contract;
        });
    }
    private function assertActivityChangeAllowed(Contract $contract, string $newActivityType): void
    {
        if ($contract->activity_type === $newActivityType || $newActivityType === 'stations') return;

        $hasInstalledStations = DB::table('installation_stations')
            ->join('installations', 'installations.id', '=', 'installation_stations.installation_id')
            ->where('installations.contract_id', $contract->id)
            ->where('installations.status', '!=', 'cancelled')
            ->where('installation_stations.status', '!=', 'cancelled')
            ->where(fn ($query) => $query->where('installation_stations.pts_installed', true)->orWhere('installation_stations.sensor_count', '>', 0))
            ->exists();

        if ($hasInstalledStations) {
            throw new \DomainException('لا يمكن تغيير عقد محطات إلى نشاط آخر بعد وجود تركيب فعلي على محطاته.');
        }
    }

    private function syncStations(Contract $contract, string $activityType, array $rows, array $deviceCapacity): void
    {
        if ($activityType !== 'stations') {
            $this->assertActivityChangeAllowed($contract, $activityType);
            $contract->stations()->sync([]);
            return;
        }

        $installedByStation = DB::table('installation_stations')
            ->join('installations', 'installations.id', '=', 'installation_stations.installation_id')
            ->where('installations.contract_id', $contract->id)
            ->where('installations.status', '!=', 'cancelled')
            ->where('installation_stations.status', '!=', 'cancelled')
            ->selectRaw('installation_stations.station_id, SUM(CASE WHEN installation_stations.pts_installed = 1 THEN 1 ELSE 0 END) AS pts_total, SUM(installation_stations.sensor_count) AS sensor_total')
            ->groupBy('installation_stations.station_id')
            ->get()->keyBy('station_id');

        $sync = [];
        $counter = 1;
        $allocatedPts = 0;
        $allocatedSensors = 0;

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];
            $stationId = ! empty($row['id']) ? (int) $row['id'] : null;
            $ptsCount = ! empty($row['pts_count']) ? 1 : 0;
            $sensorCount = max(0, (int) ($row['sensor_count'] ?? 0));

            $hasAnyData = $stationId || $ptsCount > 0 || $sensorCount > 0 || collect([
                'name','city','location','contact_name','phone','relationship_start_date','notes',
            ])->contains(fn ($field) => filled($row[$field] ?? null));
            if (! $hasAnyData) continue;

            $allocatedPts += $ptsCount;
            $allocatedSensors += $sensorCount;

            if ($allocatedPts > (int) ($deviceCapacity['pts'] ?? 0)) {
                throw new \DomainException('إجمالي PTS الموزع على المحطات أكبر من كمية بنود PTS في العقد.');
            }
            if ($allocatedSensors > (int) ($deviceCapacity['sensor'] ?? 0)) {
                throw new \DomainException('إجمالي الحساسات الموزع على المحطات أكبر من كمية بنود الحساسات في العقد.');
            }

            if ($stationId) {
                $station = Station::findOrFail($stationId);
                if ((int) $station->customer_id !== (int) $contract->customer_id) {
                    throw new \DomainException('إحدى المحطات المختارة لا تخص عميل العقد.');
                }
                $installed = $installedByStation->get($stationId);
                $installedPts = (int) ($installed?->pts_total ?? 0);
                $installedSensors = (int) ($installed?->sensor_total ?? 0);
                if ($ptsCount < min(1, $installedPts)) {
                    throw new \DomainException('لا يمكن إزالة تخصيص PTS من محطة تم تركيب PTS عليها بالفعل.');
                }
                if ($sensorCount < $installedSensors) {
                    throw new \DomainException('لا يمكن تقليل حساسات محطة عن العدد المركب فعليًا عليها.');
                }

                $updates = collect(['name','city','location','contact_name','phone','relationship_start_date','notes'])
                    ->mapWithKeys(fn ($field) => array_key_exists($field, $row)
                        ? [$field => ($row[$field] === '' ? null : $row[$field])]
                        : [])
                    ->all();
                if (array_key_exists('name', $updates) && blank($updates['name'])) unset($updates['name']);
                if ($updates) $station->update($updates);
            } else {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    do {
                        $name = 'محطة '.$counter++;
                    } while (Station::where('customer_id', $contract->customer_id)->where('name', $name)->exists());
                }

                $station = Station::create([
                    'customer_id' => $contract->customer_id,
                    'code' => $this->numbers->uniqueCode('stations', 'STA'),
                    'name' => $name,
                    'city' => filled($row['city'] ?? null) ? trim((string) $row['city']) : null,
                    'location' => filled($row['location'] ?? null) ? trim((string) $row['location']) : null,
                    'contact_name' => filled($row['contact_name'] ?? null) ? trim((string) $row['contact_name']) : null,
                    'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
                    'relationship_start_date' => $row['relationship_start_date'] ?? null,
                    'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                    'is_active' => true,
                ]);
            }

            $sync[$station->id] = ['pts_count' => $ptsCount, 'sensor_count' => $sensorCount];
        }

        $removedInstalledStation = $installedByStation->keys()->contains(fn ($stationId) => ! array_key_exists((int) $stationId, $sync));
        if ($removedInstalledStation) {
            throw new \DomainException('لا يمكن إزالة محطة من العقد بعد تنفيذ تركيب فعلي عليها.');
        }

        $contract->stations()->sync($sync);
    }

}
