<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractMaintenanceSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContractMaintenanceService
{
    public function __construct(
        private RecurringReceivableGenerator $generator,
        private ContractDateService $dates,
        private TimelineService $timeline,
    ) {}

    public function update(Contract $contract, string $mode, ?float $annualAmount, string $effectiveFrom, ?string $reason = null): array
    {
        if ($contract->status !== 'active') throw new \DomainException('أعد فتح العقد أولًا قبل تعديل الصيانة.');
        if ($contract->billing_cycle !== 'one_time') throw new \DomainException('قيمة الصيانة السنوية تطبق على عقود الدفع مرة واحدة فقط.');
        if (! in_array($mode, ['auto','manual'], true)) throw new \DomainException('طريقة احتساب الصيانة غير صحيحة.');
        if ($mode === 'manual' && ($annualAmount === null || $annualAmount < 0)) throw new \DomainException('أدخل قيمة صيانة سنوية صحيحة.');

        $effective = Carbon::parse($effectiveFrom)->startOfDay();
        if ($effective->lt($contract->service_start_date->copy()->startOfDay())) {
            throw new \DomainException('تاريخ تطبيق قيمة الصيانة لا يمكن أن يسبق بداية خدمة العقد.');
        }

        return DB::transaction(function () use ($contract, $mode, $annualAmount, $effective, $reason) {
            $locked = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $setting = ContractMaintenanceSetting::create([
                'contract_id' => $locked->id,
                'effective_from' => $effective->toDateString(),
                'mode' => $mode,
                'annual_amount' => $mode === 'manual' ? round((float) $annualAmount, 2) : null,
                'reason' => filled($reason) ? trim((string) $reason) : null,
                'created_by' => auth()->id(),
            ]);

            $locked->unsetRelation('maintenanceSettings');
            $currentAmount = $this->generator->maintenanceNetAt($locked->fresh(['items','maintenanceSettings']), today());
            $updates = ['updated_by' => auth()->id()];

            if (! $locked->next_maintenance_date) {
                $floor = ($locked->calculation_start_date ?: $locked->service_start_date)->copy()->startOfDay();
                if ($effective->gt($floor)) $floor = $effective->copy();
                $probe = $this->dates->nextMaintenanceDate(
                    $locked->service_start_date,
                    $floor,
                    $locked->maintenance_paid_until,
                );
                if ($this->generator->maintenanceNetAt($locked->fresh(['items','maintenanceSettings']), $probe) > 0) {
                    $updates['next_maintenance_date'] = $probe;
                }
            }
            $locked->update($updates);

            $adjusted = $this->generator->reconcileUnsettledFrom($locked->fresh(['items','maintenanceSettings']), $effective);
            $this->generator->generateForContract($locked->fresh(['items','maintenanceSettings']));

            $label = $mode === 'manual'
                ? 'قيمة يدوية '.number_format((float) $annualAmount, 2).' '.$locked->currency
                : 'احتساب تلقائي من بنود العقد';
            $this->timeline->record(
                $locked->customer_id,
                'contract',
                'تعديل احتساب صيانة العقد',
                $locked->number.' · '.$label.' · من '.$effective->toDateString(),
                $locked,
                $locked->id,
                route('contracts.show', $locked),
                $effective,
            );

            return ['setting' => $setting, 'adjusted_receivables' => $adjusted, 'current_amount' => $currentAmount];
        });
    }
}
