<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\DiscountVoucher;
use App\Models\Installation;
use App\Models\SalesQuotation;
use App\Models\TimelineEvent;
use Illuminate\Console\Command;

class BackfillTimeline extends Command
{
    protected $signature = 'finance:backfill-timeline {--fresh : حذف سجل الأحداث الحالي وإعادة بنائه}';
    protected $description = 'إنشاء سجل زمني موحد للحركات الموجودة قبل تفعيل Timeline';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            TimelineEvent::query()->delete();
        }

        $created = 0;
        $record = function ($model, int $customerId, ?int $contractId, string $type, string $title, string $description, $date, ?string $url = null) use (&$created): void {
            TimelineEvent::firstOrCreate([
                'source_type' => $model::class,
                'source_id' => $model->getKey(),
                'event_type' => $type,
                'title' => $title,
            ], [
                'customer_id' => $customerId,
                'contract_id' => $contractId,
                'event_at' => $date,
                'description' => $description,
                'url' => $url,
            ]);
            $created++;
        };

        SalesQuotation::withTrashed()->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) $record($row, $row->customer_id, null, 'quotation', 'عرض مبيعات', $row->number, $row->quotation_date, route('quotations.show', $row));
        });
        Contract::withTrashed()->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) $record($row, $row->customer_id, $row->id, 'contract', 'عقد', $row->number, $row->contract_date, route('contracts.show', $row));
        });
        ContractAddendum::with('contract:id,customer_id')->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) if ($row->contract) $record($row, $row->contract->customer_id, $row->contract_id, 'addendum', 'ملحق عقد', $row->number, $row->addendum_date);
        });
        Collection::withTrashed()->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) $record($row, $row->customer_id, null, 'collection', 'سند قبض', $row->number, $row->collection_date);
        });
        DiscountVoucher::withTrashed()->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) $record($row, $row->customer_id, $row->contract_id, 'discount_voucher', 'سند خصم', $row->number, $row->voucher_date);
        });
        Installation::with('contract:id,customer_id')->orderBy('id')->chunkById(250, function ($rows) use ($record) {
            foreach ($rows as $row) if ($row->contract) $record($row, $row->contract->customer_id, $row->contract_id, 'installation', 'عملية تركيب', $row->number, $row->installation_date);
        });

        $this->info("تمت مراجعة وإنشاء {$created} حدثًا زمنيًا.");
        return self::SUCCESS;
    }
}
