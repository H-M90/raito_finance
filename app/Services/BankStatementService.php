<?php
namespace App\Services;

use App\Models\BankStatementBatch;
use App\Models\BankStatementRow;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Receivable;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BankStatementService
{
    public function __construct(private CollectionService $collections, private ExpenseService $expenses) {}

    public function upload(UploadedFile $file, array $meta): BankStatementBatch
    {
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = $file->storeAs("bank-statements/{$uuid}", "statement.{$extension}", 'local');
        if (!$path) throw new DomainException('تعذر حفظ ملف كشف الحساب.');

        try {
            $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
            $sheet = $spreadsheet->getSheet(0);
            $matrix = $sheet->toArray(null, true, true, false);
            [$headerRow, $headers] = $this->detectHeader($matrix);
            $preview = array_slice($matrix, max(0, $headerRow - 1), 8);
            $mapping = $this->autoMapping($headers);
            $sheetName = $sheet->getTitle();
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e); throw new DomainException('تعذر قراءة ملف Excel. تأكد من صيغة الملف والأعمدة ثم حاول مرة أخرى.');
        }

        return BankStatementBatch::create([
            'uuid'=>$uuid,
            'bank_name'=>$meta['bank_name'] ?? null,
            'account_name'=>$meta['account_name'] ?? null,
            'original_filename'=>$file->getClientOriginalName(),
            'stored_path'=>$path,
            'currency'=>$meta['currency'] ?? 'SAR',
            'sheet_name'=>$sheetName,
            'header_row'=>$headerRow,
            'mapping'=>$mapping,
            'detected_headers'=>$headers,
            'preview_rows'=>$preview,
            'status'=>'mapping',
            'created_by'=>auth()->id(),
        ]);
    }

    public function parse(BankStatementBatch $batch, array $mapping): BankStatementBatch
    {
        if ($batch->status === 'approved') throw new DomainException('كشف البنك معتمد ولا يمكن إعادة قراءته.');
        foreach (['date','description'] as $required) {
            if (!isset($mapping[$required]) || $mapping[$required] === '') throw new DomainException('يجب تحديد عمود '.match($required){'date'=>'التاريخ','description'=>'البيان'}.' في ملف البنك.');
        }
        $hasSplit = isset($mapping['debit'],$mapping['credit']) && $mapping['debit'] !== '' && $mapping['credit'] !== '';
        if (!$hasSplit) throw new DomainException('يجب تحديد عمودي المدين والدائن من كشف البنك.');
        $indexes = array_map('intval', array_filter($mapping, fn($v)=>$v!==null&&$v!=='', ARRAY_FILTER_USE_BOTH));
        if (count(array_unique($indexes)) !== count($indexes)) throw new DomainException('لا يمكن استخدام نفس عمود Excel لأكثر من حقل.');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($batch->stored_path));
        $sheet = $spreadsheet->getSheet(0);
        $matrix = $sheet->toArray(null, true, true, false);
        $headers = $batch->detected_headers ?? [];

        DB::transaction(function () use ($batch, $mapping, $matrix, $headers) {
            $batch->rows()->delete();
            $totalDebit = 0; $totalCredit = 0; $count = 0;
            foreach ($matrix as $zeroIndex => $values) {
                $rowNumber = $zeroIndex + 1;
                if ($rowNumber <= $batch->header_row) continue;
                $debit = $this->money($values[(int)$mapping['debit']] ?? null);
                $credit = $this->money($values[(int)$mapping['credit']] ?? null);
                if ($debit <= 0 && $credit <= 0) continue;
                $date = $this->dateValue($values[(int)$mapping['date']] ?? null);
                $description = trim((string)($values[(int)$mapping['description']] ?? ''));
                $reference = isset($mapping['reference']) && $mapping['reference'] !== '' ? trim((string)($values[(int)$mapping['reference']] ?? '')) : null;
                $errors = [];
                if (!$date) $errors[] = 'تاريخ الحركة غير صالح.';
                if ($debit > 0 && $credit > 0) $errors[] = 'السطر يحتوي مبلغًا مدينًا ودائنًا في الوقت نفسه.';
                $direction = $credit > 0 && $debit <= 0 ? 'in' : 'out';
                $amount = $direction === 'in' ? $credit : $debit;
                $classification = $direction === 'in' ? 'collection' : 'expense';
                $payload=[];
                foreach($headers as $idx=>$header){ if(trim((string)$header)!=='') $payload[(string)$header]=$values[(int)$idx]??null; }
                BankStatementRow::create([
                    'bank_statement_batch_id'=>$batch->id,
                    'row_number'=>$rowNumber,
                    'transaction_date'=>$date,
                    'description'=>$description ?: 'حركة بنكية',
                    'reference_no'=>$reference ?: null,
                    'debit'=>$debit,
                    'credit'=>$credit,
                    'amount'=>$amount,
                    'direction'=>$direction,
                    'classification'=>$classification,
                    'status'=>'draft',
                    'expense_description'=>$description ?: 'مصروف من كشف البنك',
                    'source_payload'=>$payload,
                    'validation_errors'=>$errors ?: null,
                ]);
                $totalDebit += $debit; $totalCredit += $credit; $count++;
            }
            if ($count === 0) throw new DomainException('لم يتم العثور على حركات مالية بعد صف العناوين. راجع ربط الأعمدة.');
            $batch->update(['mapping'=>$mapping,'status'=>'reviewing','total_rows'=>$count,'total_debit'=>$totalDebit,'total_credit'=>$totalCredit]);
        });
        $spreadsheet->disconnectWorksheets();
        return $batch->fresh();
    }

    public function saveRow(BankStatementRow $row, array $data): BankStatementRow
    {
        $batch=BankStatementBatch::findOrFail($row->bank_statement_batch_id);
        if($batch->status!=='reviewing') throw new DomainException('لا يمكن تعديل حركة بعد اعتماد كشف البنك.');
        $classification=$data['classification']??'unclassified';
        if($classification==='ignored'){
            $row->update(['classification'=>'ignored','status'=>'ignored','expense_category_id'=>null,'expense_customer_id'=>null,'expense_contract_id'=>null,'collection_customer_id'=>null,'allocation_data'=>null,'validation_errors'=>null]);
            return $row;
        }
        if(!in_array($classification,['expense','collection'],true)) throw new DomainException('حدد الحركة كمصروف أو تحصيل أو تجاهل.');
        if(!$row->transaction_date || (float)$row->amount<=0) throw new DomainException('تاريخ أو مبلغ الحركة البنكية غير صالح. أعد رفع الملف بعد تصحيح السطر أو اختر تجاهل الحركة.');
        if(!empty($row->validation_errors)) throw new DomainException(implode(' ', $row->validation_errors).' يمكنك تجاهل الحركة أو تصحيح ملف Excel وإعادة رفعه.');

        if($classification==='expense'){
            $category=ExpenseCategory::where('is_active',true)->find($data['expense_category_id']??null);
            if(!$category) throw new DomainException('اختر تصنيف المصروف.');
            $contractId=$data['expense_contract_id']??null; $customerId=$data['expense_customer_id']??null;
            if($contractId){$contract=\App\Models\Contract::where('status','active')->findOrFail($contractId);if($customerId&&(int)$customerId!==(int)$contract->customer_id)throw new DomainException('العقد لا يخص العميل المختار.');$customerId=$contract->customer_id;}
            $row->update([
                'classification'=>'expense','status'=>'ready','expense_category_id'=>$category->id,
                'expense_description'=>trim((string)($data['expense_description']??$row->description)) ?: $row->description,
                'beneficiary'=>trim((string)($data['beneficiary']??'')) ?: null,
                'expense_customer_id'=>$customerId ?: null,'expense_contract_id'=>$contractId ?: null,
                'collection_customer_id'=>null,'collection_notes'=>null,'allocation_data'=>null,'validation_errors'=>null,
            ]);
            return $row;
        }

        $customer=Customer::active()->find($data['collection_customer_id']??null);
        if(!$customer) throw new DomainException('اختر العميل الخاص بالتحصيل.');
        $allocations=collect($data['allocations']??[])
            ->filter(fn($a)=>(float)($a['amount']??0)>0)
            ->groupBy(fn($a)=>(int)($a['receivable_id']??0))
            ->map(fn($rows,$receivableId)=>[
                'receivable_id'=>(int)$receivableId,
                'amount'=>round($rows->sum(fn($a)=>(float)($a['amount']??0)),2),
            ])->values();
        if($allocations->isEmpty()) throw new DomainException('يجب توزيع التحصيل على استحقاق واحد على الأقل.');
        $sum=round($allocations->sum('amount'),2);
        if(abs($sum-(float)$row->amount)>0.01) throw new DomainException('يجب أن يساوي مجموع توزيع التحصيل مبلغ الحركة البنكية بالكامل.');
        $normalized=[];
        foreach($allocations as $allocation){
            $receivable=Receivable::outstanding()->where('customer_id',$customer->id)->where('currency',$batch->currency)->find($allocation['receivable_id']??null);
            if(!$receivable) throw new DomainException('أحد الاستحقاقات غير متاح لهذا العميل أو العملة.');
            $value=round((float)$allocation['amount'],2);
            if($value>(float)$receivable->remaining_amount+0.01) throw new DomainException("التوزيع على {$receivable->number} أكبر من المتبقي.");
            $normalized[]=['receivable_id'=>$receivable->id,'amount'=>$value];
        }
        $row->update([
            'classification'=>'collection','status'=>'ready','collection_customer_id'=>$customer->id,
            'collection_notes'=>trim((string)($data['collection_notes']??'')) ?: null,'allocation_data'=>$normalized,
            'expense_category_id'=>null,'expense_customer_id'=>null,'expense_contract_id'=>null,'beneficiary'=>null,
            'validation_errors'=>null,
        ]);
        return $row;
    }

    public function approve(BankStatementBatch $batch): BankStatementBatch
    {
        if($batch->status==='approved') throw new DomainException('تم اعتماد كشف البنك بالفعل.');
        if($batch->status!=='reviewing') throw new DomainException('يجب قراءة الحركات ومراجعتها قبل الاعتماد.');
        $notReady=$batch->rows()->whereNotIn('status',['ready','ignored'])->count();
        if($notReady>0) throw new DomainException("يوجد {$notReady} سطر لم يتم حفظ تصنيفه بعد.");

        return DB::transaction(function()use($batch){
            $rows=$batch->rows()->lockForUpdate()->orderBy('row_number')->get();
            foreach($rows as $row){
                if($row->status==='ignored') continue;
                if($row->generated_id) throw new DomainException("السطر {$row->row_number} تم ترحيله من قبل.");
                if($row->classification==='expense'){
                    $expense=$this->expenses->create([
                        'bank_statement_row_id'=>$row->id,'expense_date'=>$row->transaction_date->toDateString(),
                        'expense_category_id'=>$row->expense_category_id,'description'=>$row->expense_description ?: $row->description,
                        'beneficiary'=>$row->beneficiary,'amount'=>$row->amount,'currency'=>$batch->currency,'payment_method'=>'transfer',
                        'customer_id'=>$row->expense_customer_id,'contract_id'=>$row->expense_contract_id,
                        'notes'=>$this->sourceNote($batch,$row),
                    ]);
                    $row->update(['status'=>'posted','generated_type'=>$expense::class,'generated_id'=>$expense->id]);
                } elseif($row->classification==='collection'){
                    $collection=$this->collections->create([
                        'bank_statement_row_id'=>$row->id,'customer_id'=>$row->collection_customer_id,
                        'collection_date'=>$row->transaction_date->toDateString(),'currency'=>$batch->currency,'amount'=>$row->amount,
                        'payment_method'=>'transfer','reference_no'=>$row->reference_no,'collector_name'=>'مطابقة كشف البنك',
                        'notes'=>trim($this->sourceNote($batch,$row).' '.($row->collection_notes??'')),
                        'allocations'=>$row->allocation_data,
                    ]);
                    $row->update(['status'=>'posted','generated_type'=>$collection::class,'generated_id'=>$collection->id]);
                } else throw new DomainException("السطر {$row->row_number} غير مصنف.");
            }
            $batch->update(['status'=>'approved','approved_by'=>auth()->id(),'approved_at'=>now()]);
            return $batch;
        });
    }

    public function delete(BankStatementBatch $batch): void
    {
        if($batch->status==='approved') throw new DomainException('لا يمكن حذف كشف بنك معتمد لأنه أنشأ سندات مالية.');
        DB::transaction(function()use($batch){$path=$batch->stored_path;$batch->delete();if($path)Storage::disk('local')->delete($path);});
    }

    private function sourceNote(BankStatementBatch $batch,BankStatementRow $row): string
    {
        return 'من كشف البنك '.($batch->bank_name ?: '').' · ملف '.$batch->original_filename.' · صف '.$row->row_number.'.';
    }

    private function detectHeader(array $matrix): array
    {
        $bestRow=1;$best=[];$bestScore=-1;
        foreach(array_slice($matrix,0,20,true) as $zeroIndex=>$row){
            $headers=array_map(fn($v)=>trim((string)$v),$row);
            $mapping=$this->autoMapping($headers);
            $aliasScore=count(array_filter($mapping,fn($v)=>$v!==null));
            $nonEmpty=count(array_filter($headers,fn($v)=>$v!==''));
            $score=$aliasScore*100+$nonEmpty;
            if($score>$bestScore){$bestScore=$score;$bestRow=$zeroIndex+1;$best=$headers;}
        }
        return [$bestRow,$best];
    }

    private function autoMapping(array $headers): array
    {
        $aliases=[
            'date'=>['التاريخ','تاريخ العملية','تاريخ الحركة','تاريخ القيد','date','transaction date','value date','posting date'],
            'description'=>['البيان','الوصف','التفاصيل','وصف العملية','description','details','narrative','transaction description','remarks'],
            'reference'=>['المرجع','رقم المرجع','رقم العملية','reference','ref','reference no','transaction id','transaction reference'],
            'debit'=>['مدين','المبلغ المدين','مبلغ مدين','سحب','مسحوبات','debit','debit amount','withdrawal','withdrawals','money out'],
            'credit'=>['دائن','المبلغ الدائن','مبلغ دائن','ايداع','إيداع','إيداعات','credit','credit amount','deposit','deposits','money in'],
        ];
        $normalized=[];foreach($headers as $i=>$header)$normalized[$i]=$this->normalizeHeader((string)$header);
        $mapping=[];
        foreach($aliases as $field=>$words){$mapping[$field]=null;foreach($normalized as $i=>$header){foreach($words as $word){$needle=$this->normalizeHeader($word);if($header===$needle||($needle!==''&&str_contains($header,$needle))){$mapping[$field]=$i;break 2;}}}}
        return $mapping;
    }

    private function normalizeHeader(string $value): string
    {
        $value=mb_strtolower(trim($value));
        $value=str_replace(['أ','إ','آ','ى','ة'],['ا','ا','ا','ي','ه'],$value);
        return preg_replace('/[^\p{L}\p{N}]+/u',' ', $value) ?: '';
    }

    private function money(mixed $value): float
    {
        if($value===null||$value==='')return 0.0;
        if(is_numeric($value))return round(abs((float)$value),2);
        $text=str_replace([',','٬',' ','ر.س','SAR','EGP','USD'],['','','','','','',''],(string)$value);
        $text=str_replace('٫','.',$text);
        $negative=str_contains($text,'(')&&str_contains($text,')');
        $text=preg_replace('/[^0-9.\-]/','',$text)?:'0';
        return round(abs((float)$text),2);
    }

    private function signedMoney(mixed $value): float
    {
        if($value===null||$value==='')return 0.0;
        if(is_numeric($value))return round((float)$value,2);
        $text=str_replace([',','٬',' ','ر.س','SAR','EGP','USD'],['','','','','','',''],(string)$value);
        $text=str_replace('٫','.',$text);
        $negative=(str_contains($text,'(')&&str_contains($text,')'))||str_starts_with(trim($text),'-');
        $text=preg_replace('/[^0-9.]/','',$text)?:'0';
        $number=round((float)$text,2);
        return $negative?-$number:$number;
    }

    private function directionHint(string $value): ?string
    {
        $value=$this->normalizeHeader($value);
        if($value==='')return null;
        foreach(['مدين','سحب','خصم','debit','dr','withdrawal','out'] as $word)if(str_contains($value,$this->normalizeHeader($word)))return 'out';
        foreach(['دائن','ايداع','تحويل وارد','credit','cr','deposit','in'] as $word)if(str_contains($value,$this->normalizeHeader($word)))return 'in';
        return null;
    }

    private function dateValue(mixed $value): ?string
    {
        if($value instanceof \DateTimeInterface)return Carbon::instance($value)->toDateString();
        if(is_numeric($value)&&(float)$value>20000&&(float)$value<80000){
            try{return Carbon::instance(ExcelDate::excelToDateTimeObject((float)$value))->toDateString();}catch(\Throwable){return null;}
        }
        $text=trim((string)$value);if($text==='')return null;
        foreach(['d/m/Y','d-m-Y','Y-m-d','m/d/Y','d/m/y','d-m-y'] as $format){
            $date=\DateTimeImmutable::createFromFormat('!'.$format,$text);
            $errors=\DateTimeImmutable::getLastErrors();
            if($date && ($errors===false || ($errors['warning_count']===0 && $errors['error_count']===0))) return $date->format('Y-m-d');
        }
        try{return Carbon::parse($text)->toDateString();}catch(\Throwable){return null;}
    }
}
