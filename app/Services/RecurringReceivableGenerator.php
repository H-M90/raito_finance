<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\ContractItem;
use App\Models\CustomerLedgerEntry;
use App\Models\Receivable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringReceivableGenerator
{
    public function __construct(
        private ReceivableService $receivables,
        private LedgerService $ledger,
        private ContractDateService $dates,
    ) {}

    public function horizon(CarbonInterface|string|null $until = null): Carbon
    {
        if ($until) return Carbon::parse($until)->startOfDay();
        return today()->addDays(max(0, (int) config('finance.receivable_generation_lead_days', 15)));
    }

    public function recurringNetAt(Contract $contract, CarbonInterface|string $date): float
    {
        $contract->loadMissing(['items','maintenanceSettings']);
        return round($contract->items
            ->filter(fn (ContractItem $item) => $this->itemIsActiveOn($contract, $item, $date))
            ->sum(fn (ContractItem $item) => (float) $item->line_net), 2);
    }

    public function maintenanceNetAt(Contract $contract, CarbonInterface|string $date): float
    {
        $contract->loadMissing(['items','maintenanceSettings']);
        $date = Carbon::parse($date)->startOfDay();
        $setting = $contract->maintenanceSettings
            ->filter(fn ($row) => $row->effective_from->copy()->startOfDay()->lte($date))
            ->sortByDesc(fn ($row) => $row->effective_from->format('Y-m-d').'#'.str_pad((string)$row->id, 20, '0', STR_PAD_LEFT))
            ->first();
        if ($setting?->mode === 'manual') return round((float) $setting->annual_amount, 2);
        return round($contract->items
            ->filter(fn (ContractItem $item) => $this->itemIsActiveOn($contract, $item, $date))
            ->sum(fn (ContractItem $item) => (float) $item->maintenance_annual), 2);
    }

    public function generateForContract(Contract $contract, Carbon|string|null $until = null): int
    {
        $until = $this->horizon($until);
        if ($contract->status !== 'active') return 0;
        $contract->loadMissing('items');

        $created = 0;

        while ($contract->next_billing_date && $contract->next_billing_date->lte($until)) {
            if (! in_array($contract->billing_cycle, ['monthly', 'annual'], true)) {
                $contract->next_billing_date = null;
                $contract->save();
                break;
            }

            $type = $contract->billing_cycle;
            $dueDate = $contract->next_billing_date->copy();
            $exists = Receivable::query()
                ->where('contract_id', $contract->id)
                ->whereNull('contract_addendum_id')
                ->where('type', $type)
                ->whereDate('due_date', $dueDate)
                ->exists();

            $netAmount = $this->recurringNetAt($contract, $dueDate);
            if (! $exists && $netAmount > 0) {
                $this->receivables->recurring($contract, $type, $dueDate, $netAmount);
                $created++;
            }

            $contract->next_billing_date = $contract->billing_cycle === 'monthly'
                ? $dueDate->addMonthNoOverflow()
                : $dueDate->addYear();
            $contract->save();
        }

        while (
            $contract->billing_cycle === 'one_time'
            && $contract->next_maintenance_date
            && $contract->next_maintenance_date->lte($until)
        ) {
            $dueDate = $contract->next_maintenance_date->copy();
            $exists = Receivable::query()
                ->where('contract_id', $contract->id)
                ->whereNull('contract_addendum_id')
                ->where('type', 'maintenance')
                ->whereDate('due_date', $dueDate)
                ->exists();

            $netAmount = $this->maintenanceNetAt($contract, $dueDate);
            if (! $exists && $netAmount > 0) {
                $this->receivables->recurring($contract, 'maintenance', $dueDate, $netAmount);
                $created++;
            }

            $contract->next_maintenance_date = $dueDate->addYear();
            $contract->save();
        }

        return $created;
    }

    public function generateHistoricalForContract(Contract $contract, CarbonInterface|string $from, CarbonInterface|string|null $until = null): int
    {
        if ($contract->status !== 'active') return 0;

        $from = Carbon::parse($from)->startOfDay();
        $until = Carbon::parse($until ?: today())->startOfDay();
        if ($until->lt($from)) throw new \DomainException('تاريخ نهاية التوليد يجب أن يكون بعد أو مساويًا لتاريخ البداية.');

        $contract->loadMissing('items');
        $created = 0;

        if (in_array($contract->billing_cycle, ['monthly', 'annual'], true)) {
            $dueDate = $this->dates->nextBillingDate($contract->billing_cycle, $contract->service_start_date, $from);
            while ($dueDate && $dueDate->lte($until)) {
                $exists = Receivable::query()
                    ->where('contract_id', $contract->id)
                    ->whereNull('contract_addendum_id')
                    ->where('type', $contract->billing_cycle)
                    ->whereDate('due_date', $dueDate)
                    ->exists();
                $netAmount = $this->recurringNetAt($contract, $dueDate);
                if (! $exists && $netAmount > 0) {
                    $this->receivables->recurring($contract, $contract->billing_cycle, $dueDate, $netAmount);
                    $created++;
                }
                $dueDate = $contract->billing_cycle === 'monthly'
                    ? $dueDate->copy()->addMonthNoOverflow()
                    : $dueDate->copy()->addYear();
            }
        }

        if ($contract->billing_cycle === 'one_time') {
            $dueDate = $this->dates->nextMaintenanceDate(
                $contract->service_start_date,
                $from,
                $contract->maintenance_paid_until,
            );
            while ($dueDate->lte($until)) {
                $exists = Receivable::query()
                    ->where('contract_id', $contract->id)
                    ->whereNull('contract_addendum_id')
                    ->where('type', 'maintenance')
                    ->whereDate('due_date', $dueDate)
                    ->exists();
                $netAmount = $this->maintenanceNetAt($contract, $dueDate);
                if (! $exists && $netAmount > 0) {
                    $this->receivables->recurring($contract, 'maintenance', $dueDate, $netAmount);
                    $created++;
                }
                $dueDate = $dueDate->copy()->addYear();
            }
        }

        return $created;
    }

    public function reconcileUnsettledFrom(Contract $contract, CarbonInterface|string $from): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $contract->loadMissing(['items','maintenanceSettings']);
        $types = $contract->billing_cycle === 'one_time' ? ['maintenance'] : [$contract->billing_cycle];
        $adjusted = 0;

        $receivables = $contract->receivables()
            ->whereNull('contract_addendum_id')
            ->whereIn('type', $types)
            ->whereDate('due_date', '>=', $from)
            ->where('collected_amount', '<=', 0)
            ->where('discounted_amount', '<=', 0)
            ->lockForUpdate()
            ->get();

        foreach ($receivables as $receivable) {
            $netAmount = $receivable->type === 'maintenance'
                ? $this->maintenanceNetAt($contract, $receivable->due_date)
                : $this->recurringNetAt($contract, $receivable->due_date);

            if ($netAmount <= 0) {
                $receivable->forceFill([
                    'net_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'remaining_amount' => 0,
                    'status' => 'cancelled',
                    'notes' => trim(($receivable->notes ? $receivable->notes."\n" : '').'أُلغي تلقائيًا بعد إعادة احتساب قيمة الاستحقاق إلى صفر.'),
                ])->save();
                CustomerLedgerEntry::where('source_type', Receivable::class)
                    ->where('source_id', $receivable->id)
                    ->where('entry_type', 'receivable')
                    ->update(['is_reversed' => true]);
                $adjusted++;
                continue;
            }

            $tax = round($netAmount * ((float) $contract->tax_rate / 100), 2);
            if (
                abs((float) $receivable->net_amount - $netAmount) > 0.009
                || abs((float) $receivable->tax_amount - $tax) > 0.009
            ) {
                $receivable->forceFill([
                    'net_amount' => $netAmount,
                    'tax_amount' => $tax,
                    'total_amount' => $netAmount + $tax,
                ])->save();
                $receivable->refreshStatus();
                $this->ledger->postReceivable($receivable->fresh());
                $adjusted++;
            }
        }

        return $adjusted;
    }

    public function generateForAddendum(ContractAddendum $addendum, Carbon|string|null $until = null): int
    {
        $until = $this->horizon($until);
        $addendum->loadMissing('contract');

        if ($addendum->status !== 'active' || ! $addendum->contract || $addendum->contract->status !== 'active') return 0;
        if ($addendum->contract->billing_cycle !== 'one_time' || (float) $addendum->maintenance_total <= 0) return 0;

        $created = 0;
        while ($addendum->next_maintenance_date && $addendum->next_maintenance_date->lte($until)) {
            $dueDate = $addendum->next_maintenance_date->copy();
            $exists = Receivable::query()
                ->where('contract_addendum_id', $addendum->id)
                ->where('type', 'maintenance')
                ->whereDate('due_date', $dueDate)
                ->exists();

            if (! $exists) {
                $this->receivables->recurringAddendumMaintenance($addendum->contract, $addendum, $dueDate);
                $created++;
            }

            $addendum->next_maintenance_date = $dueDate->addYear();
            $addendum->save();
        }

        return $created;
    }

    public function generateDue(Carbon|string|null $until = null): int
    {
        $until = $this->horizon($until);
        $created = 0;

        Contract::active()
            ->where(function ($query) use ($until) {
                $query->whereDate('next_billing_date', '<=', $until)
                    ->orWhereDate('next_maintenance_date', '<=', $until);
            })
            ->chunkById(100, function ($contracts) use ($until, &$created) {
                foreach ($contracts as $row) {
                    DB::transaction(function () use ($row, $until, &$created) {
                        $contract = Contract::lockForUpdate()->findOrFail($row->id);
                        $created += $this->generateForContract($contract, $until);
                    });
                }
            });

        ContractAddendum::query()
            ->where('status', 'active')
            ->where('maintenance_total', '>', 0)
            ->whereDate('next_maintenance_date', '<=', $until)
            ->chunkById(100, function ($addendums) use ($until, &$created) {
                foreach ($addendums as $row) {
                    DB::transaction(function () use ($row, $until, &$created) {
                        $addendum = ContractAddendum::with('contract')->lockForUpdate()->findOrFail($row->id);
                        $created += $this->generateForAddendum($addendum, $until);
                    });
                }
            });

        return $created;
    }

    private function itemIsActiveOn(Contract $contract, ContractItem $item, CarbonInterface|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();
        $start = ($item->active_from ?: $contract->service_start_date)?->copy()?->startOfDay();
        $stop = $item->stopped_at?->copy()?->startOfDay();
        return (! $start || $start->lte($date)) && (! $stop || $stop->gt($date));
    }
}
