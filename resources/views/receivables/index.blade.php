@extends('layouts.app')

@section('title', 'الاستحقاقات')
@section('page-title', 'الاستحقاقات والتحصيلات المطلوبة')
@section('page-subtitle', 'دفعات العقود والملحقات والصيانة والاشتراكات')

@section('content')
<div class="summary-strip" style="margin-bottom:14px">
    @foreach ($summaryByCurrency as $currency => $summary)
        <div class="summary-chip"><strong>{{ $currency }}</strong> · {{ number_format((float) $summary->remaining_amount_sum, 2) }} متبقي · {{ number_format((int) $summary->count_rows) }} استحقاق</div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <div><h2>قائمة الاستحقاقات</h2><p>{{ $receivables->total() }} حركة مطابقة</p></div>
        <div class="inline-actions">
            <a class="btn btn-light" href="{{ route('receivables.index', ['type' => 'maintenance', 'status' => 'overdue']) }}">صيانة متأخرة</a>
            @if (auth()->user()->hasPermission('receivables.create'))
                <a class="btn btn-outline" href="{{ route('receivables.create') }}">+ إضافة استحقاق</a>
            @endif
            @if (auth()->user()->hasPermission('collections.create'))
                <a class="btn btn-primary" href="{{ route('collections.create') }}">+ تسجيل تحصيل</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get">
        <div class="form-group search"><label>البحث</label><input class="input" name="q" value="{{ request('q') }}" placeholder="رقم الاستحقاق أو العميل أو البيان"></div>
        <div class="form-group"><label>النوع</label><select class="select" name="type"><option value="">كل الأنواع</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>الحالة</label><select class="select" name="status"><option value="">الكل</option><option value="future" @selected(request('status') === 'future')>مستقبلي</option><option value="due" @selected(request('status') === 'due')>مستحق اليوم</option><option value="overdue" @selected(request('status') === 'overdue')>متأخر</option><option value="partially_paid" @selected(request('status') === 'partially_paid')>محصل جزئيًا</option><option value="paid" @selected(request('status') === 'paid')>محصل بالكامل</option></select></div>
        <div class="form-group"><label>العملة</label><select class="select" name="currency"><option value="">الكل</option>@foreach (config('finance.currencies') as $code => $label)<option value="{{ $code }}" @selected(request('currency') === $code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>من</label><input class="input" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="form-group"><label>إلى</label><input class="input" type="date" name="to" value="{{ request('to') }}"></div>
        <button class="btn btn-secondary">عرض التقرير</button>
        <a class="btn btn-light" href="{{ route('receivables.index') }}">مسح</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>الرقم</th><th>العميل</th><th>النوع والبيان</th><th>العقد</th><th>تاريخ الاستحقاق</th><th>الإجمالي</th><th>المحصل</th><th>المتبقي</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse ($receivables as $row)
                @php($displayStatus = (float) $row->remaining_amount <= 0 ? 'paid' : ((float) $row->collected_amount > 0 ? 'partially_paid' : ($row->due_date->isPast() ? 'overdue' : ($row->due_date->isToday() ? 'due' : 'future'))))
                <tr>
                    <td>{{ $row->number }}</td>
                    <td><a href="{{ route('customers.show', $row->customer_id) }}">{{ $row->customer->name }}</a></td>
                    <td><span class="badge badge-info">{{ $types[$row->type] ?? $row->type }}</span><div>{{ $row->name }}</div>@if ($row->is_opening_balance)<div><span class="badge badge-dark">افتتاحي</span></div>@endif</td>
                    <td>{{ $row->contract?->number ?? '—' }}</td>
                    <td>{{ $row->due_date->format('Y-m-d') }}@if ($row->due_date->isPast() && $row->remaining_amount > 0)<div class="text-danger">{{ $row->due_date->diffInDays(today()) }} يوم تأخير</div>@endif</td>
                    <td class="money">{{ number_format((float) $row->total_amount, 2) }} {{ $row->currency }}</td>
                    <td class="money text-success">{{ number_format((float) $row->collected_amount, 2) }}</td>
                    <td class="money {{ $row->remaining_amount > 0 ? 'text-danger' : '' }}">{{ number_format((float) $row->remaining_amount, 2) }}</td>
                    <td><span class="badge {{ $displayStatus === 'paid' ? 'badge-success' : ($displayStatus === 'overdue' ? 'badge-danger' : ($displayStatus === 'partially_paid' ? 'badge-warning' : 'badge-info')) }}">{{ ['future' => 'مستقبلي', 'due' => 'مستحق اليوم', 'overdue' => 'متأخر', 'partially_paid' => 'محصل جزئيًا', 'paid' => 'محصل بالكامل'][$displayStatus] }}</span></td>
                    <td>
                        @if ($row->remaining_amount > 0 && $row->contract_id && !in_array($row->status, ['cancelled', 'waived'], true) && auth()->user()->hasPermission('collections.create'))
                            <a class="btn btn-sm btn-primary" href="{{ route('receivables.payment.create', $row) }}">سداد</a>
                        @endif
                        @if ($row->type === 'legacy_import_due' && $row->collection_allocations_count === 0 && auth()->user()->hasPermission('receivables.update'))
                            <a class="btn btn-sm btn-outline" href="{{ route('receivables.edit', $row) }}">تعديل</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty-state">لا توجد استحقاقات مطابقة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $receivables->links() }}
</div>
@endsection
