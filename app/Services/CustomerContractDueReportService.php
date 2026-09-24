<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Product;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerContractDueReportService
{
    public function __construct(private RecurringReceivableGenerator $generator) {}

    public function report(Request $request): LengthAwarePaginator
    {
        $today = today()->toDateString();
        $query = OwnRecordVisibility::apply(Contract::query())
            ->active()
            ->with([
                'customer:id,name,code',
                'items.product:id,code,name',
                'maintenanceSettings',
                'receivables' => fn ($q) => OwnRecordVisibility::apply($q)
                    ->whereDate('due_date', '>=', $today)
                    ->where('remaining_amount', '>', 0)
                    ->whereNotIn('status', ['cancelled','waived','paid'])
                    ->orderBy('due_date')
                    ->orderBy('id'),
                'addendums' => fn ($q) => $q
                    ->where('status', 'active')
                    ->whereDate('service_start_date', '<=', $today)
                    ->with(['items.product:id,code,name']),
            ])
            ->when($request->integer('customer_id'), fn (Builder $q, $id) => $q->where('customer_id', $id))
            ->when($request->input('currency'), fn (Builder $q, $currency) => $q->where('currency', $currency))
            ->when($request->integer('product_id'), function (Builder $q, $productId) use ($today) {
                $q->where(function (Builder $x) use ($productId, $today) {
                    $x->whereHas('items', fn (Builder $i) => $i->where('product_id', $productId)
                        ->where(fn (Builder $s) => $s->whereNull('active_from')->orWhereDate('active_from', '<=', $today))
                        ->where(fn (Builder $s) => $s->whereNull('stopped_at')->orWhereDate('stopped_at', '>', $today)))
                      ->orWhereHas('addendums', fn (Builder $a) => $a->where('status','active')
                        ->whereDate('service_start_date','<=',$today)
                        ->whereHas('items', fn (Builder $i) => $i->where('product_id',$productId)));
                });
            })
            ->when(trim((string) $request->input('q')), function (Builder $q, $search) {
                $term = '%'.trim($search).'%';
                $q->where(function (Builder $x) use ($term) {
                    $x->where('number', 'like', $term)
                      ->orWhereHas('customer', fn (Builder $c) => $c->where('name','like',$term)->orWhere('code','like',$term))
                      ->orWhereHas('items.product', fn (Builder $p) => $p->where('name','like',$term)->orWhere('code','like',$term));
                });
            })
            ->orderBy('customer_id')
            ->orderBy('service_start_date');

        $paginator = $query->paginate(config('finance.pagination', 25))->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(fn (Contract $contract) => $this->decorate($contract)));
        return $paginator;
    }

    private function decorate(Contract $contract): Contract
    {
        $today = today();
        $products = collect();
        foreach ($contract->items as $item) {
            if ($item->product && $item->isActiveOn($today)) $products->push($item->product);
        }
        foreach ($contract->addendums as $addendum) {
            foreach ($addendum->items as $item) if ($item->product) $products->push($item->product);
        }
        $contract->setAttribute('report_products', $products->unique('id')->values());

        $candidates = collect();
        foreach ($contract->receivables as $receivable) {
            $candidates->push([
                'date' => $receivable->due_date->copy()->startOfDay(),
                'type' => $receivable->type,
                'label' => FinanceOptions::receivableTypes()[$receivable->type] ?? $receivable->name,
                'amount' => (float) $receivable->remaining_amount,
                'generated' => true,
            ]);
        }

        if (in_array($contract->billing_cycle, ['monthly','annual'], true) && $contract->next_billing_date) {
            $date = $contract->next_billing_date->copy()->startOfDay();
            $net = $this->generator->recurringNetAt($contract, $date);
            if ($net > 0) $candidates->push($this->virtualCandidate($contract, $date, $contract->billing_cycle, $net));
        }
        if ($contract->billing_cycle === 'one_time' && $contract->next_maintenance_date) {
            $date = $contract->next_maintenance_date->copy()->startOfDay();
            $net = $this->generator->maintenanceNetAt($contract, $date);
            if ($net > 0) $candidates->push($this->virtualCandidate($contract, $date, 'maintenance', $net));
        }
        foreach ($contract->addendums as $addendum) {
            if ($addendum->next_maintenance_date && (float) $addendum->maintenance_total > 0) {
                $date = $addendum->next_maintenance_date->copy()->startOfDay();
                $net = (float) $addendum->maintenance_total;
                $tax = round($net * ((float) $contract->tax_rate / 100), 2);
                $candidates->push([
                    'date' => $date,
                    'type' => 'maintenance',
                    'label' => 'صيانة ملحق '.$addendum->number,
                    'amount' => $net + $tax,
                    'generated' => false,
                ]);
            }
        }

        $future = $candidates->filter(fn ($row) => $row['date']->gte($today))->sortBy('date')->values();
        if ($future->isEmpty()) {
            $contract->setAttribute('next_due_date_report', null);
            $contract->setAttribute('next_due_types_report', collect());
            $contract->setAttribute('next_due_amount_report', 0.0);
            $contract->setAttribute('next_due_generated_report', null);
            return $contract;
        }

        $firstDate = $future->first()['date'];
        $sameDate = $future->filter(fn ($row) => $row['date']->isSameDay($firstDate))->values();
        $contract->setAttribute('next_due_date_report', $firstDate);
        $contract->setAttribute('next_due_types_report', $sameDate->pluck('label')->unique()->values());
        $contract->setAttribute('next_due_amount_report', round($sameDate->sum('amount'), 2));
        $contract->setAttribute('next_due_generated_report', $sameDate->every(fn ($row) => $row['generated']));
        return $contract;
    }

    private function virtualCandidate(Contract $contract, Carbon $date, string $type, float $net): array
    {
        $tax = round($net * ((float) $contract->tax_rate / 100), 2);
        return [
            'date' => $date,
            'type' => $type,
            'label' => FinanceOptions::receivableTypes()[$type] ?? $type,
            'amount' => $net + $tax,
            'generated' => false,
        ];
    }
}
