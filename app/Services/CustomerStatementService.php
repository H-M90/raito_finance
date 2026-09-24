<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Receivable;
use App\Models\User;
use App\Support\OwnRecordVisibility;

class CustomerStatementService
{
    public function generate(Customer $customer, string $currency, ?string $from = null, ?string $to = null, ?int $contractId = null, string $movement = 'all', ?User $viewer = null): array
    {
        $to = $to ?: today()->toDateString();
        $base = OwnRecordVisibility::apply($customer->ledgerEntries()->where('currency', $currency)->where('is_reversed', false), $viewer);
        if ($contractId) {
            $base->where('contract_id', $contractId);
        }

        $opening = (clone $base)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '<', $from), fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw('COALESCE(SUM(debit-credit),0) balance')->value('balance') ?? 0;

        $entries = $base->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->whereDate('entry_date', '<=', $to)->orderBy('entry_date')->orderBy('id')->get();

        $receivableIds = $entries->where('source_type', Receivable::class)->pluck('source_id')->filter()->unique();
        $receivables = Receivable::whereIn('id', $receivableIds)->get()->keyBy('id');
        $labels = ['collection_allocation' => 'سند قبض', 'discount_voucher' => 'سند خصم'];
        $balance = (float) $opening;
        $allRows = $entries->map(function ($entry) use (&$balance, $labels, $receivables) {
            $balance = round($balance + (float) $entry->debit - (float) $entry->credit, 2);
            $receivable = $entry->source_type === Receivable::class ? $receivables->get($entry->source_id) : null;

            return [
                'entry' => $entry,
                'balance' => $balance,
                'label' => $entry->entry_type === 'receivable'
                    ? $this->receivableLabel($receivable?->type)
                    : ($labels[$entry->entry_type] ?? $entry->entry_type),
                'receivable' => $receivable,
            ];
        });

        $rows = $allRows->filter(function ($row) use ($movement) {
            if ($movement === 'all') {
                return true;
            }
            $entry = $row['entry'];
            $receivable = $row['receivable'];

            return match ($movement) {
                'receivables' => $entry->entry_type === 'receivable',
                'collections' => $entry->entry_type === 'collection_allocation',
                'discounts' => $entry->entry_type === 'discount_voucher',
                'overdue' => $entry->entry_type === 'receivable' && $receivable && (float) $receivable->remaining_amount > 0 && $receivable->due_date?->lt(today()),
                'maintenance' => $entry->entry_type === 'receivable' && in_array($receivable?->type, ['maintenance', 'opening_maintenance'], true),
                'subscriptions' => $entry->entry_type === 'receivable' && in_array($receivable?->type, ['monthly', 'annual'], true),
                'addendums' => $entry->entry_type === 'receivable' && $receivable?->type === 'addendum_installment',
                default => true,
            };
        })->values();

        $outstanding = OwnRecordVisibility::apply($customer->receivables()->where('currency', $currency)->outstanding(), $viewer);
        if ($contractId) {
            $outstanding->where('contract_id', $contractId);
        }
        $overdue = (float) (clone $outstanding)->whereDate('due_date', '<', today())->sum('remaining_amount');
        $upcoming = (float) (clone $outstanding)->whereDate('due_date', '>=', today())->sum('remaining_amount');

        return [
            'opening' => (float) $opening,
            'debit' => (float) $entries->sum('debit'),
            'credit' => (float) $entries->sum('credit'),
            'closing' => $balance,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'movement' => $movement,
            'rows' => $rows,
            'to' => $to,
        ];
    }

    private function receivableLabel(?string $type): string
    {
        return match ($type) {
            'installment' => 'دفعة عقد',
            'maintenance' => 'صيانة',
            'monthly' => 'اشتراك شهري',
            'annual' => 'اشتراك سنوي',
            'addendum_installment' => 'دفعة ملحق',
            'opening_contract' => 'رصيد افتتاحي عقد',
            'opening_maintenance' => 'رصيد افتتاحي صيانة',
            default => 'استحقاق',
        };
    }
}
