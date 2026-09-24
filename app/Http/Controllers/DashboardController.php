<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerSuccessTask;
use App\Models\Receivable;
use App\Models\SalesQuotation;
use App\Models\SalesLead;
use App\Models\SalesLeadTask;
use App\Services\CustomerSuccessService;
use App\Support\OwnRecordVisibility;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $viewer = request()->user();
        $cacheKey = 'finance.dashboard.v2.user-'.$viewer->id;
        $data = Cache::remember($cacheKey, config('finance.dashboard_cache_seconds', 60), function () use ($viewer) {
            $receivables = fn () => OwnRecordVisibility::apply(Receivable::query(), $viewer);
            $collections = fn () => OwnRecordVisibility::apply(Collection::query(), $viewer);
            $contracts = fn () => OwnRecordVisibility::apply(Contract::query(), $viewer);
            $quotations = fn () => OwnRecordVisibility::apply(SalesQuotation::query(), $viewer);

            $salesFollowUpsToday = collect();
            $salesFollowUpsOverdueCount = 0;
            $salesLeadOpenCount = 0;
            if ($viewer->hasPermission('sales-leads.view')) {
                $visibleLeads = SalesLead::query();
                if (OwnRecordVisibility::restricts($viewer)) {
                    $visibleLeads->where(function ($q) use ($viewer) {
                        $q->where('owner_id', $viewer->id)->orWhere('created_by', $viewer->id);
                    });
                }
                $salesLeadOpenCount = (clone $visibleLeads)->open()->count();
                if ($viewer->hasPermission('sales-leads.tasks')) {
                    $leadIds = (clone $visibleLeads)->select('id');
                    $salesTaskQuery = SalesLeadTask::query()
                        ->with(['lead.owner','assignee'])
                        ->whereIn('sales_lead_id', $leadIds)
                        ->where('status', 'open')
                        ->whereNotNull('due_at')
                        ->where('due_at', '<=', today()->endOfDay());
                    $salesFollowUpsOverdueCount = (clone $salesTaskQuery)->where('due_at', '<', today()->startOfDay())->count();
                    $salesFollowUpsToday = $salesTaskQuery
                        ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                        ->orderBy('due_at')
                        ->limit(12)
                        ->get();
                }
            }

            $customerFollowUpsToday = collect();
            $customerFollowUpsOverdueCount = 0;
            if ($viewer->hasPermission('customer-success.view')) {
                $followUpQuery = CustomerSuccessTask::query()
                    ->with(['customer.successProfile.lifecycleStatus','assignee'])
                    ->open()
                    ->where('title','like',CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX.'%')
                    ->whereNotNull('due_at')
                    ->where('due_at','<=',today()->endOfDay());
                if (! $viewer->hasPermission('customer-success.dashboard')) {
                    $followUpQuery->where('assigned_to',$viewer->id);
                }
                $customerFollowUpsOverdueCount = (clone $followUpQuery)->where('due_at','<',today()->startOfDay())->count();
                $customerFollowUpsToday = $followUpQuery->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('due_at')->limit(12)->get();
            }

            return [
                'activeCustomers' => Customer::active()->count(),
                'activeContracts' => $contracts()->active()->count(),
                'openQuotations' => $quotations()->current()->whereIn('status', ['draft', 'sent', 'negotiation'])->count(),
                'overdueCount' => $receivables()->outstanding()->whereDate('due_date', '<', today())->count(),
                'receivablesByCurrency' => $receivables()->outstanding()->selectRaw('currency, SUM(remaining_amount) total')->groupBy('currency')->pluck('total', 'currency'),
                'monthCollections' => $collections()->where('status', 'confirmed')->whereBetween('collection_date', [now()->startOfMonth(), now()->endOfMonth()])->selectRaw('currency, SUM(amount) total')->groupBy('currency')->pluck('total', 'currency'),
                'upcoming' => OwnRecordVisibility::apply(Receivable::with(['customer:id,name', 'contract:id,number']), $viewer)->outstanding()->whereBetween('due_date', [today(), today()->addDays(30)])->orderBy('due_date')->limit(8)->get(),
                'overdue' => OwnRecordVisibility::apply(Receivable::with(['customer:id,name', 'contract:id,number']), $viewer)->outstanding()->whereDate('due_date', '<', today())->orderBy('due_date')->limit(8)->get(),
                'salesFollowUpsToday' => $salesFollowUpsToday,
                'salesFollowUpsOverdueCount' => $salesFollowUpsOverdueCount,
                'salesLeadOpenCount' => $salesLeadOpenCount,
                'customerFollowUpsToday' => $customerFollowUpsToday,
                'customerFollowUpsOverdueCount' => $customerFollowUpsOverdueCount,
            ];
        });

        return view('dashboard', $data);
    }
}
