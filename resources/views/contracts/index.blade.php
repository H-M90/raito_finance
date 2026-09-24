@extends('layouts.app')

@section('title', 'العقود')
@section('page-title', 'العقود')
@section('page-subtitle', 'فلترة العقود حسب الحقول المعروضة، مع عرض البنود وتعديلها مباشرة')

@section('content')
@php($statuses = array_replace(['active' => 'مفعل', 'inactive' => 'غير مفعل', 'cancelled' => 'ملغي'], $statuses))
<div class="card">
    <div class="card-header">
        <div><h2>قائمة العقود</h2><p>{{ $contracts->total() }} عقد</p></div>
        @if (auth()->user()->hasPermission('contracts.create'))<a class="btn btn-primary" href="{{ route('contracts.create') }}">+ عقد جديد</a>@endif
    </div>

    <form class="form-grid" method="get" style="margin-bottom:18px">
        <div class="form-group col-3"><label>رقم العقد</label><input class="input" name="number" value="{{ request('number') }}" placeholder="رقم العقد"></div>
        <div class="form-group col-3"><label>العميل</label><input class="input" name="customer" value="{{ request('customer') }}" placeholder="اسم العميل"></div>
        <div class="form-group col-3"><label>بداية الخدمة من</label><input class="input" type="date" name="service_start_from" value="{{ request('service_start_from') }}"></div>
        <div class="form-group col-3"><label>بداية الخدمة إلى</label><input class="input" type="date" name="service_start_to" value="{{ request('service_start_to') }}"></div>
        <div class="form-group col-3"><label>الدورية</label><select class="select" name="billing_cycle"><option value="">الكل</option>@foreach (\App\Support\FinanceOptions::billingCycles() as $value => $label)<option value="{{ $value }}" @selected(request('billing_cycle') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-3"><label>الاستحقاق القادم من</label><input class="input" type="date" name="next_due_from" value="{{ request('next_due_from') }}"></div>
        <div class="form-group col-3"><label>الاستحقاق القادم إلى</label><input class="input" type="date" name="next_due_to" value="{{ request('next_due_to') }}"></div>
        <div class="form-group col-3"><label>الحالة</label><select class="select" name="status"><option value="">الكل</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-12"><button class="btn btn-secondary">تطبيق الفلاتر</button> <a class="btn btn-light" href="{{ route('contracts.index') }}">مسح الفلاتر</a></div>
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr><th>العقد</th><th>العميل</th><th>بداية الخدمة</th><th>الدورية</th><th>الإجمالي</th><th>الصيانة الحالية</th><th>الاستحقاق القادم</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse ($contracts as $contract)
                <tr>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline" data-contract-items-toggle data-target="contract-items-{{ $contract->id }}" aria-expanded="false">{{ $contract->number }}</button>
                        @if ($contract->is_imported)<div><span class="badge badge-dark">عقد قديم</span></div>@endif
                    </td>
                    <td>{{ $contract->customer->name }}</td>
                    <td>{{ $contract->service_start_date->format('Y-m-d') }}</td>
                    <td>{{ \App\Support\FinanceOptions::billingCycles()[$contract->billing_cycle] ?? $contract->billing_cycle }}</td>
                    <td class="money">{{ number_format((float) $contract->grand_total, 2) }} {{ $contract->currency }}</td>
                    <td class="money">{{ number_format((float) $contract->current_maintenance_total, 2) }}</td>
                    <td>{{ optional($contract->next_maintenance_date ?? $contract->next_billing_date)->format('Y-m-d') ?: '—' }}</td>
                    <td><span class="badge {{ $contract->status === 'active' ? 'badge-success' : 'badge-dark' }}">{{ $statuses[$contract->status] ?? $contract->status }}</span></td>
                    <td><a class="btn btn-sm btn-light" href="{{ route('contracts.show', $contract) }}">فتح</a></td>
                </tr>
                <tr id="contract-items-{{ $contract->id }}" hidden>
                    <td colspan="9" style="background:#f8fbfc">
                        <div class="form-section" style="margin:10px 0">
                            <div class="form-section-title"><div><h3>منتجات العقد</h3><div class="help">تعديل المستخدمين أو الأسعار يعيد احتساب الاستحقاقات غير المسددة فقط. الإيقاف أو إعادة التفعيل يؤثر في الاستحقاقات المستقبلية غير المحصلة.</div></div></div>
                            @forelse ($contract->items as $item)
                                <div class="form-section" style="margin-bottom:10px">
                                    <div class="form-grid">
                                        <div class="form-group col-3"><label>المنتج</label><div class="readonly-value">{{ $item->product?->name ?? '—' }}</div></div>
                                        <div class="form-group col-2"><label>الحالة</label><div>@if ($item->stopped_at)<span class="badge badge-danger">متوقف</span><div class="help">من {{ $item->stopped_at->format('Y-m-d') }}</div>@else<span class="badge badge-success">فعال</span>@endif</div></div>
                                        <form style="display:contents" method="post" action="{{ route('contracts.items.update', [$contract, $item]) }}">
                                            @csrf @method('put')
                                            <div class="form-group col-2"><label>عدد المستخدمين</label><input class="input" type="number" name="requested_users" min="0" step="1" value="{{ $item->requested_users }}" @disabled($item->stopped_at || !auth()->user()->hasPermission('contracts.update'))></div>
                                            <div class="form-group col-2"><label>سعر البند</label><input class="input" type="number" name="unit_price" min="0" step="0.01" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" @disabled($item->stopped_at || !auth()->user()->hasPermission('contracts.update'))></div>
                                            <div class="form-group col-2"><label>سعر المستخدم</label><input class="input" type="number" name="user_unit_price" min="0" step="0.01" value="{{ number_format((float) $item->user_unit_price, 2, '.', '') }}" @disabled($item->stopped_at || !auth()->user()->hasPermission('contracts.update'))></div>
                                            <div class="form-group col-1"><label>&nbsp;</label>@if (! $item->stopped_at && auth()->user()->hasPermission('contracts.update'))<button class="btn btn-primary btn-sm">حفظ</button>@endif</div>
                                        </form>
                                    </div>
                                    <div class="inline-actions">
                                        <span class="summary-chip">صافي البند: {{ number_format((float) $item->line_net, 2) }} {{ $contract->currency }}</span>
                                        @if (auth()->user()->hasPermission('contracts.update') && $contract->status === 'active')
                                            @if ($item->stopped_at)
                                                <form method="post" action="{{ route('contracts.items.reopen', [$contract, $item]) }}">@csrf<button class="btn btn-sm btn-outline">إعادة تفعيل</button></form>
                                            @else
                                                <form method="post" action="{{ route('contracts.items.stop', [$contract, $item]) }}" data-confirm="سيتم إيقاف البند من اليوم وتحديث الاستحقاقات غير المحصلة. متابعة؟">@csrf<input type="hidden" name="effective_date" value="{{ today()->format('Y-m-d') }}"><input type="hidden" name="stop_reason" value="إيقاف من قائمة العقود"><button class="btn btn-sm btn-danger">إيقاف</button></form>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">لا توجد منتجات مرتبطة بهذا العقد.</div>
                            @endforelse
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty-state">لا توجد عقود مطابقة للفلاتر.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contracts->links() }}
</div>
@endsection
