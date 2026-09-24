@extends('layouts.app')
@section('title','تقرير الربحية')
@section('page-title','الربحية الفعلية')
@section('page-subtitle','صافي التحصيلات بدون الضريبة ناقص المشتريات والمصروفات والعمولات المدفوعة')
@section('content')
<form class="filters page-no-print">
    <div class="form-group">
        <label>العميل</label>
        <select class="select" name="customer_id" data-remote-url="{{ route('lookup.customers') }}">
            <option value="">كل العملاء</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected(request('customer_id')==$customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label>العملة</label>
        <select class="select" name="currency">
            @foreach($currencies as $value=>$label)
                <option value="{{ $value }}" @selected($currency===$value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group"><label>نوع التقرير</label><select class="select" name="mode"><option value="cash" @selected($mode === 'cash')>الربحية النقدية خلال الفترة</option><option value="lifetime" @selected($mode === 'lifetime')>ربحية العميل/العقد طوال التعامل</option></select></div>
    <div class="form-group"><label>من</label><input class="input" type="date" name="from" value="{{ $from }}"></div>
    <div class="form-group"><label>إلى</label><input class="input" type="date" name="to" value="{{ $to }}"></div>
    <button class="btn btn-light">تطبيق</button>
    <button class="btn btn-outline" type="button" data-print-page>طباعة</button>
</form>

<div class="alert alert-success">
    @if($mode === 'lifetime') يعرض هذا الوضع ربحية العقود طوال التعامل، بما فيها العقود الملغاة، مع تكاليفها المرتبطة بها. لا يشمل المصروفات العامة غير الموزعة على عقد.
    @else الإيراد هو الجزء الصافي من سندات القبض المؤكدة بعد استبعاد ضريبة القيمة المضافة. يتم خصم المشتريات والمصروفات والعمولات المدفوعة فعليًا خلال الفترة. الحركات العامة تظهر في سطر «عام / بدون عقد». @endif
    جميع الأرقام بعملة <strong>{{ $currency }}</strong>.
</div>

<div class="grid grid-4">
    <div class="stat-card"><div class="label">{{ $mode === 'lifetime' ? 'الإيراد المتحقق بدون ضريبة' : 'صافي التحصيل بدون ضريبة' }}</div><div class="value">{{ number_format($summary['revenue'],2) }}</div></div>
    <div class="stat-card"><div class="label">المشتريات</div><div class="value">{{ number_format($summary['purchase'],2) }}</div></div>
    <div class="stat-card"><div class="label">المصروفات + العمولات</div><div class="value">{{ number_format($summary['expenses'] + $summary['commissions'],2) }}</div><div class="muted">عمولات {{ number_format($summary['commissions'],2) }}</div></div>
    <div class="stat-card"><div class="label">صافي الربح</div><div class="value {{ $summary['profit']>=0?'profit-positive':'profit-negative' }}">{{ number_format($summary['profit'],2) }}</div><div class="muted">الهامش {{ number_format($summary['margin'],2) }}%</div></div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-header"><div><h2>تفاصيل الربحية</h2><p>توزيع الحركات المرتبطة بالعقود مع تجميع الحركات العامة بصورة مستقلة.</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>العقد / البيان</th><th>العميل</th><th>{{ $mode === 'lifetime' ? 'الإيراد المتحقق' : 'صافي التحصيل' }}</th><th>المشتريات</th><th>المصروفات</th><th>العمولات</th><th>صافي الربح</th><th>الهامش</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>@if($row['url'])<a href="{{ $row['url'] }}">{{ $row['label'] }}</a>@else<strong>{{ $row['label'] }}</strong>@endif</td>
                    <td>{{ $row['customer'] }}</td>
                    <td class="money">{{ number_format($row['revenue'],2) }}</td>
                    <td class="money">{{ number_format($row['purchase'],2) }}</td>
                    <td class="money">{{ number_format($row['expenses'],2) }}</td>
                    <td class="money">{{ number_format($row['commissions'],2) }}</td>
                    <td class="money {{ $row['profit']>=0?'profit-positive':'profit-negative' }}">{{ number_format($row['profit'],2) }}</td>
                    <td>{{ number_format($row['margin'],2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-state">لا توجد حركات مالية مطابقة للفترة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
