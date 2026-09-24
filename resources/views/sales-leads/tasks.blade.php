@extends('layouts.app')
@section('title','مهام المبيعات')
@section('page-title','المهام والمتابعات')
@section('page-subtitle','قائمة العمل اليومية لفريق المبيعات قبل التعاقد')
@section('content')
<div class="sales-crm-page">
    <div class="sales-page-head compact"><div><span class="sales-eyebrow">المبيعات / المتابعات</span><h2>المهام والمتابعات</h2><p>ما المطلوب اليوم، وما الذي تأخر، وما القادم.</p></div><a class="btn btn-light" href="{{ route('sales-leads.index') }}">العملاء المحتملون</a></div>
    <div class="sales-task-kpis">@foreach([['open','مهام مفتوحة'],['overdue','متأخرة'],['today','اليوم'],['meetings','اجتماعات']] as [$key,$label])<div><strong>{{ number_format($taskMetrics[$key]) }}</strong><span>{{ $label }}</span></div>@endforeach</div>
    <div class="card sales-task-toolbar">
        <div class="sales-view-tabs">@foreach(['today'=>'اليوم','overdue'=>'متأخرة','upcoming'=>'قادمة','all'=>'الكل'] as $key=>$label)<a class="{{ $view===$key?'active':'' }}" href="{{ route('sales-leads.tasks',array_merge(request()->except('page'),['view'=>$key])) }}">{{ $label }}</a>@endforeach</div>
        <form method="get" class="sales-task-filters"><input type="hidden" name="view" value="{{ $view }}"><select class="select" name="type"><option value="">كل الأنواع</option>@foreach(\App\Models\SalesLead::TASK_TYPES as $code=>$label)<option value="{{ $code }}" @selected(request('type')===$code)>{{ $label }}</option>@endforeach</select><select class="select" name="owner_id"><option value="">كل المسؤولين</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string)request('owner_id')===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select><input class="input" name="q" value="{{ request('q') }}" placeholder="ابحث في المهمة أو العميل"><button class="btn btn-secondary">بحث</button></form>
    </div>
    <div class="card sales-task-board">
        @forelse($tasks as $task)<div class="sales-task-board-row {{ $task->status==='open'&&$task->due_at->isPast()?'overdue':'' }}"><div class="sales-task-checkmark">@if($task->status==='completed')<x-ui-icon name="check" />@endif</div><span class="sales-task-type">{{ \App\Models\SalesLead::TASK_TYPES[$task->type]??'مهمة' }}</span><div class="sales-task-desc"><a href="{{ route('sales-leads.show',$task->lead) }}"><strong>{{ $task->title }}</strong></a><small>{{ $task->lead->company_name }} · {{ $task->lead->contact_name }}</small></div><div class="sales-task-owner">{{ $task->assignee?->name ?: $task->lead->owner?->name ?: 'غير معين' }}</div><div class="sales-task-date {{ $task->status==='open'&&$task->due_at->isPast()?'text-danger':'' }}">{{ $task->due_at->format('Y-m-d') }}<small>{{ $task->due_at->format('H:i') }}</small></div><span class="sales-task-priority p-{{ $task->priority }}">{{ \App\Models\SalesLead::PRIORITIES[$task->priority]??$task->priority }}</span>@if($task->status==='open')<form method="post" action="{{ route('sales-leads.tasks.complete',[$task->lead,$task]) }}">@csrf<button class="btn btn-sm btn-light">تمت</button></form>@else<span class="badge badge-success">مكتملة</span>@endif</div>@empty<div class="empty-state">لا توجد مهام مطابقة.</div>@endforelse
        <div class="sales-task-pagination">{{ $tasks->links() }}</div>
    </div>
</div>
@endsection
