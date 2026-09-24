@extends('layouts.app')
@section('title','عروض المبيعات')
@section('page-title','عروض المبيعات')
@section('page-subtitle','من العرض الأول حتى التحويل إلى عقد')
@section('content')
<div class="card"><div class="card-header"><div><h2>قائمة العروض</h2><p>{{ $quotations->total() }} عرض</p></div><div class="inline-actions">@if(auth()->user()->hasPermission('quotations.reports'))<a class="btn btn-light" href="{{ route('quotations.report') }}">تقرير العروض</a>@endif @if(auth()->user()->hasPermission('quotations.create'))<a class="btn btn-primary" href="{{ route('quotations.create') }}">+ عرض جديد</a>@endif</div></div>
<form class="filters" method="get"><div class="form-group search"><label>البحث</label><input class="input" name="q" value="{{ request('q') }}" placeholder="رقم العرض أو اسم العميل"></div><div class="form-group"><label>الحالة</label><select class="select" name="status"><option value="">الكل</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><button class="btn btn-secondary">بحث</button></form>
<div class="table-wrap"><table><thead><tr><th>العرض</th><th>العميل</th><th>التاريخ</th><th>النشاط</th><th>الإجمالي</th><th>الصيانة المتوقعة</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($quotations as $q)<tr><td><strong>{{ $q->number }}</strong><div class="muted">V{{ $q->version_number }}</div></td><td>{{ $q->customer->name }}</td><td>{{ $q->quotation_date->format('Y-m-d') }}</td><td>{{ \App\Support\FinanceOptions::activityTypes()[$q->activity_type] ?? $q->activity_type }}</td><td class="money">{{ number_format((float)$q->grand_total,2) }} {{ $q->currency }}</td><td class="money">{{ number_format((float)$q->maintenance_total,2) }}</td><td><span class="badge {{ in_array($q->status,['accepted','converted'])?'badge-success':($q->status==='rejected'?'badge-danger':'badge-info') }}">{{ $statuses[$q->status] ?? $q->status }}</span></td><td><a class="btn btn-sm btn-light" href="{{ route('quotations.show',$q) }}">فتح</a></td></tr>
@empty<tr><td colspan="8" class="empty-state">لا توجد عروض.</td></tr>@endforelse
</tbody></table></div>{{ $quotations->links() }}</div>
@endsection
