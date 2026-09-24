<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\ContractAddendumInstallment;
use App\Models\ContractInstallment;
use App\Models\DiscountVoucher;
use App\Models\Receivable;
use Illuminate\Support\Facades\DB;

class ReceivableService
{
    public function __construct(private NumberGenerator $numbers, private LedgerService $ledger, private TimelineService $timeline) {}

    public function fromInstallment(Contract $contract, ContractInstallment $installment): Receivable
    {
        $receivable = Receivable::firstOrCreate(
            ['source_type' => ContractInstallment::class, 'source_id' => $installment->id],
            [
                'number' => $this->numbers->unique('receivables', 'REC'),
                'customer_id' => $contract->customer_id,
                'contract_id' => $contract->id,
                'type' => 'installment',
                'name' => $installment->name,
                'due_date' => $installment->due_date,
                'currency' => $contract->currency,
                'net_amount' => $installment->net_amount,
                'tax_amount' => $installment->tax_amount,
                'total_amount' => $installment->total_amount,
                'remaining_amount' => $installment->total_amount,
                'created_by' => $contract->created_by ?: auth()->id(),
            ]
        );
        $receivable->refreshStatus();
        $this->ledger->postReceivable($receivable);
        if ($receivable->wasRecentlyCreated) {
            $this->recordTimeline($receivable);
        }

        return $receivable;
    }

    public function fromAddendumInstallment(Contract $contract, ContractAddendum $addendum, ContractAddendumInstallment $installment): Receivable
    {
        $receivable = Receivable::firstOrCreate(
            ['source_type' => ContractAddendumInstallment::class, 'source_id' => $installment->id],
            [
                'number' => $this->numbers->unique('receivables', 'REC'),
                'customer_id' => $contract->customer_id,
                'contract_id' => $contract->id,
                'contract_addendum_id' => $addendum->id,
                'type' => 'addendum_installment',
                'name' => $installment->name.' - '.$addendum->number,
                'due_date' => $installment->due_date,
                'currency' => $contract->currency,
                'net_amount' => $installment->net_amount,
                'tax_amount' => $installment->tax_amount,
                'total_amount' => $installment->total_amount,
                'remaining_amount' => $installment->total_amount,
                'created_by' => $addendum->created_by ?: $contract->created_by ?: auth()->id(),
            ]
        );
        $receivable->refreshStatus();
        $this->ledger->postReceivable($receivable);
        if ($receivable->wasRecentlyCreated) {
            $this->recordTimeline($receivable);
        }

        return $receivable;
    }

    public function openingBalance(Contract $contract, string $type, float $amount): ?Receivable
    {
        if ($amount <= 0) {
            return null;
        }
        $date = $contract->calculation_start_date ?? $contract->service_start_date;
        $name = $type === 'opening_maintenance' ? 'رصيد افتتاحي صيانة' : 'رصيد افتتاحي دفعات';
        $receivable = Receivable::create([
            'number' => $this->numbers->unique('receivables', 'OB'),
            'customer_id' => $contract->customer_id,
            'contract_id' => $contract->id,
            'type' => $type,
            'name' => $name,
            'due_date' => $date,
            'currency' => $contract->currency,
            'net_amount' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'remaining_amount' => $amount,
            'status' => now()->startOfDay()->gt($date) ? 'overdue' : 'due',
            'is_opening_balance' => true,
            'created_by' => $contract->created_by ?: auth()->id(),
        ]);
        $this->ledger->postReceivable($receivable);
        $this->recordTimeline($receivable);

        return $receivable;
    }

    public function recurringAddendumMaintenance(Contract $contract, ContractAddendum $addendum, \DateTimeInterface|string $dueDate): Receivable
    {
        $netAmount = (float) $addendum->maintenance_total;
        $tax = round($netAmount * ((float) $contract->tax_rate / 100), 2);
        $receivable = Receivable::create([
            'number' => $this->numbers->unique('receivables', 'REC'),
            'customer_id' => $contract->customer_id,
            'contract_id' => $contract->id,
            'contract_addendum_id' => $addendum->id,
            'type' => 'maintenance',
            'name' => 'صيانة سنوية للملحق - '.$addendum->number,
            'due_date' => $dueDate,
            'currency' => $contract->currency,
            'net_amount' => $netAmount,
            'tax_amount' => $tax,
            'total_amount' => $netAmount + $tax,
            'remaining_amount' => $netAmount + $tax,
            'created_by' => $addendum->created_by ?: $contract->created_by ?: auth()->id(),
        ]);
        $receivable->refreshStatus();
        $this->ledger->postReceivable($receivable);
        $this->recordTimeline($receivable);

        return $receivable;
    }

    public function recurring(Contract $contract, string $type, \DateTimeInterface|string $dueDate, float $netAmount): Receivable
    {
        $tax = round($netAmount * ((float) $contract->tax_rate / 100), 2);
        $label = match ($type) {
            'maintenance' => 'الصيانة السنوية', 'monthly' => 'الاشتراك الشهري', default => 'الاشتراك السنوي'
        };
        $receivable = Receivable::create([
            'number' => $this->numbers->unique('receivables', 'REC'),
            'customer_id' => $contract->customer_id,
            'contract_id' => $contract->id,
            'type' => $type,
            'name' => $label.' - '.$contract->number,
            'due_date' => $dueDate,
            'currency' => $contract->currency,
            'net_amount' => $netAmount,
            'tax_amount' => $tax,
            'total_amount' => $netAmount + $tax,
            'remaining_amount' => $netAmount + $tax,
            'created_by' => $contract->created_by ?: auth()->id(),
        ]);
        $receivable->refreshStatus();
        $this->ledger->postReceivable($receivable);
        $this->recordTimeline($receivable);

        return $receivable;
    }

    public function createManual(Contract $contract, array $data): Receivable
    {
        return DB::transaction(function () use ($contract, $data) {
            $contract = Contract::lockForUpdate()->findOrFail($contract->id);
            if ($contract->status !== 'active') {
                throw new \DomainException('لا يمكن إضافة استحقاق لعقد ملغى.');
            }

            $amount = round((float) $data['total_amount'], 2);
            $receivable = Receivable::create([
                'number' => $this->numbers->unique('receivables', 'REC'),
                'customer_id' => $contract->customer_id,
                'contract_id' => $contract->id,
                'type' => 'manual',
                'name' => $data['name'],
                'due_date' => $data['due_date'],
                'currency' => $contract->currency,
                'net_amount' => $amount,
                'tax_amount' => 0,
                'total_amount' => $amount,
                'remaining_amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $receivable->refreshStatus();
            $this->ledger->postReceivable($receivable->fresh());
            $this->recordTimeline($receivable->fresh());

            return $receivable->fresh();
        });
    }

    /**
     * Imported dues are intentionally created at zero while their value is
     * reviewed. They can be completed here until a financial movement exists.
     */
    public function updateImportedDue(Receivable $receivable, array $data): Receivable
    {
        if ($receivable->type !== 'legacy_import_due') {
            throw new \DomainException('يمكن تعديل الاستحقاقات المستوردة فقط من هذه الشاشة.');
        }

        return DB::transaction(function () use ($receivable, $data) {
            $receivable = Receivable::lockForUpdate()->findOrFail($receivable->id);

            $hasDiscount = DiscountVoucher::query()
                ->where('receivable_id', $receivable->id)
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($receivable->collectionAllocations()->exists() || $hasDiscount) {
                throw new \DomainException('لا يمكن تعديل استحقاق مرتبط بتحصيل أو سند خصم.');
            }

            $amount = round((float) $data['total_amount'], 2);
            $receivable->fill([
                'name' => $data['name'],
                'due_date' => $data['due_date'],
                'net_amount' => $amount,
                'tax_amount' => 0,
                'total_amount' => $amount,
            ])->save();
            $receivable->refreshStatus();
            $receivable->refresh();

            $this->ledger->postReceivable($receivable);
            $this->timeline->record(
                $receivable->customer_id,
                'receivable',
                'تعديل استحقاق مستورد',
                $receivable->name.' · '.number_format((float) $receivable->total_amount, 2).' '.$receivable->currency,
                $receivable,
                $receivable->contract_id,
                route('receivables.index', ['q' => $receivable->number]),
                $receivable->due_date,
            );

            return $receivable;
        });
    }

    private function recordTimeline(Receivable $receivable): void
    {
        $this->timeline->record(
            $receivable->customer_id,
            'receivable',
            'إنشاء استحقاق',
            $receivable->name.' · '.number_format((float) $receivable->total_amount, 2).' '.$receivable->currency,
            $receivable,
            $receivable->contract_id,
            route('receivables.index', ['q' => $receivable->number]),
            $receivable->due_date,
        );
    }
}
