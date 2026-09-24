<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\BankStatementBatch;
use App\Models\BankStatementRow;
use App\Models\CollectionAllocation;
use App\Models\Intermediary;
use App\Models\IntermediaryCommissionPayment;
use App\Models\Receivable;
use App\Models\Role;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Collection;
use App\Models\CustomerLedgerEntry;
use App\Models\PurchaseAllocation;
use App\Models\PurchaseItem;
use App\Models\ContractAddendumInstallment;
use App\Models\ContractAddendumItem;
use App\Models\ContractInstallment;
use App\Models\ContractItem;
use App\Models\CollectionFollowUp;
use App\Models\Contract;
use App\Models\ContractAddendum;
use App\Models\Customer;
use App\Models\CustomerSuccessProfile;
use App\Models\CustomerAttentionFlag;
use App\Models\CustomerCommercialSignal;
use App\Models\CustomerPlaybookRun;
use App\Models\CustomerSuccessTask;
use App\Models\CustomerSuccessEvent;
use App\Models\CustomerSuccessStatusHistory;
use App\Models\DiscountVoucher;
use App\Models\Expense;
use App\Models\Installation;
use App\Models\IntermediaryCommission;
use App\Models\PricingOffer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesQuotation;
use App\Models\SalesLeadTask;
use App\Models\SalesLeadNote;
use App\Models\SalesLeadInterest;
use App\Models\SalesLeadActivity;
use App\Models\SalesLead;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Paginator::useBootstrapFive();
        foreach ([User::class,Role::class,BankStatementBatch::class,BankStatementRow::class,Customer::class,Product::class,PricingOffer::class,SalesQuotation::class,Contract::class,ContractAddendum::class,Receivable::class,Collection::class,CollectionFollowUp::class,CollectionAllocation::class,CustomerLedgerEntry::class,ContractItem::class,ContractInstallment::class,ContractAddendumItem::class,ContractAddendumInstallment::class,PurchaseItem::class,PurchaseAllocation::class,DiscountVoucher::class,Purchase::class,Expense::class,Installation::class,Station::class,Supplier::class,Intermediary::class,IntermediaryCommission::class,IntermediaryCommissionPayment::class,Attachment::class,CustomerSuccessProfile::class,CustomerAttentionFlag::class,CustomerCommercialSignal::class,CustomerPlaybookRun::class,CustomerSuccessTask::class,CustomerSuccessEvent::class,CustomerSuccessStatusHistory::class,SalesLead::class,SalesLeadActivity::class,SalesLeadInterest::class,SalesLeadNote::class,SalesLeadTask::class] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
