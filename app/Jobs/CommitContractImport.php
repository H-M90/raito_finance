<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ContractImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CommitContractImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(public int $batchId) {}

    public function handle(ContractImportService $service): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        if (in_array($batch->status, ['completed', 'rolled_back'], true)) {
            return;
        }

        $service->commit($batch);
    }

    public function failed(\Throwable $exception): void
    {
        ImportBatch::whereKey($this->batchId)
            ->whereNotIn('status', ['completed', 'rolled_back'])
            ->update([
                'status' => 'failed',
                'failure_message' => mb_substr(\App\Support\SafeExceptionMessage::from($exception), 0, 4000),
            ]);
    }
}
