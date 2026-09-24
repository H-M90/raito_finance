<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContractItemLifecycleService
{
    public function __construct(
        private RecurringReceivableGenerator $generator,
        private TimelineService $timeline,
    ) {}

    public function stop(Contract $contract, ContractItem $item, string $effectiveDate, ?string $reason = null): array
    {
        if ((int) $item->contract_id !== (int) $contract->id) abort(404);
        $effective = Carbon::parse($effectiveDate)->startOfDay();

        return DB::transaction(function () use ($contract, $item, $effective, $reason) {
            $lockedContract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $lockedItem = ContractItem::whereKey($item->id)->where('contract_id', $lockedContract->id)->lockForUpdate()->firstOrFail();

            if ($lockedContract->status !== 'active') {
                throw new \DomainException('لا يمكن إيقاف بند داخل عقد ملغي.');
            }

            $serviceStart = $lockedContract->service_start_date->copy()->startOfDay();
            $activeFrom = ($lockedItem->active_from ?: $lockedContract->service_start_date)->copy()->startOfDay();
            if ($effective->lt($serviceStart) || $effective->lt($activeFrom)) {
                throw new \DomainException('تاريخ إيقاف البند لا يمكن أن يسبق بداية خدمته داخل العقد.');
            }
            if ($lockedItem->stopped_at) {
                throw new \DomainException('تم تسجيل إيقاف لهذا البند بالفعل اعتبارًا من '.$lockedItem->stopped_at->format('Y-m-d').'.');
            }

            $lockedItem->loadMissing('product');
            $dependentBaseItems = ContractItem::query()
                ->with('product:id,name,required_product_id')
                ->where('contract_id', $lockedContract->id)
                ->where('id', '!=', $lockedItem->id)
                ->activeOn($effective)
                ->whereHas('product', fn ($q) => $q->where('required_product_id', $lockedItem->product_id))
                ->get();
            $dependentAddendumNames = DB::table('contract_addendum_items as cai')
                ->join('contract_addendums as ca', 'ca.id', '=', 'cai.contract_addendum_id')
                ->join('products as p', 'p.id', '=', 'cai.product_id')
                ->where('ca.contract_id', $lockedContract->id)
                ->where('ca.status', 'active')
                ->whereDate('ca.service_start_date', '<=', $effective->toDateString())
                ->where('p.required_product_id', $lockedItem->product_id)
                ->pluck('p.name');
            $dependentNames = $dependentBaseItems->pluck('product.name')->merge($dependentAddendumNames)->filter()->unique()->values();
            if ($dependentNames->isNotEmpty()) {
                throw new \DomainException('لا يمكن إيقاف '.$lockedItem->product?->name.' قبل إيقاف البنود المرتبطة به: '.$dependentNames->join('، ').'.');
            }

            $types = $lockedContract->billing_cycle === 'one_time' ? ['maintenance'] : [$lockedContract->billing_cycle];
            $affectedReceivables = $lockedContract->receivables()
                ->whereNull('contract_addendum_id')
                ->whereIn('type', $types)
                ->whereDate('due_date', '>=', $effective)
                ->lockForUpdate()
                ->get();

            if ($affectedReceivables->contains(fn ($receivable) => (float) $receivable->collected_amount > 0 || (float) $receivable->discounted_amount > 0)) {
                throw new \DomainException('يوجد استحقاق من تاريخ الإيقاف أو بعده عليه تحصيل أو خصم. ألغِ/صحح الحركة المالية أولًا ثم أوقف البند للحفاظ على سلامة الحسابات.');
            }

            $lockedItem->update([
                'stopped_at' => $effective->toDateString(),
                'status' => 'stopped',
                'stop_reason' => filled($reason) ? trim((string) $reason) : null,
                'stopped_by' => auth()->id(),
            ]);

            $adjusted = $this->generator->reconcileUnsettledFrom($lockedContract->fresh('items'), $effective);
            $this->timeline->record(
                $lockedContract->customer_id,
                'contract',
                'إيقاف بند من العقد',
                $lockedItem->product?->name.' · اعتبارًا من '.$effective->toDateString().(filled($reason) ? ' · '.trim((string) $reason) : ''),
                $lockedContract,
                $lockedContract->id,
                route('contracts.show', $lockedContract),
                $effective,
            );

            return ['item' => $lockedItem->fresh('product'), 'adjusted_receivables' => $adjusted];
        });
    }

    public function reopen(Contract $contract, ContractItem $item): array
    {
        if ((int) $item->contract_id !== (int) $contract->id) abort(404);

        return DB::transaction(function () use ($contract, $item) {
            $lockedContract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $lockedItem = ContractItem::whereKey($item->id)->where('contract_id', $lockedContract->id)->lockForUpdate()->firstOrFail();
            if ($lockedContract->status !== 'active') {
                throw new \DomainException('لا يمكن إعادة تفعيل بند داخل عقد ملغى.');
            }
            if (! $lockedItem->stopped_at) {
                return ['item' => $lockedItem->fresh('product'), 'adjusted_receivables' => 0];
            }

            $types = $lockedContract->billing_cycle === 'one_time' ? ['maintenance'] : [$lockedContract->billing_cycle];
            $hasFinancialMovements = $lockedContract->receivables()
                ->whereNull('contract_addendum_id')
                ->whereIn('type', $types)
                ->whereDate('due_date', '>=', today())
                ->where(fn ($query) => $query->where('collected_amount', '>', 0)->orWhere('discounted_amount', '>', 0))
                ->exists();
            if ($hasFinancialMovements) {
                throw new \DomainException('لا يمكن إعادة تفعيل البند لأن استحقاقًا متأثرًا به عليه تحصيل أو سند خصم.');
            }

            $lockedItem->update([
                'stopped_at' => null,
                'status' => 'active',
                'stop_reason' => null,
                'stopped_by' => null,
            ]);
            $adjusted = $this->generator->reconcileUnsettledFrom($lockedContract->fresh('items'), today());
            $this->generator->generateForContract($lockedContract->fresh('items'));
            $this->timeline->record(
                $lockedContract->customer_id,
                'contract',
                'إعادة تفعيل بند في العقد',
                $lockedItem->fresh('product')->product?->name,
                $lockedContract,
                $lockedContract->id,
                route('contracts.index'),
            );

            return ['item' => $lockedItem->fresh('product'), 'adjusted_receivables' => $adjusted];
        });
    }
}
