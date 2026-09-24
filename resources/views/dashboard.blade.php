@extends('layouts.app')
@section('title','لوحة القيادة - Raito Finance')
@section('page-title','لوحة القيادة المالية')
@section('page-subtitle','ملخص سريع لما يحتاج المتابعة اليوم')
@section('content')
<div class="dashboard-page">
<div class="grid grid-4">
    <div class="stat-card"><div class="label">العملاء النشطون</div><div class="value">{{ number_format($activeCustomers) }}</div><div class="hint">عملاء يمكن التعامل معهم حاليًا</div></div>
    <div class="stat-card"><div class="label">العقود النشطة</div><div class="value">{{ number_format($activeContracts) }}</div><div class="hint">تشمل الاشتراكات وعقود المرة الواحدة</div></div>
    <div class="stat-card"><div class="label">عروض قيد المتابعة</div><div class="value">{{ number_format($openQuotations) }}</div><div class="hint"><a href="{{ route('quotations.index',['status'=>'negotiation']) }}">فتح عروض المبيعات</a></div></div>
    <div class="stat-card"><div class="label">استحقاقات متأخرة</div><div class="value text-danger">{{ number_format($overdueCount) }}</div><div class="hint"><a href="{{ route('receivables.index',['status'=>'overdue']) }}">راجع المتأخرات الآن</a></div></div>
</div>

@if(auth()->user()->hasPermission('sales-leads.view'))
<div class="card section-spaced dashboard-section sales-home-followups">
    <div class="card-header">
        <div>
            <h2>متابعات المبيعات اليوم</h2>
            <p>{{ number_format($salesLeadOpenCount ?? 0) }} عميل محتمل ما زال في رحلة ما قبل التعاقد.</p>
        </div>
        <div class="sales-home-head-actions">
            @if(auth()->user()->hasPermission('sales-leads.tasks'))<a class="btn btn-sm btn-light" href="{{ route('tasks.index',['view'=>'today','team'=>'sales']) }}">مهام اليوم</a>@endif
            <a class="btn btn-sm btn-primary" href="{{ route('sales-leads.index',['quick'=>'today']) }}">رحلة المبيعات</a>
        </div>
    </div>
    @if(auth()->user()->hasPermission('sales-leads.tasks') && ($salesFollowUpsToday ?? collect())->isNotEmpty())
        <div class="table-wrap"><table><thead><tr><th>العميل المحتمل</th><th>المطلوب</th><th>المرحلة</th><th>الموعد</th><th></th></tr></thead><tbody>
        @foreach($salesFollowUpsToday as $task)
            <tr>
                <td><strong>{{ $task->lead->company_name }}</strong><div class="muted">{{ $task->lead->contact_name ?: $task->lead->code }}</div></td>
                <td><span class="badge {{ $task->priority==='high'?'badge-danger':'badge-info' }}">{{ \App\Models\SalesLead::TASK_TYPES[$task->type] ?? 'متابعة' }}</span><div class="muted">{{ $task->title }}</div></td>
                <td><span class="sales-stage stage-{{ $task->lead->stage }}">{{ $task->lead->stageLabel() }}</span></td>
                <td class="{{ $task->due_at && $task->due_at->isPast()?'text-danger':'' }}"><strong>{{ $task->due_at?->format('Y-m-d H:i') }}</strong>@if($task->due_at && $task->due_at->isPast())<div class="muted">متأخرة</div>@endif</td>
                <td><a class="btn btn-sm btn-light" href="{{ route('sales-leads.show',$task->lead) }}">فتح</a></td>
            </tr>
        @endforeach
        </tbody></table></div>
        @if(($salesFollowUpsOverdueCount ?? 0)>0)<div class="sales-home-note"><strong>{{ $salesFollowUpsOverdueCount }}</strong> متابعة مبيعات متأخرة تحتاج تدخلًا.</div>@endif
    @elseif(auth()->user()->hasPermission('sales-leads.tasks'))
        <div class="empty-state">لا توجد متابعات مبيعات مستحقة اليوم.</div>
    @else
        <div class="empty-state">يمكنك متابعة العملاء المحتملين من شاشة رحلة المبيعات.</div>
    @endif
</div>
@endif

@if(auth()->user()->hasPermission('customer-success.view'))
<div class="card section-spaced dashboard-section cs-home-followups">
    <div class="card-header">
        <div>
            <h2>متابعات العملاء اليوم</h2>
            <p>تظهر تلقائيًا حسب حالة العميل وسبب احتياجه للمتابعة.</p>
        </div>
        @if(auth()->user()->hasPermission('customer-success.dashboard'))
            <a class="btn btn-sm btn-light" href="{{ route('customer-success.dashboard') }}">عرض قائمة المتابعة</a>
        @endif
    </div>
    @if($customerFollowUpsToday->isNotEmpty())
        <div class="table-wrap"><table><thead><tr><th>العميل</th><th>سبب المتابعة</th><th>حالة العميل</th><th>الموعد</th><th></th></tr></thead><tbody>
        @foreach($customerFollowUpsToday as $task)
            @php
                $cadence = str_contains((string)$task->description,'التكرار: يوميًا') ? 'يومية'
                    : (str_contains((string)$task->description,'التكرار: كل يومين') ? 'كل يومين'
                    : (str_contains((string)$task->description,'التكرار: كل 3 أيام') ? 'كل 3 أيام'
                    : (str_contains((string)$task->description,'التكرار: أسبوعيًا') ? 'أسبوعية' : 'دورية')));
            @endphp
            <tr>
                <td><strong>{{ $task->customer->name }}</strong><div class="muted">{{ $task->customer->code }}</div></td>
                <td><span class="badge {{ $task->priority==='critical'?'badge-danger':'badge-warning' }}">{{ str_replace(\App\Services\CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX,'',$task->title) }}</span> <span class="badge badge-info">{{ $cadence }}</span>@if($task->description)<div class="muted">{{ \Illuminate\Support\Str::limit($task->description,110) }}</div>@endif</td>
                <td>{{ $task->customer->successProfile?->lifecycleStatus?->name ?: 'غير محدد' }}</td>
                <td class="{{ $task->due_at && $task->due_at->isPast()?'text-danger':'' }}"><strong>{{ $task->due_at?->format('Y-m-d H:i') }}</strong>@if($task->due_at && $task->due_at->isPast())<div class="muted">متأخرة</div>@endif</td>
                <td><a class="icon-action" href="{{ route('customers.show',$task->customer) }}#customer-success" title="فتح ملف العميل" aria-label="فتح ملف العميل"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/></svg></a></td>
            </tr>
        @endforeach
        </tbody></table></div>
        @if($customerFollowUpsOverdueCount>0)<div class="cs-home-followup-note"><strong>{{ $customerFollowUpsOverdueCount }}</strong> متابعة متأخرة ضمن القائمة الحالية.</div>@endif
    @else
        <div class="empty-state">لا توجد متابعات عملاء مستحقة اليوم.</div>
    @endif
</div>
@endif

<div class="grid grid-2 dashboard-section">
    <div class="card">
        <div class="card-header"><div><h2>إجمالي المبالغ المفتوحة</h2><p>الرصيد المتبقي حسب عملة العقد</p></div></div>
        <div class="grid grid-3">
            @foreach(config('finance.currencies') as $code=>$label)
                <div class="detail-item"><span>{{ $label }}</span><strong class="money">{{ number_format((float)($receivablesByCurrency[$code] ?? 0),2) }} {{ $code }}</strong></div>
            @endforeach
        </div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>تحصيلات الشهر</h2><p>{{ now()->translatedFormat('F Y') }}</p></div><a class="btn btn-sm btn-outline" href="{{ route('collections.index') }}">كل التحصيلات</a></div>
        <div class="grid grid-3">
            @foreach(config('finance.currencies') as $code=>$label)
                <div class="detail-item"><span>{{ $label }}</span><strong class="money text-success">{{ number_format((float)($monthCollections[$code] ?? 0),2) }} {{ $code }}</strong></div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-2 dashboard-section">
    <div class="card">
        <div class="card-header"><div><h2>استحقاقات خلال 30 يومًا</h2><p>دفعات واشتراكات وصيانة قادمة</p></div><a class="btn btn-sm btn-light" href="{{ route('receivables.index') }}">عرض الكل</a></div>
        <div class="table-wrap"><table><thead><tr><th>العميل</th><th>الاستحقاق</th><th>التاريخ</th><th>المبلغ</th></tr></thead><tbody>
        @forelse($upcoming as $row)<tr><td><a href="{{ route('customers.show',$row->customer_id) }}">{{ $row->customer->name }}</a></td><td>{{ $row->name }}</td><td>{{ $row->due_date->format('Y-m-d') }}</td><td class="money">{{ number_format((float)$row->remaining_amount,2) }} {{ $row->currency }}</td></tr>@empty<tr><td colspan="4" class="empty-state">لا توجد استحقاقات قريبة.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>أقدم المتأخرات</h2><p>الأولوية في المتابعة والتحصيل</p></div><a class="btn btn-sm btn-danger" href="{{ route('receivables.index',['status'=>'overdue']) }}">كل المتأخرات</a></div>
        <div class="table-wrap"><table><thead><tr><th>العميل</th><th>الاستحقاق</th><th>متأخر</th><th>المتبقي</th></tr></thead><tbody>
        @forelse($overdue as $row)<tr><td>{{ $row->customer->name }}</td><td>{{ $row->name }}</td><td><span class="badge badge-danger">{{ $row->due_date->diffInDays(today()) }} يوم</span></td><td class="money text-danger">{{ number_format((float)$row->remaining_amount,2) }} {{ $row->currency }}</td></tr>@empty<tr><td colspan="4" class="empty-state">لا توجد متأخرات.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
</div>
@endsection
