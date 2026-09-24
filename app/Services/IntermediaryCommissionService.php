<?php
namespace App\Services;
use App\Models\CollectionAllocation;
use App\Models\Contract;
use App\Models\IntermediaryCommission;
use App\Models\IntermediaryCommissionPayment;
use Illuminate\Support\Facades\DB;
class IntermediaryCommissionService
{
    public function __construct(private NumberGenerator $numbers){}
    public function accrue(CollectionAllocation $allocation): ?IntermediaryCommission
    {
        $allocation->loadMissing('collection','receivable.contract'); $receivable=$allocation->receivable; $contract=$receivable?->contract;
        if(!$contract?->intermediary_id||$contract->commission_due_basis!=='collection'||!$contract->commission_type||(float)$contract->commission_value<=0) return null;
        // Contract commission is calculated on the base contract net value.
        // Maintenance and addendum collections must never consume that commission cap.
        if($receivable->contract_addendum_id || in_array($receivable->type,['maintenance','opening_maintenance'],true)) return null;
        $netBase=(float)$allocation->receivable->total_amount>0?round((float)$allocation->amount*((float)$allocation->receivable->net_amount/(float)$allocation->receivable->total_amount),2):0;
        $amount=$contract->commission_type==='percentage'?round($netBase*((float)$contract->commission_value/100),2):round((float)$contract->commission_total*($contract->net_total>0?$netBase/(float)$contract->net_total:0),2);
        $already=(float)IntermediaryCommission::where('contract_id',$contract->id)->where('status','!=','cancelled')->sum('amount');
        $amount=max(0,min($amount,(float)$contract->commission_total-$already)); if($amount<=0) return null;
        return IntermediaryCommission::create(['number'=>$this->numbers->unique('intermediary_commissions','COM'),'intermediary_id'=>$contract->intermediary_id,'contract_id'=>$contract->id,'collection_id'=>$allocation->collection_id,'collection_allocation_id'=>$allocation->id,'due_date'=>$allocation->collection->collection_date,'currency'=>$contract->currency,'basis'=>'collection','base_amount'=>$netBase,'commission_type'=>$contract->commission_type,'commission_value'=>$contract->commission_value,'amount'=>$amount,'remaining_amount'=>$amount,'status'=>'outstanding','created_by'=>auth()->id()]);
    }
    public function cancelForAllocation(CollectionAllocation $allocation): void
    {
        $commission=IntermediaryCommission::where('collection_allocation_id',$allocation->id)->first(); if(!$commission) return;
        if((float)$commission->paid_amount>0) throw new \DomainException('لا يمكن إلغاء التحصيل لأن عمولة الوسيط المرتبطة به تم صرف جزء منها.');
        $commission->update(['status'=>'cancelled','remaining_amount'=>0]);
    }
    public function reopenForAllocation(CollectionAllocation $allocation): void
    {
        $commission=IntermediaryCommission::where('collection_allocation_id',$allocation->id)->first();
        if($commission){$commission->update(['status'=>'outstanding','remaining_amount'=>$commission->amount]);return;}
        $this->accrue($allocation);
    }
    public function pay(IntermediaryCommission $commission,array $data): IntermediaryCommissionPayment
    {
        return DB::transaction(function()use($commission,$data){$commission=IntermediaryCommission::lockForUpdate()->findOrFail($commission->id);$amount=round((float)$data['amount'],2);if($commission->status==='cancelled'||$amount<=0||$amount>(float)$commission->remaining_amount) throw new \DomainException('مبلغ صرف العمولة غير صحيح.');$payment=$commission->payments()->create(['number'=>$this->numbers->unique('intermediary_commission_payments','COMP'),'payment_date'=>$data['payment_date'],'amount'=>$amount,'payment_method'=>$data['payment_method']??null,'reference_no'=>$data['reference_no']??null,'notes'=>$data['notes']??null,'created_by'=>auth()->id()]);$paid=round((float)$commission->paid_amount+$amount,2);$remaining=round((float)$commission->amount-$paid,2);$commission->update(['paid_amount'=>$paid,'remaining_amount'=>$remaining,'status'=>$remaining<=0?'paid':'partial']);return $payment;});
    }
}
