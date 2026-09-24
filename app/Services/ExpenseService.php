<?php
namespace App\Services;
use App\Models\Contract;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
class ExpenseService
{
    public function __construct(private NumberGenerator $numbers,private TimelineService $timeline){}
    public function create(array $data): Expense { return DB::transaction(fn()=>$this->persist(new Expense,$data)); }
    public function update(Expense $expense,array $data): Expense { if($expense->status==='cancelled')throw new \DomainException('أعد فتح المصروف أولًا قبل تعديله.'); return DB::transaction(fn()=>$this->persist($expense,$data)); }
    private function persist(Expense $expense,array $data): Expense
    {
        if($expense->exists && $expense->bank_statement_row_id){$bankRow=\App\Models\BankStatementRow::with('batch')->findOrFail($expense->bank_statement_row_id);$data['expense_date']=$bankRow->transaction_date->toDateString();$data['amount']=$bankRow->amount;$data['currency']=$bankRow->batch->currency;$data['payment_method']='transfer';}
        if(!empty($data['contract_id'])){$contract=Contract::where('status','active')->findOrFail($data['contract_id']);if(!empty($data['customer_id'])&&(int)$data['customer_id']!==(int)$contract->customer_id)throw new \DomainException('العقد لا يخص العميل المختار.');$data['customer_id']=$contract->customer_id;$data['currency']=$contract->currency;}
        $data['currency']=$data['currency']??config('finance.default_currency','SAR');
        $expense->fill(array_merge($data,['number'=>$expense->exists?$expense->number:$this->numbers->unique('expenses','EXP'),'status'=>'approved','created_by'=>$expense->created_by?:auth()->id(),'updated_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null]));$expense->save();
        if($expense->bank_statement_row_id){\App\Models\BankStatementRow::whereKey($expense->bank_statement_row_id)->update(['expense_category_id'=>$expense->expense_category_id,'expense_description'=>$expense->description,'beneficiary'=>$expense->beneficiary,'expense_customer_id'=>$expense->customer_id,'expense_contract_id'=>$expense->contract_id]);}
        if($expense->customer_id)$this->timeline->record($expense->customer_id,'expense',$expense->wasRecentlyCreated?'تسجيل مصروف':'تعديل مصروف',"{$expense->number} · ".number_format((float)$expense->amount,2),$expense,$expense->contract_id,route('expenses.index',['q'=>$expense->number]),$expense->expense_date);
        return $expense;
    }

    public function cancel(Expense $expense, string $reason): Expense
    {
        if ($expense->status === 'cancelled') return $expense;
        if ($expense->bank_statement_row_id) throw new \DomainException('المصروف منشأ من كشف بنك معتمد؛ لا يمكن إلغاؤه منفصلًا عن المطابقة البنكية.');
        return DB::transaction(function () use ($expense, $reason) {
            $expense->update(['status'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason,'updated_by'=>auth()->id()]);
            if ($expense->customer_id) $this->timeline->record($expense->customer_id,'expense','إلغاء مصروف',"{$expense->number} · {$reason}",$expense,$expense->contract_id,route('expenses.index',['q'=>$expense->number]));
            return $expense;
        });
    }

    public function reopen(Expense $expense): Expense
    {
        if ($expense->status !== 'cancelled') return $expense;
        return DB::transaction(function () use ($expense) {
            $expense->update(['status'=>'approved','reopened_at'=>now(),'reopened_by'=>auth()->id(),'cancelled_at'=>null,'cancelled_by'=>null,'cancellation_reason'=>null,'updated_by'=>auth()->id()]);
            if ($expense->customer_id) $this->timeline->record($expense->customer_id,'expense','إعادة فتح مصروف',$expense->number,$expense,$expense->contract_id,route('expenses.index',['q'=>$expense->number]));
            return $expense;
        });
    }

    public function delete(Expense $expense): void { if($expense->bank_statement_row_id)throw new \DomainException('لا يمكن حذف مصروف منشأ من كشف بنك معتمد. عدّل بياناته مع بقاء مبلغ وتاريخ الحركة البنكية محفوظين.'); DB::transaction(function()use($expense){if($expense->customer_id)$this->timeline->record($expense->customer_id,'expense','حذف مصروف',"{$expense->number} · ".number_format((float)$expense->amount,2),null,$expense->contract_id,null,now());$expense->delete();}); }
}
