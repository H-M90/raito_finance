<?php
namespace App\Services;
use App\Models\Collection;
use App\Models\Receivable;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Support\Money;
class CollectionService
{
    public function __construct(private NumberGenerator $numbers,private LedgerService $ledger,private IntermediaryCommissionService $commissions,private TimelineService $timeline){}
    public function create(array $data): Collection { return DB::transaction(fn()=>$this->persist(new Collection,$data)); }
    public function update(Collection $collection,array $data): Collection
    {
        if($collection->status==='cancelled') throw new DomainException('أعد فتح سند القبض أولًا قبل تعديله.');
        if($collection->status!=='confirmed') throw new DomainException('هذه الحركة ليست سند قبض ماليًا ولا يمكن تعديلها من شاشة سندات القبض.');
        return DB::transaction(function()use($collection,$data){$this->removeEffects($collection,true);return $this->persist($collection,$data);});
    }
    private function persist(Collection $collection,array $data): Collection
    {
        if($collection->exists && $collection->bank_statement_row_id){
            $bankRow=\App\Models\BankStatementRow::with('batch')->findOrFail($collection->bank_statement_row_id);
            $data['amount']=$bankRow->amount;$data['collection_date']=$bankRow->transaction_date->toDateString();$data['currency']=$bankRow->batch->currency;$data['reference_no']=$bankRow->reference_no;
        }
        $amountMinor=Money::minor($data['amount']); $amount=Money::decimal($amountMinor); $allocations=collect($data['allocations']??[])->filter(fn($r)=>Money::minor($r['amount']??0)>0);
        $allocatedMinor=$allocations->sum(fn($r)=>Money::minor($r['amount']??0)); $allocated=Money::decimal((int)$allocatedMinor);
        if($allocations->isEmpty()||$allocatedMinor!==$amountMinor) throw new DomainException('يجب توزيع مبلغ سند القبض بالكامل على الاستحقاقات، ولا يسمح برصيد غير موزع أو دفع زائد.');
        $collection->fill(array_merge(Arr::except($data,['allocations','status','attachment','contract_id']),['number'=>$collection->exists?$collection->number:$this->numbers->unique('collections','COL'),'allocated_amount'=>$allocated,'unallocated_amount'=>0,'status'=>'confirmed','created_by'=>$collection->created_by?:auth()->id(),'updated_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null]));$collection->save();
        foreach($allocations as $row){$receivable=Receivable::lockForUpdate()->findOrFail($row['receivable_id']);if((int)$receivable->customer_id!==(int)$collection->customer_id||$receivable->currency!==$collection->currency)throw new DomainException('لا يمكن توزيع التحصيل على استحقاق لعميل أو عملة مختلفة.');if(!empty($data['contract_id'])&&(int)$receivable->contract_id!==(int)$data['contract_id'])throw new DomainException('لا يمكن توزيع سند القبض على استحقاق من عقد آخر.');$valueMinor=Money::minor($row['amount']);$value=Money::decimal($valueMinor);if($valueMinor>Money::minor($receivable->remaining_amount))throw new DomainException('قيمة التوزيع أكبر من المبلغ المستحق. عدّل قيمة الدفعة أو التوزيع.');$allocation=$collection->allocations()->create(['receivable_id'=>$receivable->id,'amount'=>$value]);$receivable->increment('collected_amount',$value);$receivable->refresh()->refreshStatus();$this->ledger->postCollectionAllocation($allocation);$this->commissions->accrue($allocation);}
        if($collection->bank_statement_row_id){\App\Models\BankStatementRow::whereKey($collection->bank_statement_row_id)->update(['collection_customer_id'=>$collection->customer_id,'allocation_data'=>$collection->allocations()->get(['receivable_id','amount'])->map(fn($a)=>['receivable_id'=>$a->receivable_id,'amount'=>(float)$a->amount])->values()->all()]);}
        $this->timeline->record($collection->customer_id,'collection',$collection->wasRecentlyCreated?'تسجيل سند قبض':'تعديل سند قبض',"{$collection->number} · ".number_format((float)$collection->amount,2).' '.$collection->currency,$collection,null,route('collections.index',['q'=>$collection->number]),$collection->collection_date);
        return $collection->load('customer','allocations.receivable');
    }
    public function cancel(Collection $collection,string $reason): Collection
    {
        if($collection->status==='cancelled') return $collection;
        if($collection->status!=='confirmed') throw new DomainException('هذه الحركة ليست سند قبض ماليًا ولا يمكن إلغاؤها من شاشة سندات القبض.');
        if(!$collection->allocations()->exists()) throw new DomainException('لا يمكن إلغاء سند قبض بلا توزيعات مالية. راجع سلامة البيانات أولًا.');
        return DB::transaction(function()use($collection,$reason){$this->removeEffects($collection,false);$collection->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason,'updated_by'=>auth()->id()]);$this->timeline->record($collection->customer_id,'collection','إلغاء سند قبض',"{$collection->number} · {$reason}",$collection,null,route('collections.index',['q'=>$collection->number]));return $collection;});
    }
    public function reopen(Collection $collection): Collection
    {
        if($collection->status!=='cancelled') return $collection;
        if(!$collection->allocations()->exists()) throw new DomainException('لا يمكن إعادة فتح سند قبض بلا توزيعات مالية. هذا السجل ليس حركة قبض مكتملة.');
        return DB::transaction(function()use($collection){foreach($collection->allocations()->with('receivable')->get() as $allocation){$receivable=Receivable::lockForUpdate()->findOrFail($allocation->receivable_id);if(Money::minor($allocation->amount)>Money::minor($receivable->remaining_amount))throw new DomainException('لا يمكن إعادة فتح السند لأن رصيد أحد الاستحقاقات لم يعد كافيًا.');$receivable->increment('collected_amount',$allocation->amount);$receivable->refresh()->refreshStatus();$this->ledger->postCollectionAllocation($allocation);$this->commissions->reopenForAllocation($allocation);} $collection->update(['status'=>'confirmed','reopened_at'=>now(),'reopened_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id()]);$this->timeline->record($collection->customer_id,'collection','إعادة فتح سند قبض',$collection->number,$collection);return $collection;});
    }
    private function removeEffects(Collection $collection,bool $deleteAllocations): void
    {
        foreach($collection->allocations()->with('receivable')->get() as $allocation){$this->commissions->cancelForAllocation($allocation);$receivable=Receivable::lockForUpdate()->findOrFail($allocation->receivable_id);$receivable->decrement('collected_amount',$allocation->amount);$receivable->refresh()->refreshStatus();$entry=$allocation->ledgerEntry??null;\App\Models\CustomerLedgerEntry::where('source_type',$allocation::class)->where('source_id',$allocation->id)->where('entry_type','collection_allocation')->get()->each(fn($entry)=>$entry->update(['is_reversed'=>true]));if($deleteAllocations){\App\Models\IntermediaryCommission::where('collection_allocation_id',$allocation->id)->where('paid_amount',0)->delete();$allocation->delete();}}
    }
}
