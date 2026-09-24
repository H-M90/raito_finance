@extends('layouts.app')
@section('title','استحقاقات العملاء والمنتجات')
@section('page-title','استحقاقات العملاء والمنتجات')
@section('page-subtitle','كل عقد مع المنتجات الفعالة وأقرب استحقاق قادم ونوعه وقيمته')
@section('content')
<form class="filters page-no-print">
    <div class="form-group search"><label>بحث</label><input class="input" name="q" value="{{ request('q') }}" placeholder="العميل أو العقد أو المنتج"></div>
    <x-reference-picker label="العميل" name="customer_id" wrapper-class="form-group" data-remote-url="{{ route('lookup.customers') }}">
        <option value="">كل العملاء</option>
        @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id')==$customer->id)>{{ $customer->name }}</option>@endforeach
    </x-reference-picker>
    <x-reference-picker label="المنتج" name="product_id" wrapper-class="form-group" data-remote-url="{{ route('lookup.products') }}">
        <option value="">كل المنتجات</option>
        @foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id')==$product->id)>{{ $product->name }}</option>@endforeach
    </x-reference-picker>
    <div class="form-group"><label>العملة</label><select class="select" name="currency"><option value="">كل العملات</option>@foreach($currencies as $v=>$l)<option value="{{ $v }}" @selected(request('currency')===$v)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn btn-secondary">تطبيق</button>
    <a class="btn btn-light" href="{{ route('reports.customer-products-due') }}">مسح</a>
    <button class="btn btn-outline" type="button" data-print-page>طباعة</button>
</form>

@if (auth()->user()->hasPermission('contracts.update'))
    <form class="filters page-no-print" method="post" action="{{ route('reports.customer-products-due.generate') }}" style="margin-top:-6px">
        @csrf
        <div class="form-group">
            <label for="until_date">احتساب وتوليد حتى</label>
            <input id="until_date" class="input" type="date" name="until_date" min="{{ today()->format('Y-m-d') }}" value="{{ today()->addDays((int) config('finance.receivable_generation_lead_days', 15))->format('Y-m-d') }}">
        </div>
        <button class="btn btn-primary">توليد الاستحقاقات</button>
        <span class="help">يولّد الاستحقاقات الدورية المستحقة لكل العقود النشطة حتى التاريخ المحدد، دون تكرار.</span>
    </form>
@endif

<div class="alert alert-success">أقرب استحقاق يُحتسب من الاستحقاقات المتولدة بالفعل ومن مواعيد الاشتراك/الصيانة المجدولة مستقبلًا. استخدم زر التوليد لإنشاء الاستحقاقات المحسوبة حتى التاريخ الذي تختاره.</div>

<div class="card">
    <div class="card-header"><div><h2>العملاء والعقود</h2><p>{{ $contracts->total() }} عقد فعال. كل عقد يظهر في صف مستقل حتى تكون قيمة وتاريخ الاستحقاق واضحة.</p></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>العميل</th><th>العقد</th><th>المنتجات الفعالة</th><th>دورة العقد</th><th>الاستحقاق القادم</th><th>النوع</th><th>القيمة</th><th>حالة التوليد</th><th>المدة</th></tr></thead>
        <tbody>
        @forelse($contracts as $contract)
            <tr>
                <td><strong>{{ $contract->customer->name }}</strong>@if($contract->customer->code)<small class="muted">{{ $contract->customer->code }}</small>@endif</td>
                <td><a href="{{ route('contracts.show',$contract) }}">{{ $contract->number }}</a><small class="muted">بداية {{ $contract->service_start_date->format('Y-m-d') }}</small></td>
                <td>
                    @forelse($contract->report_products as $product)<span class="summary-chip">{{ $product->name }}</span>@empty<span class="muted">لا توجد منتجات فعالة</span>@endforelse
                </td>
                <td>{{ \App\Support\FinanceOptions::billingCycles()[$contract->billing_cycle] ?? $contract->billing_cycle }}</td>
                @if($contract->next_due_date_report)
                    @php($daysToDue=(int)today()->diffInDays($contract->next_due_date_report))
                    <td><strong>{{ $contract->next_due_date_report->format('Y-m-d') }}</strong><small class="muted">{{ $daysToDue===0 ? 'اليوم' : 'بعد '.$daysToDue.' يوم' }}</small></td>
                    <td>@foreach($contract->next_due_types_report as $label)<span class="badge badge-info">{{ $label }}</span>@endforeach</td>
                    <td class="money"><strong>{{ number_format((float)$contract->next_due_amount_report,2) }}</strong> {{ $contract->currency }}</td>
                    <td><span class="badge {{ $contract->next_due_generated_report ? 'badge-success' : 'badge-warning' }}">{{ $contract->next_due_generated_report ? 'تم التوليد' : 'غير مولد' }}</span>@if(! $contract->next_due_generated_report)<small class="muted">محسوب ومجدول</small>@endif</td>
                    <td><span class="badge {{ $daysToDue<=30?'badge-warning':'badge-success' }}">{{ $daysToDue===0?'اليوم':$daysToDue.' يوم' }}</span></td>
                @else
                    <td>—</td><td><span class="muted">لا يوجد استحقاق قادم</span></td><td>—</td><td>—</td><td>—</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="9" class="empty-state">لا توجد عقود مطابقة للفلاتر.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $contracts->links() }}
</div>
@endsection
