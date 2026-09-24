<?php
namespace App\Services;
use App\Models\DiscountVoucher;
use App\Models\Receivable;
use Illuminate\Support\Facades\DB;
class DiscountVoucherService
{
    public function __construct(private NumberGenerator $numbers,private LedgerService $ledger,private TimelineService $timeline){}
    public function create(array $data): DiscountVoucher { return DB::transaction(fn()=>$this->persist(new DiscountVoucher,$data)); }
    public function update(DiscountVoucher $voucher,array $data): DiscountVoucher
    {
        if($voucher->status==='cancelled')throw new \DomainException('أعد فتح سند الخصم قبل تعديله.');
        return DB::transaction(function()use($voucher,$data){$old=Receivable::lockForUpdate()->findOrFail($voucher->receivable_id);$old->decrement('discounted_amount',$voucher->amount);$old->refresh()->refreshStatus();\App\Models\CustomerLedgerEntry::where('source_type',$voucher::class)->where('source_id',$voucher->id)->get()->each(fn($entry)=>$entry->update(['is_reversed'=>true]));return $this->persist($voucher,$data);});
    }
    private function persist(DiscountVoucher $voucher,array $data): DiscountVoucher
    {
        $receivable=Receivable::lockForUpdate()->findOrFail($data['receivable_id']);$amount=round((float)$data['amount'],2);
        if((int)$receivable->customer_id!==(int)$data['customer_id']||$receivable->currency!==$data['currency'])throw new \DomainException('الاستحقاق لا يخص العميل أو العملة المختارة.');
        if($amount<=0||$amount>(float)$receivable->remaining_amount)throw new \DomainException('قيمة سند الخصم أكبر من المبلغ المستحق.');
        $voucher->fill(array_merge($data,['number'=>$voucher->exists?$voucher->number:$this->numbers->unique('discount_vouchers','DSC'),'contract_id'=>$receivable->contract_id,'status'=>'active','created_by'=>$voucher->created_by?:auth()->id(),'updated_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null]));$voucher->save();$receivable->increment('discounted_amount',$amount);$receivable->refresh()->refreshStatus();$this->ledger->postDiscountVoucher($voucher);$this->timeline->record($voucher->customer_id,'discount_voucher',$voucher->wasRecentlyCreated?'إنشاء سند خصم':'تعديل سند خصم',"{$voucher->number} · ".number_format($amount,2).' '.$voucher->currency,$voucher,$voucher->contract_id,route('discount-vouchers.index',['q'=>$voucher->number]),$voucher->voucher_date);return $voucher;
    }
    public function cancel(DiscountVoucher $voucher,string $reason): DiscountVoucher
    {
        if($voucher->status==='cancelled')return $voucher;return DB::transaction(function()use($voucher,$reason){$r=Receivable::lockForUpdate()->findOrFail($voucher->receivable_id);$r->decrement('discounted_amount',$voucher->amount);$r->refresh()->refreshStatus();\App\Models\CustomerLedgerEntry::where('source_type',$voucher::class)->where('source_id',$voucher->id)->get()->each(fn($entry)=>$entry->update(['is_reversed'=>true]));$voucher->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason,'updated_by'=>auth()->id()]);$this->timeline->record($voucher->customer_id,'discount_voucher','إلغاء سند خصم',"{$voucher->number} · {$reason}",$voucher,$voucher->contract_id,route('discount-vouchers.index',['q'=>$voucher->number]));return $voucher;});
    }
    public function reopen(DiscountVoucher $voucher): DiscountVoucher
    {
        if($voucher->status!=='cancelled')return $voucher;return DB::transaction(function()use($voucher){$r=Receivable::lockForUpdate()->findOrFail($voucher->receivable_id);if((float)$voucher->amount>(float)$r->remaining_amount)throw new \DomainException('لا يمكن إعادة فتح سند الخصم لأن الرصيد المستحق لم يعد كافيًا.');$r->increment('discounted_amount',$voucher->amount);$r->refresh()->refreshStatus();$voucher->update(['status'=>'active','cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id()]);$this->ledger->postDiscountVoucher($voucher);$this->timeline->record($voucher->customer_id,'discount_voucher','إعادة فتح سند خصم',$voucher->number,$voucher,$voucher->contract_id,route('discount-vouchers.index',['q'=>$voucher->number]));return $voucher;});
    }
}
