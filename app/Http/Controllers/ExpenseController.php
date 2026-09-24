<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\DocumentService;
use App\Services\ExpenseService;
use App\Support\FinanceOptions;
use App\Support\OwnRecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = OwnRecordVisibility::apply(Expense::with(['category:id,name', 'customer:id,name', 'contract:id,number', 'attachments']))->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('number', 'like', "%$v%")->orWhere('description', 'like', "%$v%")))->when($request->status, fn ($q, $v) => $q->where('status', $v))->latest('expense_date')->paginate(config('finance.pagination'))->withQueryString();

        return view('expenses.index', compact('expenses'));
    }

    public function create(): View
    {
        return view('expenses.form', $this->formData(new Expense));
    }

    public function edit(Expense $expense): View|RedirectResponse
    {
        if ($expense->status === 'cancelled') {
            return redirect()->route('expenses.index')->withErrors(['expense' => 'أعد فتح المصروف أولًا قبل تعديله.']);
        }

return view('expenses.form', $this->formData($expense));
    }

    private function formData(Expense $expense): array
    {
        $customers = Customer::active()->orderBy('name')->limit(50)->get(['id', 'name']);
        if ($expense->customer_id) {
            $customers = $customers->concat(Customer::whereKey($expense->customer_id)->get(['id', 'name']))->unique('id')->values();
        }$contracts = Contract::with('customer:id,name')->where('status', 'active')->orderByDesc('contract_date')->limit(100)->get(['id', 'number', 'customer_id']);
        if ($expense->contract_id) {
            $contracts = $contracts->concat(Contract::with('customer:id,name')->whereKey($expense->contract_id)->get(['id', 'number', 'customer_id']))->unique('id')->values();
        }

return ['expense' => $expense, 'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(), 'customers' => $customers, 'contracts' => $contracts, 'methods' => FinanceOptions::paymentMethods(), 'currencies' => FinanceOptions::currencies()];
    }

    public function store(StoreExpenseRequest $request, ExpenseService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        try {
            $expense = $service->create($data);
            if ($request->hasFile('attachment')) {
                $documents->attach($expense, $request->file('attachment'), 'مستند المصروف');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['expense' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف كمعتمد.');
    }

    public function update(StoreExpenseRequest $request, Expense $expense, ExpenseService $service, DocumentService $documents): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        try {
            $service->update($expense, $data);
            if ($request->hasFile('attachment')) {
                $documents->attach($expense, $request->file('attachment'), 'مستند المصروف');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['expense' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return redirect()->route('expenses.index')->with('success', 'تم تعديل المصروف.');
    }

    public function cancel(Request $request, Expense $expense, ExpenseService $service): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => 'required|string|min:3|max:1000']);
        try {
            $service->cancel($expense, $data['cancellation_reason']);
        } catch (\DomainException $e) {
            return back()->withErrors(['expense' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success', 'تم إلغاء المصروف مع الاحتفاظ بأثره للمراجعة.');
    }

    public function reopen(Expense $expense, ExpenseService $service): RedirectResponse
    {
        try {
            $service->reopen($expense);
        } catch (\DomainException $e) {
            return back()->withErrors(['expense' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success', 'تمت إعادة فتح المصروف.');
    }

    public function destroy(Expense $expense, ExpenseService $service): RedirectResponse
    {
        try {
            $service->delete($expense);
        } catch (\DomainException $e) {
            return back()->withErrors(['expense' => \App\Support\SafeExceptionMessage::from($e)]);
        }

return back()->with('success','تم حذف المصروف نهائيًا.');
    }
}
