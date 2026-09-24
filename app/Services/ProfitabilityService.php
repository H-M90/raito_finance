<?php

namespace App\Services;

use App\Models\Contract;
use App\Support\OwnRecordVisibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitabilityService
{
    public function lifetimeReport(?int $customerId, string $currency): array
    {
        $rows = $this->contracts($customerId, $currency, 'lifetime')->map(fn (Contract $contract) => $this->row(
            'contract',
            $contract->number,
            $contract->customer->name,
            route('contracts.show', $contract),
            (float) $contract->calculated_revenue,
            (float) $contract->purchase_cost,
            (float) $contract->expense_cost,
            (float) $contract->commission_cost,
        ));

        $revenue = round($rows->sum('revenue'), 2);
        $purchase = round($rows->sum('purchase'), 2);
        $expenses = round($rows->sum('expenses'), 2);
        $commissions = round($rows->sum('commissions'), 2);
        $profit = round($revenue - $purchase - $expenses - $commissions, 2);

        return ['rows' => $rows, 'summary' => [
            'revenue' => $revenue,
            'purchase' => $purchase,
            'expenses' => $expenses,
            'commissions' => $commissions,
            'profit' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0,
        ]];
    }

    /**
     * Actual cash profitability for a period.
     * Revenue is cash collected NET of VAT, not the gross receipt.
     * Costs include purchases, approved expenses, and actually paid intermediary commissions.
     */
    public function report(?int $customerId, string $currency, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->startOfYear()->toDateString();
        $to = $to ?: today()->toDateString();
        $viewer = auth()->user();

        $revenueBase = DB::table('collection_allocations')
            ->join('collections', 'collections.id', '=', 'collection_allocations.collection_id')
            ->join('receivables', 'receivables.id', '=', 'collection_allocations.receivable_id')
            ->where('collections.status', 'confirmed')
            ->whereNull('collections.deleted_at')
            ->whereNull('receivables.deleted_at')
            ->where('collections.currency', $currency)
            ->whereDate('collections.collection_date', '>=', $from)
            ->whereDate('collections.collection_date', '<=', $to)
            ->when($customerId, fn (Builder $q) => $q->where('collections.customer_id', $customerId));
        OwnRecordVisibility::applyColumn($revenueBase, 'collections.created_by', $viewer);

        // Collections are gross (VAT inclusive). Profitability must use the net share of each receivable.
        $netRevenueExpression = 'collection_allocations.amount * (1.0 * receivables.net_amount / NULLIF(receivables.total_amount, 0))';
        $revenueTotal = (float) (clone $revenueBase)->selectRaw('COALESCE(SUM('.$netRevenueExpression.'),0) total')->value('total');
        $revenueByContract = (clone $revenueBase)
            ->whereNotNull('receivables.contract_id')
            ->selectRaw('receivables.contract_id AS contract_id, COALESCE(SUM('.$netRevenueExpression.'),0) AS total')
            ->groupBy('receivables.contract_id')
            ->pluck('total', 'contract_id');

        $purchaseAllocationBase = DB::table('purchase_allocations')
            ->join('purchase_items', 'purchase_items.id', '=', 'purchase_allocations.purchase_item_id')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'confirmed')
            ->where('purchases.currency', $currency)
            ->whereDate('purchases.purchase_date', '>=', $from)
            ->whereDate('purchases.purchase_date', '<=', $to)
            ->when($customerId, fn (Builder $q) => $q->where('purchase_allocations.customer_id', $customerId));
        OwnRecordVisibility::applyColumn($purchaseAllocationBase, 'purchases.created_by', $viewer);

        $purchaseByContract = (clone $purchaseAllocationBase)
            ->whereNotNull('purchase_allocations.contract_id')
            ->selectRaw('purchase_allocations.contract_id AS contract_id, SUM(purchase_allocations.amount) AS total')
            ->groupBy('purchase_allocations.contract_id')
            ->pluck('total', 'contract_id');

        if ($customerId) {
            $purchaseTotal = (float) (clone $purchaseAllocationBase)->sum('purchase_allocations.amount');
        } else {
            $purchaseTotalQuery = DB::table('purchases')
                ->where('status', 'confirmed')
                ->where('currency', $currency)
                ->whereDate('purchase_date', '>=', $from)
                ->whereDate('purchase_date', '<=', $to);
            OwnRecordVisibility::applyColumn($purchaseTotalQuery, 'purchases.created_by', $viewer);
            $purchaseTotal = (float) $purchaseTotalQuery->sum('total_amount');
        }

        $expenseBase = DB::table('expenses')
            ->where('status', 'approved')
            ->where('currency', $currency)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->when($customerId, fn (Builder $q) => $q->where('customer_id', $customerId));
        OwnRecordVisibility::applyColumn($expenseBase, 'expenses.created_by', $viewer);

        $expenseTotal = (float) (clone $expenseBase)->sum('amount');
        $expenseByContract = (clone $expenseBase)
            ->whereNotNull('contract_id')
            ->selectRaw('contract_id, SUM(amount) AS total')
            ->groupBy('contract_id')
            ->pluck('total', 'contract_id');

        $commissionBase = DB::table('intermediary_commission_payments')
            ->join('intermediary_commissions', 'intermediary_commissions.id', '=', 'intermediary_commission_payments.intermediary_commission_id')
            ->join('contracts', 'contracts.id', '=', 'intermediary_commissions.contract_id')
            ->where('intermediary_commissions.status', '!=', 'cancelled')
            ->where('intermediary_commissions.currency', $currency)
            ->whereDate('intermediary_commission_payments.payment_date', '>=', $from)
            ->whereDate('intermediary_commission_payments.payment_date', '<=', $to)
            ->when($customerId, fn (Builder $q) => $q->where('contracts.customer_id', $customerId));
        OwnRecordVisibility::applyColumn($commissionBase, 'intermediary_commission_payments.created_by', $viewer);

        $commissionTotal = (float) (clone $commissionBase)->sum('intermediary_commission_payments.amount');
        $commissionByContract = (clone $commissionBase)
            ->selectRaw('intermediary_commissions.contract_id AS contract_id, SUM(intermediary_commission_payments.amount) AS total')
            ->groupBy('intermediary_commissions.contract_id')
            ->pluck('total', 'contract_id');

        $contractIds = $revenueByContract->keys()
            ->merge($purchaseByContract->keys())
            ->merge($expenseByContract->keys())
            ->merge($commissionByContract->keys())
            ->unique()->values();

        $contractsQuery = Contract::with('customer:id,name')
            ->whereIn('id', $contractIds)
            ->where('currency', $currency)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));
        OwnRecordVisibility::apply($contractsQuery, $viewer);
        $contracts = $contractIds->isEmpty() ? collect() : $contractsQuery->orderByDesc('contract_date')->get();

        $rows = $contracts->map(fn (Contract $contract) => $this->row(
            'contract',
            $contract->number,
            $contract->customer->name,
            route('contracts.show', $contract),
            (float) ($revenueByContract[$contract->id] ?? 0),
            (float) ($purchaseByContract[$contract->id] ?? 0),
            (float) ($expenseByContract[$contract->id] ?? 0),
            (float) ($commissionByContract[$contract->id] ?? 0),
        ));

        $generalRevenue = round(max(0, $revenueTotal - (float) $revenueByContract->sum()), 2);
        $generalPurchase = round(max(0, $purchaseTotal - (float) $purchaseByContract->sum()), 2);
        $generalExpense = round(max(0, $expenseTotal - (float) $expenseByContract->sum()), 2);
        $generalCommission = round(max(0, $commissionTotal - (float) $commissionByContract->sum()), 2);

        if ($generalRevenue > 0 || $generalPurchase > 0 || $generalExpense > 0 || $generalCommission > 0) {
            $rows->push($this->row(
                'general',
                'عام / بدون عقد',
                $customerId ? 'حركات غير مرتبطة بعقد' : 'حركات عامة غير مرتبطة بعقد',
                null,
                $generalRevenue,
                $generalPurchase,
                $generalExpense,
                $generalCommission,
            ));
        }

        $profit = round($revenueTotal - $purchaseTotal - $expenseTotal - $commissionTotal, 2);

        return [
            'rows' => $rows,
            'summary' => [
                'revenue' => round($revenueTotal, 2),
                'purchase' => round($purchaseTotal, 2),
                'expenses' => round($expenseTotal, 2),
                'commissions' => round($commissionTotal, 2),
                'profit' => $profit,
                'margin' => $revenueTotal > 0 ? round($profit / $revenueTotal * 100, 2) : 0,
            ],
        ];
    }

    private function row(string $type, string $label, string $customer, ?string $url, float $revenue, float $purchase, float $expenses, float $commissions): array
    {
        $profit = round($revenue - $purchase - $expenses - $commissions, 2);

        return [
            'type' => $type,
            'label' => $label,
            'customer' => $customer,
            'url' => $url,
            'revenue' => round($revenue, 2),
            'purchase' => round($purchase, 2),
            'expenses' => round($expenses, 2),
            'commissions' => round($commissions, 2),
            'profit' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0,
        ];
    }

    /**
     * Historical modes retained for internal callers. Cash revenue is net of VAT,
     * and commission treatment is consistent with the selected basis.
     */
    public function contracts(?int $customerId, string $currency, string $mode = 'cash', ?string $from = null, ?string $to = null): Collection
    {
        $from = $from ?: now()->startOfYear()->toDateString();
        $to = $to ?: today()->toDateString();
        if ($mode === 'lifetime') {
            $from = '1900-01-01';
            $to = today()->toDateString();
        }

        $contractsQuery = Contract::with('customer:id,name')
            ->where('currency', $currency)
            ->when($mode !== 'lifetime', fn ($query) => $query->where('status', 'active'))
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId));
        OwnRecordVisibility::apply($contractsQuery);
        $contracts = $contractsQuery->orderByDesc('contract_date')->get();
        $ids = $contracts->pluck('id');
        if ($ids->isEmpty()) return $contracts;

        $sumBy = function (Builder $query, string $group, string $expression = 'SUM(amount)') {
            return $query->selectRaw($group.' AS group_key')->selectRaw($expression.' AS aggregate_value')->groupBy($group)->get()->pluck('aggregate_value', 'group_key');
        };
        $dateRange = static fn (Builder $query, string $column, string $start, string $end): Builder => $query->whereDate($column, '>=', $start)->whereDate($column, '<=', $end);

        $addendums = $sumBy(DB::table('contract_addendums')->whereIn('contract_id', $ids)->where('status', 'active'), 'contract_id', 'SUM(net_total)');
        $earned = $sumBy($dateRange(DB::table('receivables')->whereIn('contract_id', $ids)->whereNull('deleted_at')->whereNotIn('status', ['cancelled','needs_review']), 'due_date', $from, $to), 'contract_id', 'SUM(net_amount)');
        $discounts = $sumBy($dateRange(DB::table('discount_vouchers')->join('receivables', 'receivables.id', '=', 'discount_vouchers.receivable_id')->whereIn('discount_vouchers.contract_id', $ids)->where('discount_vouchers.status', 'active')->whereNull('discount_vouchers.deleted_at'), 'discount_vouchers.voucher_date', $from, $to), 'discount_vouchers.contract_id', 'SUM(discount_vouchers.amount * (1.0 * receivables.net_amount / NULLIF(receivables.total_amount,0)))');

        $cashQuery = $dateRange(DB::table('collection_allocations')->join('collections', 'collections.id', '=', 'collection_allocations.collection_id')->join('receivables', 'receivables.id', '=', 'collection_allocations.receivable_id')->whereIn('receivables.contract_id', $ids)->where('collections.status', 'confirmed')->whereNull('collections.deleted_at'), 'collections.collection_date', $from, $to);
        OwnRecordVisibility::applyColumn($cashQuery, 'collections.created_by');
        $cash = $sumBy(clone $cashQuery, 'receivables.contract_id', 'SUM(collection_allocations.amount * (1.0 * receivables.net_amount / NULLIF(receivables.total_amount,0)))');
        $collectedGross = $sumBy(clone $cashQuery, 'receivables.contract_id', 'SUM(collection_allocations.amount)');

        $purchaseQuery = DB::table('purchase_allocations')->join('purchase_items', 'purchase_items.id', '=', 'purchase_allocations.purchase_item_id')->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')->join('contracts', 'contracts.id', '=', 'purchase_allocations.contract_id')->whereIn('purchase_allocations.contract_id', $ids)->where('purchases.status', 'confirmed')->where('contracts.currency', $currency);
        $expenseQuery = DB::table('expenses')->join('contracts', 'contracts.id', '=', 'expenses.contract_id')->whereIn('expenses.contract_id', $ids)->where('expenses.status', 'approved')->where('contracts.currency', $currency);
        $commissionDueQuery = DB::table('intermediary_commissions')->whereIn('contract_id', $ids)->where('status', '!=', 'cancelled')->where('currency', $currency);
        $commissionPaidQuery = DB::table('intermediary_commission_payments')->join('intermediary_commissions', 'intermediary_commissions.id', '=', 'intermediary_commission_payments.intermediary_commission_id')->whereIn('intermediary_commissions.contract_id', $ids)->where('intermediary_commissions.status', '!=', 'cancelled')->where('intermediary_commissions.currency', $currency);
        OwnRecordVisibility::applyColumn($purchaseQuery, 'purchases.created_by');
        OwnRecordVisibility::applyColumn($expenseQuery, 'expenses.created_by');
        OwnRecordVisibility::applyColumn($commissionDueQuery, 'intermediary_commissions.created_by');
        OwnRecordVisibility::applyColumn($commissionPaidQuery, 'intermediary_commission_payments.created_by');
        if ($mode !== 'contractual') {
            $dateRange($purchaseQuery, 'purchases.purchase_date', $from, $to);
            $dateRange($expenseQuery, 'expenses.expense_date', $from, $to);
            $dateRange($commissionDueQuery, 'due_date', $from, $to);
            $dateRange($commissionPaidQuery, 'intermediary_commission_payments.payment_date', $from, $to);
        }

        $purchase = $sumBy($purchaseQuery, 'purchase_allocations.contract_id', 'SUM(purchase_allocations.amount)');
        $expenses = $sumBy($expenseQuery, 'expenses.contract_id', 'SUM(expenses.amount)');
        $commissionDue = $sumBy($commissionDueQuery, 'contract_id', 'SUM(amount)');
        $commissionPaid = $sumBy($commissionPaidQuery, 'intermediary_commissions.contract_id', 'SUM(intermediary_commission_payments.amount)');

        return $contracts->map(function (Contract $contract) use ($mode, $addendums, $earned, $discounts, $cash, $collectedGross, $purchase, $expenses, $commissionDue, $commissionPaid) {
            $revenue = match ($mode) {
                'earned', 'lifetime' => (float) ($earned[$contract->id] ?? 0) - (float) ($discounts[$contract->id] ?? 0),
                'cash' => (float) ($cash[$contract->id] ?? 0),
                default => (float) $contract->net_total + (float) ($addendums[$contract->id] ?? 0),
            };
            $purchaseCost = (float) ($purchase[$contract->id] ?? 0);
            $expenseCost = (float) ($expenses[$contract->id] ?? 0);
            $commissionCost = match ($mode) {
                'cash' => (float) ($commissionPaid[$contract->id] ?? 0),
                'earned', 'lifetime' => (float) ($commissionDue[$contract->id] ?? 0),
                default => (float) $contract->commission_total,
            };
            $profit = round($revenue - $purchaseCost - $expenseCost - $commissionCost, 2);
            foreach ([
                'calculated_revenue' => $revenue,
                'purchase_cost' => $purchaseCost,
                'expense_cost' => $expenseCost,
                'commission_cost' => $commissionCost,
                'collected_total' => (float) ($collectedGross[$contract->id] ?? 0) + ($mode === 'lifetime' ? (float) $contract->previous_collections_total : 0.0),
                'historical_collections_total' => (float) $contract->previous_collections_total,
                'calculated_profit' => $profit,
                'profit_margin' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0,
            ] as $key => $value) $contract->setAttribute($key, $value);
            return $contract;
        });
    }
}
