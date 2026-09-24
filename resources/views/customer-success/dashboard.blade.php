@extends('layouts.app')
@section('title','قائمة المتابعة اليوم')
@section('page-title','قائمة المتابعة اليوم')
@section('page-subtitle','مين محتاج متابعة، وليه، وإمتى')
@section('content')
@php $attentionLabels=\App\Support\CustomerSuccessOptions::attentionLevels(); @endphp

<div class="grid grid-4">
    <div class="stat-card"><div class="label">متابعات متأخرة</div><div class="value text-danger">{{ $stats['overdue_tasks'] }}</div><div class="hint">ابدأ بها أولًا</div></div>
    <div class="stat-card"><div class="label">حالات عاجلة</div><div class="value text-danger">{{ $stats['critical'] }}</div><div class="hint">تحتاج تدخل واضح</div></div>
    <div class="stat-card"><div class="label">تنبيهات مفتوحة</div><div class="value">{{ $stats['open_flags'] }}</div><div class="hint">أسباب مؤقتة تحتاج معالجة</div></div>
    <div class="stat-card"><div class="label">فرص مع العملاء</div><div class="value">{{ $stats['active_signals'] }}</div><div class="hint">تجديد أو توسع أو بيع إضافي</div></div>
</div>

<div class="card section-spaced">
    <div class="card-header"><div><h2>المطلوب تنفيذه</h2><p>مرتبة حسب أقرب موعد.</p></div><a class="btn btn-light" href="{{ route('customers.index') }}">كل العملاء</a></div>
    <div class="table-wrap"><table><thead><tr><th>العميل</th><th>المطلوب</th><th>المسؤول</th><th>الموعد</th><th></th></tr></thead><tbody>
    @forelse($tasks as $task)
        <tr>
            <td><strong>{{ $task->customer->name }}</strong><div class="muted">{{ $task->customer->code }}</div></td>
            <td><strong>{{ str_replace(\App\Services\CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX,'',$task->title) }}</strong>@if($task->description)<div class="muted">{{ \Illuminate\Support\Str::limit($task->description,90) }}</div>@endif</td>
            <td>{{ $task->assignee?->name ?: 'غير محدد' }}</td>
            <td class="{{ $task->due_at && $task->due_at->isPast()?'text-danger':'' }}"><strong>{{ $task->due_at?->format('Y-m-d H:i') ?: 'بدون موعد' }}</strong>@if($task->due_at && $task->due_at->isPast())<div class="muted">متأخرة</div>@endif</td>
            <td><a class="icon-action" href="{{ route('customers.show',$task->customer) }}#customer-success" title="فتح ملف العميل" aria-label="فتح ملف العميل"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg></a></td>
        </tr>
    @empty<tr><td colspan="5" class="empty-state">لا توجد متابعات مفتوحة الآن.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="card section-spaced">
    <div class="card-header"><div><h2>عملاء يحتاجون انتباه</h2><p>الحالة توضح وضع العلاقة، والتنبيه يوضح سبب التدخل الحالي.</p></div></div>
    <form method="get" class="filters">
        <div class="form-group search"><label>البحث</label><input class="input" name="q" value="{{ $q }}" placeholder="اسم العميل أو الكود"></div>
        <div class="form-group"><label>الأولوية</label><select class="select" name="attention"><option value="">العاجل والمستحق فقط</option>@foreach($attentionLabels as $code=>$label)<option value="{{ $code }}" @selected($attention===$code)>{{ $label }}</option>@endforeach</select></div>
        <button class="btn btn-secondary">تطبيق</button><a class="btn btn-light" href="{{ route('customer-success.dashboard') }}">مسح</a>
    </form>
    <div class="table-wrap"><table><thead><tr><th>العميل</th><th>حالة العميل</th><th>سبب المتابعة</th><th>المراجعة القادمة</th><th></th></tr></thead><tbody>
    @forelse($profiles as $profile)
        @php
            $primaryFlag=$profile->customer->attentionFlags->sortByDesc(fn($flag)=>['critical'=>4,'high'=>3,'medium'=>2,'normal'=>1][$flag->severity]??0)->first();
        @endphp
        <tr>
            <td><strong>{{ $profile->customer->name }}</strong><div class="muted">{{ $profile->customer->code }}</div></td>
            <td><span class="badge {{ in_array($profile->attention_level,['critical','high'],true)?'badge-warning':'badge-info' }}">{{ $profile->lifecycleStatus?->name ?: '—' }}</span></td>
            <td><strong>{{ $primaryFlag?->type?->name ?: 'لا يوجد تنبيه حالي' }}</strong>@if($primaryFlag?->notes)<div class="muted">{{ \Illuminate\Support\Str::limit($primaryFlag->notes,80) }}</div>@endif</td>
            <td class="{{ $profile->next_review_at && $profile->next_review_at->isPast()?'text-danger':'' }}">{{ $profile->next_review_at?->format('Y-m-d H:i') ?: '—' }}</td>
            <td><a class="icon-action" href="{{ route('customers.show',$profile->customer) }}#customer-success" title="فتح ملف العميل" aria-label="فتح ملف العميل"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg></a></td>
        </tr>
    @empty<tr><td colspan="5" class="empty-state">لا توجد حالات مطابقة.</td></tr>@endforelse
    </tbody></table></div>
    <div style="margin-top:14px">{{ $profiles->links() }}</div>
</div>
@endsection
