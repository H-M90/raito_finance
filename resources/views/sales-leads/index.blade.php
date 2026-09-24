@extends('layouts.app')
@section('title','رحلة المبيعات')
@section('page-title','العملاء المحتملون')
@section('page-subtitle','إدارة رحلة العميل من أول تواصل حتى التحويل إلى عميل')
@section('content')
@php
    $stageLabels=\App\Models\SalesLead::STAGES;
    $ratingLabels=\App\Models\SalesLead::RATINGS;
    $qualificationLabels=\App\Models\SalesLead::QUALIFICATIONS;
    $sourceLabels=\App\Models\SalesLead::SOURCES;
    $sortUrl = fn(string $key) => route('sales-leads.index', array_merge(request()->except('page'), [
        'sort'=>$key,
        'direction'=>(request('sort')===$key && request('direction')==='asc')?'desc':'asc',
    ]));
    $sortMark = fn(string $key) => request('sort')===$key ? (request('direction')==='asc'?'↑':'↓') : '';
@endphp
<div class="sales-crm-page">
    <div class="sales-page-head">
        <div>
            <span class="sales-eyebrow">المبيعات / رحلة ما قبل التعاقد</span>
            <h2>العملاء المحتملون</h2>
            <p>متابعة المراحل، التأهيل، التواصل، والمتابعة القادمة من مكان واحد.</p>
        </div>
        <div class="sales-head-actions">
            @if(auth()->user()->hasPermission('sales-leads.tasks'))<a class="btn btn-light" href="{{ route('tasks.index',['team'=>'sales']) }}">المهام والمتابعات</a>@endif
            @if(auth()->user()->hasPermission('sales-leads.create'))<a class="btn btn-primary" href="{{ route('sales-leads.create') }}">+ إضافة عميل محتمل</a>@endif
        </div>
    </div>

    <div class="sales-kpis">
        @foreach([
            ['value'=>$metrics['total'],'label'=>'إجمالي العملاء المحتملين','tone'=>'teal'],
            ['value'=>$metrics['contacted'],'label'=>'تم التواصل','tone'=>'blue'],
            ['value'=>$metrics['qualified'],'label'=>'مؤهل','tone'=>'green'],
            ['value'=>$metrics['advanced'],'label'=>'في مراحل متقدمة','tone'=>'purple'],
            ['value'=>$metrics['won'],'label'=>'تم البيع','tone'=>'success'],
            ['value'=>$metrics['lost'],'label'=>'غير ناجح','tone'=>'danger'],
        ] as $kpi)
            <div class="sales-kpi sales-tone-{{ $kpi['tone'] }}"><i></i><div><strong>{{ number_format($kpi['value']) }}</strong><span>{{ $kpi['label'] }}</span></div></div>
        @endforeach
    </div>

    <section class="sales-pipeline" aria-label="مراحل رحلة المبيعات">
        <div class="sales-pipeline-head"><div><span>رحلة ما قبل التعاقد</span><strong>اضغط على أي مرحلة لعرض العملاء الموجودين بها</strong></div></div>
        <div class="sales-pipeline-track">
            @foreach($stageLabels as $code=>$label)
                @php $stageQuery=array_filter(array_merge(request()->except(['page','stage']),['stage'=>$code]),fn($v)=>$v!==null&&$v!==''); @endphp
                <a class="sales-pipeline-step {{ request('stage')===$code?'active':'' }} stage-{{ $code }}" href="{{ route('sales-leads.index',$stageQuery) }}">
                    <span>{{ $label }}</span><strong>{{ number_format((int)($stageCounts[$code]??0)) }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <div class="sales-toolbar card">
        <form method="get" class="sales-filter-form">
            <div class="sales-search"><span></span><input name="q" value="{{ request('q') }}" placeholder="ابحث بالاسم، الشركة، الجوال، البريد..."></div>
            <select class="select" name="stage"><option value="">كل المراحل</option>@foreach($stageLabels as $code=>$label)<option value="{{ $code }}" @selected(request('stage')===$code)>{{ $label }}</option>@endforeach</select>
            <select class="select" name="owner_id"><option value="">كل المسؤولين</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string)request('owner_id')===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select>
            <select class="select" name="source"><option value="">كل المصادر</option>@foreach($sourceLabels as $code=>$label)<option value="{{ $code }}" @selected(request('source')===$code)>{{ $label }}</option>@endforeach</select>
            <input type="hidden" name="quick" value="{{ request('quick') }}">
            <input type="hidden" name="per_page" value="{{ request('per_page',20) }}">
            <button class="btn btn-secondary">بحث</button>
            @if(request()->hasAny(['q','stage','owner_id','source','quick']))<a class="btn btn-light" href="{{ route('sales-leads.index') }}">إلغاء الفلاتر</a>@endif
        </form>
        <div class="sales-quick-filters">
            @foreach([
                'today'=>['متابعة اليوم',$quickCounts['today']??0],
                'tomorrow'=>['متابعة غدًا',null],
                'week'=>['هذا الأسبوع',null],
                'overdue'=>['متأخر',$quickCounts['overdue']??0],
                'nofollowup'=>['بدون متابعة',null],
                'unanswered'=>['لم يتم الرد',$quickCounts['unanswered']??0],
                'hot'=>['عملاء ساخنة',$quickCounts['hot']??0],
                'qualified'=>['مؤهل',$quickCounts['qualified']??0],
                'meetingToday'=>['اجتماعات اليوم',null],
                'noactivity'=>['بدون تواصل +7 أيام',$quickCounts['noactivity']??0],
                'won'=>['تم البيع',null],
            ] as $code=>$meta)
                @php $query=array_filter(array_merge(request()->except('page'),['quick'=>request('quick')===$code?null:$code]),fn($v)=>$v!==null&&$v!==''); @endphp
                <a href="{{ route('sales-leads.index',$query) }}" class="sales-filter-chip {{ request('quick')===$code?'active':'' }}">{{ $meta[0] }} @if($meta[1]!==null)<b>{{ $meta[1] }}</b>@endif</a>
            @endforeach
        </div>
    </div>

    <form method="post" action="{{ route('sales-leads.bulk-update') }}" class="card sales-grid-card" id="salesBulkForm">
        @csrf
        <div class="sales-grid-head">
            <div class="sales-bulk-actions">
                <span id="salesSelectedCount">لم يتم تحديد عملاء</span>
                @if(auth()->user()->hasPermission('sales-leads.update'))
                    <select class="select select-compact" name="action" id="salesBulkAction"><option value="stage">تغيير المرحلة</option>@if(auth()->user()->hasPermission('sales-leads.assign'))<option value="owner">تعيين مسؤول</option>@endif</select>
                    <select class="select select-compact" name="stage" id="salesBulkStage">@foreach($stageLabels as $code=>$label) @if($code==='won') @continue @endif <option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                    @if(auth()->user()->hasPermission('sales-leads.assign'))<select class="select select-compact" name="owner_id" id="salesBulkOwner" hidden>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select>@endif
                    <button class="btn btn-sm btn-light" type="submit">تطبيق</button>
                @endif
            </div>
            <span><strong>{{ number_format($leads->total()) }}</strong> نتيجة</span>
        </div>
        <div class="table-wrap sales-table-wrap"><table class="sales-leads-table">
            <thead><tr>
                <th class="sales-check-col"><input type="checkbox" data-sales-select-all aria-label="تحديد الكل"></th>
                <th><a class="sales-sort" href="{{ $sortUrl('company_name') }}">الشركة {{ $sortMark('company_name') }}</a></th><th>القطاع</th><th><a class="sales-sort" href="{{ $sortUrl('stage') }}">المرحلة {{ $sortMark('stage') }}</a></th><th><a class="sales-sort" href="{{ $sortUrl('rating') }}">أولوية العميل {{ $sortMark('rating') }}</a></th><th>تم الرد؟</th><th>التأهيل</th><th>النشاط</th><th><a class="sales-sort" href="{{ $sortUrl('last_activity_at') }}">آخر تواصل {{ $sortMark('last_activity_at') }}</a></th><th><a class="sales-sort" href="{{ $sortUrl('next_follow_up_at') }}">المتابعة القادمة {{ $sortMark('next_follow_up_at') }}</a></th><th>المسؤول</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($leads as $lead)
                @php
                    $overdue=$lead->next_follow_up_at && $lead->next_follow_up_at->isPast() && !in_array($lead->stage,['won','lost','disqualified'],true);
                    $days=$lead->last_activity_at ? $lead->last_activity_at->startOfDay()->diffInDays(today()) : null;
                @endphp
                <tr>
                    <td><input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" data-sales-select></td>
                    <td><a class="sales-company" href="{{ route('sales-leads.show',$lead) }}"><strong>{{ $lead->company_name }}</strong><small>{{ $lead->contact_name ?: 'بدون مسؤول اتصال' }} @if($lead->phone) · {{ $lead->phone }} @endif</small></a></td>
                    <td>{{ $lead->sector ?: '—' }}</td>
                    <td><span class="sales-stage stage-{{ $lead->stage }}">{{ $lead->stageLabel() }}</span></td>
                    <td><span class="sales-score score-{{ $lead->rating }}">{{ $lead->ratingLabel() }}</span></td>
                    <td><span class="sales-response {{ $lead->responded?'yes':'no' }}">{{ $lead->responded?'نعم':'لا' }}</span></td>
                    <td><span class="sales-qualification q-{{ $lead->qualification }}">{{ $lead->qualificationLabel() }}</span></td>
                    <td><span class="sales-activity-count">{{ $lead->activities_count }}</span></td>
                    <td>@if($lead->last_activity_at)<span class="sales-last-activity {{ $days!==null&&$days>7?'stale':($days!==null&&$days>3?'warm':'fresh') }}">{{ $days===0?'اليوم':($days===1?'أمس':$days.' أيام') }}</span>@else<span class="sales-last-activity stale">لا يوجد</span>@endif</td>
                    <td class="{{ $overdue?'text-danger':'' }}">@if($lead->next_follow_up_at)<strong>{{ $lead->next_follow_up_at->format('Y-m-d') }}</strong><small class="muted">{{ $lead->next_follow_up_at->format('H:i') }} · {{ \App\Models\SalesLead::TASK_TYPES[$lead->next_follow_up_type]??'متابعة' }}</small>@else<span class="muted">بدون متابعة</span>@endif</td>
                    <td>{{ $lead->owner?->name ?: 'غير معين' }}</td>
                    <td><a class="sales-profile-icon" href="{{ route('sales-leads.show',$lead) }}" title="فتح ملف العميل المحتمل" aria-label="فتح ملف العميل المحتمل"><span></span></a></td>
                </tr>
            @empty<tr><td colspan="12" class="empty-state">لا توجد عملاء محتملون مطابقة للفلاتر الحالية.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="sales-pager-row">
            <div>عرض
                @foreach([20,50,100] as $n)<a class="sales-page-size {{ (int)request('per_page',20)===$n?'active':'' }}" href="{{ route('sales-leads.index',array_merge(request()->except('page'),['per_page'=>$n])) }}">{{ $n }}</a>@endforeach
            </div>
            <div>{{ $leads->links() }}</div>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const all=document.querySelector('[data-sales-select-all]'), boxes=[...document.querySelectorAll('[data-sales-select]')], count=document.getElementById('salesSelectedCount');
  const refresh=()=>{const n=boxes.filter(x=>x.checked).length;count.textContent=n?`تم تحديد ${n} عميل محتمل`:'لم يتم تحديد عملاء';if(all)all.checked=n&&n===boxes.length};
  all?.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);refresh()}); boxes.forEach(x=>x.addEventListener('change',refresh));
  const action=document.getElementById('salesBulkAction'),stage=document.getElementById('salesBulkStage'),owner=document.getElementById('salesBulkOwner');
  action?.addEventListener('change',()=>{if(stage)stage.hidden=action.value!=='stage';if(owner)owner.hidden=action.value!=='owner'});
  document.getElementById('salesBulkForm')?.addEventListener('submit',e=>{if(!boxes.some(x=>x.checked)){e.preventDefault();alert('حدد عميلًا محتملًا واحدًا على الأقل.')}});
});
</script>
@endpush
