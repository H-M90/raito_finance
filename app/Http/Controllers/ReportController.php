<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use App\Services\ProfitabilityService;
use App\Services\CustomerContractDueReportService;
use App\Models\Product;
use App\Support\FinanceOptions;
use App\Services\RecurringReceivableGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ReportController extends Controller
{
    public function customerProductsDue(Request $request, CustomerContractDueReportService $service): View
    {
        return view('reports.customer-products-due', [
            'contracts' => $service->report($request),
            'customers' => Customer::active()->orderBy('name')->limit(100)->get(['id','name','code']),
            'products' => Product::active()->orderBy('name')->get(['id','name','code']),
            'currencies' => FinanceOptions::currencies(),
        ]);
    }

    public function generateCustomerProductsDue(Request $request, RecurringReceivableGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'until_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $until = $data['until_date'] ?? $generator->horizon()->toDateString();
        $created = $generator->generateDue($until);

        return redirect()
            ->route('reports.customer-products-due')
            ->with('success', 'تم احتساب وتوليد '.$created.' استحقاق جديد حتى تاريخ '.$until.'. لا تتكرر الاستحقاقات الموجودة.');
    }

    public function profitability(Request $request,ProfitabilityService $service): View
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'currency' => ['nullable', 'in:SAR,USD,EGP'],
            'mode' => ['nullable', 'in:cash,lifetime'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $currency = $data['currency'] ?? config('finance.default_currency', 'SAR');
        $mode = $data['mode'] ?? 'cash';
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;
        $report = $mode === 'lifetime'
            ? $service->lifetimeReport($customerId, $currency)
            : $service->report($customerId, $currency, $from, $to);

        return view('reports.profitability', [
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'customers' => Customer::active()->orderBy('name')->limit(100)->get(['id','name']),
            'currencies' => FinanceOptions::currencies(),
            'currency' => $currency,
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
