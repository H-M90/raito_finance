<?php
namespace App\Jobs;
use App\Models\ImportBatch;
use App\Services\ContractImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessContractImport implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $timeout=600; public int $tries=2;
    public function __construct(public int $batchId){}
    public function handle(ContractImportService $service): void { $service->process(ImportBatch::findOrFail($this->batchId)); }
    public function failed(\Throwable $e): void { ImportBatch::whereKey($this->batchId)->update(['status'=>'failed','failure_message'=>\App\Support\SafeExceptionMessage::from($e)]); }
}
