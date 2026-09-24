<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ContractItemPricingService
{
    public function __construct(
        private PricingCalculator $pricing,
        private RecurringReceivableGenerator $generator,
        private TimelineService $timeline,
    ) {}

    public function update(Contract $contract, ContractItem $item, array $data): array
    {
        if ((int) $item->contract_id !== (int) $contract->id) {
            abort(404);
        }

        return DB::transaction(function () use ($contract, $item, $data) {
            $contract = Contract::with(['items.product', 'pricingOffers.products', 'maintenanceSettings'])
                ->lockForUpdate()
                ->findOrFail($contract->id);
            $item = $contract->items->firstWhere('id', $item->id);
            if (! $item) {
                abort(404);
            }
            if ($contract->status !== 'active') {
                throw new \DomainException('لا يمكن تعديل بند داخل عقد ملغى.');
            }
            if ($item->stopped_at) {
                throw new \DomainException('أعد تفعيل البند أولًا قبل تعديل تسعيره.');
            }
            if ($contract->billing_cycle === 'one_time' && $contract->installments()->exists()) {
                throw new \DomainException('تعديل سعر بند في عقد ذي دفعات يتطلب تعديل العقد للحفاظ على مطابقة جدول الدفعات.');
            }

            $affectedTypes = $contract->billing_cycle === 'one_time'
                ? ['maintenance']
                : [$contract->billing_cycle];
            $hasFinancialMovements = $contract->receivables()
                ->whereNull('contract_addendum_id')
                ->whereIn('type', $affectedTypes)
                ->whereDate('due_date', '>=', today())
                ->where(fn ($query) => $query->where('collected_amount', '>', 0)->orWhere('discounted_amount', '>', 0))
                ->exists();
            if ($hasFinancialMovements) {
                throw new \DomainException('لا يمكن تعديل البند لأن استحقاقًا متأثرًا به عليه تحصيل أو سند خصم.');
            }

            $payload = $contract->items->map(function (ContractItem $row) use ($item, $data) {
                return [
                    'product_id' => $row->product_id,
                    'product' => $row->product,
                    'quantity' => $row->quantity,
                    'requested_users' => $row->id === $item->id ? $data['requested_users'] : $row->requested_users,
                    'user_unit_price' => $row->id === $item->id ? $data['user_unit_price'] : $row->user_unit_price,
                    'unit_price' => $row->id === $item->id ? $data['unit_price'] : $row->unit_price,
                    'discount_value' => $row->discount_value,
                    'maintenance_rate' => $row->maintenance_rate,
                    'notes' => $row->notes,
                ];
            })->all();

            $totals = $this->pricing->calculate(
                $payload,
                (float) $contract->tax_rate,
                $contract->billing_cycle,
                $contract->pricingOffers,
                $contract->commission_type,
                (float) $contract->commission_value,
            );
            foreach ($totals['calculatedItems'] as $calculated) {
                ContractItem::whereKey($contract->items->firstWhere('product_id', $calculated['product_id'])->id)->update([
                    'requested_users' => $calculated['requested_users'],
                    'included_users' => $calculated['included_users'],
                    'promotional_free_users' => $calculated['promotional_free_users'],
                    'billable_users' => $calculated['billable_users'],
                    'total_licensed_users' => $calculated['total_licensed_users'],
                    'unit_price' => $calculated['unit_price'],
                    'user_unit_price' => $calculated['user_unit_price'],
                    'user_total' => $calculated['user_total'],
                    'discount_value' => $calculated['discount_value'],
                    'promotional_discount_value' => $calculated['promotional_discount_value'],
                    'line_subtotal' => $calculated['line_subtotal'],
                    'line_net' => $calculated['line_net'],
                    'maintenance_rate' => $calculated['maintenance_rate'],
                    'maintenance_annual' => $calculated['maintenance_annual'],
                ]);
            }

            $products = $contract->items->mapWithKeys(fn (ContractItem $row) => [$row->product_id => $row->product]);
            $snapshot = $this->pricing->snapshot($totals, $contract->pricingOffers, $products, $contract->billing_cycle, $contract->currency);
            $legacyManualTotal = $contract->is_imported && ($contract->pricing_snapshot['pricing_mode'] ?? null) === 'legacy_manual_total';
            if ($legacyManualTotal) {
                $snapshot['pricing_mode'] = 'legacy_manual_total';
                $snapshot['manual_contract_total'] = (float) $contract->grand_total;
            }
            $contractUpdates = [
                'maintenance_total' => $contract->billing_cycle === 'one_time' ? $totals['maintenanceTotal'] : 0,
                'commission_total' => $totals['commissionTotal'],
                'pricing_snapshot' => $snapshot,
                'updated_by' => auth()->id(),
            ];
            if (! $legacyManualTotal) {
                $contractUpdates = array_merge($contractUpdates, [
                    'subtotal' => $totals['subtotal'],
                    'discount_total' => $totals['discountTotal'],
                    'promotional_discount_total' => $totals['promotionalDiscountTotal'],
                    'net_total' => $totals['netTotal'],
                    'tax_total' => $totals['taxTotal'],
                    'grand_total' => $totals['grandTotal'],
                ]);
            }
            $contract->update($contractUpdates);

            $adjusted = $this->generator->reconcileUnsettledFrom($contract->fresh(['items', 'maintenanceSettings']), today());
            $created = $this->generator->generateForContract($contract->fresh(['items', 'maintenanceSettings']));
            $updatedItem = ContractItem::with('product')->findOrFail($item->id);
            $this->timeline->record(
                $contract->customer_id,
                'contract',
                'تعديل مستخدمي وتسعير بند',
                $updatedItem->product->name.' · المستخدمون '.$updatedItem->requested_users.' · السعر '.number_format((float) $updatedItem->unit_price, 2),
                $contract,
                $contract->id,
                route('contracts.index'),
            );

            return ['item' => $updatedItem, 'adjusted_receivables' => $adjusted, 'created_receivables' => $created];
        });
    }
}
