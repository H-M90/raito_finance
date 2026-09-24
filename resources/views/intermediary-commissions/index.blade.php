@extends('layouts.app')
@section('title','عمولات الوسطاء')
@section('page-title','عمولات الوسطاء')
@section('page-subtitle','متابعة العمولة المستحقة من التحصيل الفعلي وصرفها بدون تجاوز قيمة العقد')
@section('content')
@if($summaryByCurrency->isNotEmpty())
<div class="commission-summary-grid">
    @foreach($summaryByCurrency as $code=>$s)
    <div class="commission-summary-card">
        <div class="commission-summary-head"><span>العمولات · {{ $code }}</span><strong>{{ number_format((float)$s->remaining_amount,2) }}</strong></div>
        <div class="commission-summary-metrics"><span>إجمالي <b>{{ number_format((float)$s->total_amount,2) }}</b></span><span>مصروف <b>{{ number_format((float)$s->paid_amount,2) }}</b></span><span>متبقي <b>{{ number_format((float)$s->remaining_amount,2) }}</b></span></div>
        @php($ratio=(float)$s->total_amount>0?min(100,round((float)$s->paid_amount/(float)$s->total_amount*100)):0)
        <progress class="mini-progress-native" value="{{ $ratio }}" max="100" aria-label="نسبة صرف العمولة {{ $ratio }}%"></progress>
    </div>
    @endforeach
</div>
@endif

<div class="card commission-filter-card">
    <form class="filter-grid">
        <div class="form-group"><label>الوسيط</label><select class="select" name="intermediary_id" data-smart-select="1" data-smart-placeholder="ابحث عن الوسيط"><option value="">كل الوسطاء</option>@foreach($intermediaries as $i)<option value="{{ $i->id }}" @selected(request('intermediary_id')==$i->id)>{{ $i->name }}</option>@endforeach</select></div>
        <div class="form-group"><label>الحالة</label><select class="select" name="status"><option value="">كل الحالات</option><option value="outstanding" @selected(request('status')==='outstanding')>مستحقة</option><option value="partial" @selected(request('status')==='partial')>مصروفة جزئيًا</option><option value="paid" @selected(request('status')==='paid')>مصروفة بالكامل</option><option value="cancelled" @selected(request('status')==='cancelled')>ملغاة</option></select></div>
        <div class="form-group"><label>العملة</label><select class="select" name="currency"><option value="">كل العملات</option>@foreach($currencies as $code=>$label)<option value="{{ $code }}" @selected(request('currency')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>من</label><input class="input" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="form-group"><label>إلى</label><input class="input" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-actions"><button class="btn btn-primary">تطبيق</button>@if(request()->hasAny(['intermediary_id','status','currency','from','to']))<a class="btn btn-light" href="{{ route('intermediary-commissions.index') }}">مسح</a>@endif</div>
    </form>
</div>

<div class="commission-list">
@forelse($commissions as $c)
    @php($paidRatio=(float)$c->amount>0?min(100,round((float)$c->paid_amount/(float)$c->amount*100)):0)
    <div class="card commission-row-card">
        <div class="commission-row-main">
            <div class="commission-identity"><span class="entity-avatar">{{ mb_substr($c->intermediary->name,0,1) }}</span><div><strong>{{ $c->intermediary->name }}</strong><small>{{ $c->number }} · عقد {{ $c->contract->number }} · سند {{ $c->collection?->number?:'—' }}</small></div></div>
            <div class="commission-basis"><span>أساس العمولة</span><strong>{{ number_format((float)$c->base_amount,2) }} {{ $c->currency }}</strong><small>@if($c->commission_type==='percentage'){{ number_format((float)$c->commission_value,2) }}% · عمولة العقد الكلية {{ number_format((float)$c->contract->commission_total,2) }} من صافي {{ number_format((float)$c->contract->net_total,2) }}@elseعمولة العقد الكلية {{ number_format((float)$c->contract->commission_total,2) }} موزعة مع التحصيل@endif</small></div>
            <div class="commission-amount"><span>المستحق</span><strong>{{ number_format((float)$c->amount,2) }} {{ $c->currency }}</strong><small>متبقي {{ number_format((float)$c->remaining_amount,2) }}</small></div>
            <div class="commission-status"><span class="badge {{ $c->status==='paid'?'badge-success':($c->status==='partial'?'badge-warning':($c->status==='cancelled'?'badge-dark':'badge-info')) }}">{{ ['outstanding'=>'مستحقة','partial'=>'مصروفة جزئيًا','paid'=>'مصروفة','cancelled'=>'ملغاة'][$c->status]??$c->status }}</span><small>استحقاق {{ $c->due_date->format('Y-m-d') }}</small></div>
        </div>
        <progress class="commission-progress-native" value="{{ $paidRatio }}" max="100" aria-label="نسبة صرف العمولة {{ $paidRatio }}%"></progress>
        <div class="commission-row-foot">
            <div>@if($c->payments->isNotEmpty())<span class="muted">آخر صرف:</span> <strong>{{ $c->payments->first()->number }}</strong> · {{ $c->payments->first()->payment_date->format('Y-m-d') }} · {{ number_format((float)$c->payments->first()->amount,2) }}@else<span class="muted">لم يتم صرف أي مبلغ بعد.</span>@endif</div>
            @if((float)$c->remaining_amount>0&&$c->status!=='cancelled'&&auth()->user()->hasPermission('intermediaries.pay-commissions'))
            <details class="commission-pay-panel"><summary class="btn btn-sm btn-primary">صرف من العمولة</summary><form method="post" action="{{ route('intermediary-commissions.pay',$c) }}" class="commission-pay-form">@csrf<div class="form-group"><label>التاريخ</label><input class="input" type="date" name="payment_date" value="{{ today()->format('Y-m-d') }}" required></div><div class="form-group"><label>القيمة</label><input class="input" type="number" step=".01" min=".01" max="{{ $c->remaining_amount }}" name="amount" value="{{ $c->remaining_amount }}" required></div><div class="form-group"><label>الطريقة</label><select class="select" name="payment_method"><option value="">غير محدد</option>@foreach($methods as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div><div class="form-group"><label>المرجع</label><input class="input" name="reference_no"></div><div class="form-group commission-note"><label>ملاحظات</label><input class="input" name="notes"></div><button class="btn btn-primary">تسجيل سند الصرف</button></form></details>
            @endif
        </div>
    </div>
@empty<div class="card empty-state">لا توجد عمولات مطابقة للفلاتر.</div>@endforelse
</div>
{{ $commissions->links() }}
@endsection
