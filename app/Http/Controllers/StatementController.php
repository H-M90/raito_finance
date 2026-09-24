<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerStatementService;
use App\Services\StatementExportService;
use App\Support\FinanceOptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    private function data(Request $request, Customer $customer, CustomerStatementService $service): array
    {
        $validated = $request->validate(['currency' => 'nullable|in:SAR,USD,EGP', 'from' => 'nullable|date', 'to' => 'nullable|date|before_or_equal:today', 'contract_id' => 'nullable|integer|exists:contracts,id', 'movement' => 'nullable|in:all,receivables,collections,discounts,overdue,maintenance,subscriptions,addendums']);
        $currency = $validated['currency'] ?? 'SAR';
        $to = $validated['to'] ?? today()->toDateString();
        $from = $validated['from'] ?? null;
        $contractId = ! empty($validated['contract_id']) ? (int) $validated['contract_id'] : null;
        $movement = $validated['movement'] ?? 'all';
        if ($contractId && ! $customer->contracts()->whereKey($contractId)->exists()) {
            abort(404);
        }$statement = $service->generate($customer, $currency, $from, $to, $contractId, $movement, $request->user());
        $contracts = $customer->contracts()->orderByDesc('contract_date')->get(['id', 'number']);

        return compact('customer', 'currency', 'statement', 'contracts', 'to', 'movement') + ['currencies' => FinanceOptions::currencies()];
    }

    public function show(Request $request, Customer $customer, CustomerStatementService $service): View
    {
        return view('customers.statement', $this->data($request, $customer, $service));
    }

    public function print(Request $request, Customer $customer, CustomerStatementService $service): View
    {
        return view('customers.statement-print', $this->data($request, $customer, $service));
    }

    public function excel(Request $request, Customer $customer, CustomerStatementService $service, StatementExportService $exporter): BinaryFileResponse
    {
        $data = $this->data($request, $customer, $service);
        $path = $exporter->xlsx($customer, $data['currency'], $data['statement'], $request->input('from'), $data['to']);

        return response()->download($path, 'statement-'.$customer->code.'-'.$data['currency'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, Customer $customer, CustomerStatementService $service): Response
    {
        $data = $this->data($request, $customer, $service);
        $html = view('customers.statement-print', $data)->render();
        if (! class_exists(Dompdf::class)) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Raito-PDF-Fallback' => 'Install dompdf/dompdf then retry']);
        }
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="statement-'.$customer->code.'-'.$data['currency'].'.pdf"']);
    }
}
