<?php

namespace App\Services;

use App\Models\CollectionAllocation;
use App\Models\CustomerLedgerEntry;
use App\Models\DiscountVoucher;
use App\Models\Receivable;

class LedgerService
{
    public function postReceivable(Receivable $receivable): CustomerLedgerEntry
    {
        return CustomerLedgerEntry::updateOrCreate(['source_type' => $receivable::class, 'source_id' => $receivable->id, 'entry_type' => 'receivable'], ['customer_id' => $receivable->customer_id, 'contract_id' => $receivable->contract_id, 'entry_date' => $receivable->due_date, 'currency' => $receivable->currency, 'reference_no' => $receivable->number, 'description' => $receivable->name, 'debit' => $receivable->total_amount, 'credit' => 0, 'is_reversed' => false, 'posted_at' => now(), 'created_by' => $receivable->created_by]);
    }

    public function postCollectionAllocation(CollectionAllocation $allocation): CustomerLedgerEntry
    {
        $allocation->loadMissing(['collection', 'receivable']);

        return CustomerLedgerEntry::updateOrCreate(['source_type' => $allocation::class, 'source_id' => $allocation->id, 'entry_type' => 'collection_allocation'], ['customer_id' => $allocation->collection->customer_id, 'contract_id' => $allocation->receivable->contract_id, 'entry_date' => $allocation->collection->collection_date, 'currency' => $allocation->collection->currency, 'reference_no' => $allocation->collection->number, 'description' => 'سند قبض مقابل '.$allocation->receivable->name, 'debit' => 0, 'credit' => $allocation->amount, 'is_reversed' => false, 'posted_at' => now(), 'created_by' => $allocation->collection->created_by]);
    }

    public function postDiscountVoucher(DiscountVoucher $voucher): CustomerLedgerEntry
    {
        return CustomerLedgerEntry::updateOrCreate(['source_type' => $voucher::class, 'source_id' => $voucher->id, 'entry_type' => 'discount_voucher'], ['customer_id' => $voucher->customer_id, 'contract_id' => $voucher->contract_id, 'entry_date' => $voucher->voucher_date, 'currency' => $voucher->currency, 'reference_no' => $voucher->number, 'description' => 'سند خصم: '.$voucher->reason, 'debit' => 0, 'credit' => $voucher->amount, 'is_reversed' => false, 'posted_at' => now(), 'created_by' => $voucher->created_by]);
    }
}
