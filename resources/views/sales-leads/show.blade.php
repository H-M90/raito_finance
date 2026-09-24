@extends('layouts.app')
@section('title',$lead->company_name)
@section('page-title','ملف العميل المحتمل')
@section('page-subtitle','كل ما حدث مع العميل قبل التعاقد في مكان واحد')
@section('content')
@php
    $nextOpen=$lead->tasks->first(fn($t)=>$t->status==='open'&&$t->is_follow_up);
    $openTasks=$lead->tasks->where('status','open');
@endphp
<div class="sales-lead-profile">
    <section class="sales-lead-hero">
        <div class="sales-lead-avatar">{{ mb_substr($lead->company_name,0,1) }}</div>
        <div class="sales-lead-identity">
            <div class="sales-lead-title"><h2>{{ $lead->company_name }}</h2>@if($lead->favorite)<span class="sales-favorite">مميز</span>@endif</div>
            <p>{{ $lead->contact_name ?: 'لم يتم تحديد مسؤول اتصال' }} @if($lead->city) · {{ $lead->city }} @endif</p>
            <div class="sales-lead-tags"><span>{{ $lead->sector ?: 'قطاع غير محدد' }}</span><span>{{ $lead->sourceLabel() }}</span><span>{{ $lead->code }}</span></div>
        </div>
        <div class="sales-lead-hero-stats">
            <div><span>المرحلة</span><strong>{{ $lead->stageLabel() }}</strong></div>
            <div><span>الأولوية</span><strong class="score-text-{{ $lead->rating }}">{{ $lead->ratingLabel() }}</strong></div>
            <div><span>التأهيل</span><strong>{{ $lead->qualificationLabel() }}</strong></div>
        </div>
    </section>

    <div class="sales-profile-actions">
        <a class="btn btn-light" href="{{ route('sales-leads.index') }}">قائمة العملاء المحتملين</a>
        @if(auth()->user()->hasPermission('sales-leads.update'))<a class="btn btn-light" href="{{ route('sales-leads.edit',$lead) }}">تعديل البيانات</a>@endif
        @if(auth()->user()->hasPermission('sales-leads.tasks'))<a class="btn btn-light" href="#lead-task-create">+ مهمة متابعة</a>@endif
        @if($lead->customer_id)<a class="btn btn-primary" href="{{ route('customers.show',$lead->customer_id) }}">فتح العميل الحالي</a>@endif
    </div>

    <div class="sales-profile-grid">
        <div class="sales-profile-main">
            <section class="card sales-status-card">
                <div class="card-header"><div><h2>حالة المبيعات</h2><p>غيّر المرحلة والتقييم والمتابعة القادمة من نفس المكان.</p></div></div>
                @if(auth()->user()->hasPermission('sales-leads.update') && !$lead->customer_id)
                <form method="post" action="{{ route('sales-leads.update',$lead) }}" class="sales-status-form">@csrf @method('put')
                    <div class="form-grid cols-4">
                        <div class="form-group"><label>المرحلة الحالية</label><select class="select" name="stage">@foreach(\App\Models\SalesLead::STAGES as $code=>$label) @if($code==='won') @continue @endif <option value="{{ $code }}" @selected($lead->stage===$code)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-group"><label>أولوية العميل المحتمل</label><select class="select" name="rating">@foreach(\App\Models\SalesLead::RATINGS as $code=>$label)<option value="{{ $code }}" @selected($lead->rating===$code)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-group"><label>تم الرد؟</label><select class="select" name="responded"><option value="1" @selected($lead->responded)>نعم</option><option value="0" @selected(!$lead->responded)>لا</option></select></div>
                        <div class="form-group"><label>حالة التأهيل</label><select class="select" name="qualification">@foreach(\App\Models\SalesLead::QUALIFICATIONS as $code=>$label)<option value="{{ $code }}" @selected($lead->qualification===$code)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                    <div class="sales-rating-guide"><span class="hot"><b></b>ساخن<small>احتياج واضح وتوقيت قريب</small></span><span class="promising"><b></b>واعد<small>فرصة جيدة وبعض القرار ناقص</small></span><span class="medium"><b></b>متوسط<small>اهتمام موجود وجاهزية متوسطة</small></span><span class="low"><b></b>ضعيف<small>مؤشرات التحول محدودة</small></span></div>
                    <div class="sales-followup-editor">
                        <div class="sales-section-mini"><div><span>المتابعة القادمة</span><h3>{{ $lead->next_follow_up_title ?: 'لا توجد متابعة مجدولة' }}</h3></div><em>تُحفظ كمهمة</em></div>
                        <div class="form-grid cols-4">
                            <div class="form-group span-2"><label>المطلوب</label><input class="input" name="next_follow_up_title" value="{{ $lead->next_follow_up_title }}" placeholder="مثال: مراجعة العرض مع المدير المالي"></div>
                            <div class="form-group"><label>النوع</label><select class="select" name="next_follow_up_type">@foreach(\App\Models\SalesLead::FOLLOW_UP_TYPES as $code=>$label)<option value="{{ $code }}" @selected(($lead->next_follow_up_type?:'call')===$code)>{{ $label }}</option>@endforeach</select></div>
                            <div class="form-group"><label>الأولوية</label><select class="select" name="next_follow_up_priority">@foreach(\App\Models\SalesLead::PRIORITIES as $code=>$label)<option value="{{ $code }}" @selected(($lead->next_follow_up_priority?:'medium')===$code)>{{ $label }}</option>@endforeach</select></div>
                            <div class="form-group span-2"><label>الموعد</label><input class="input" type="datetime-local" name="next_follow_up_at" value="{{ $lead->next_follow_up_at?->format('Y-m-d\TH:i') }}"></div>
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">حفظ حالة المبيعات</button></div>
                </form>
                @endif
            </section>

            <section class="card" id="lead-activity"><div class="card-header"><div><h2>رحلة العميل والنشاط</h2><p>سجل زمني لا يضيع مع انتقال العميل بين المراحل.</p></div></div>
                <div class="sales-timeline">
                @forelse($lead->activities as $activity)<div class="sales-timeline-item"><i></i><div><strong>{{ $activity->type }}</strong><p>{{ $activity->description }}</p><small>{{ $activity->occurred_at?->format('Y-m-d H:i') }} @if($activity->creator) · {{ $activity->creator->name }} @endif</small></div></div>@empty<div class="empty-state">لا يوجد نشاط مسجل بعد.</div>@endforelse
                </div>
            </section>

            <section class="card"><div class="card-header"><div><h2>الملاحظات</h2><p>ملاحظات الاجتماعات والمكالمات والاحتياجات.</p></div></div>
                @if(auth()->user()->hasPermission('sales-leads.update'))<form class="sales-note-form" method="post" action="{{ route('sales-leads.notes.store',$lead) }}">@csrf<textarea class="textarea" name="body" rows="3" required placeholder="أضف ملاحظة جديدة..."></textarea><button class="btn btn-primary">إضافة ملاحظة</button></form>@endif
                <div class="sales-note-list">@forelse($lead->notes as $note)<div class="sales-note"><p>{{ $note->body }}</p><small>{{ $note->creator?->name ?: 'النظام' }} · {{ $note->created_at->format('Y-m-d H:i') }}</small></div>@empty<div class="empty-state">لا توجد ملاحظات.</div>@endforelse</div>
            </section>

            <section class="card" id="lead-task-create"><div class="card-header"><div><h2>المهام والمتابعات</h2><p>{{ $openTasks->count() }} مهمة مفتوحة</p></div></div>
                @if(auth()->user()->hasPermission('sales-leads.tasks'))<form method="post" action="{{ route('sales-leads.tasks.store',$lead) }}" class="sales-task-create">@csrf
                    <div class="form-grid cols-4"><div class="form-group span-2"><label>عنوان المهمة</label><input class="input" name="title" required placeholder="مثال: مكالمة لمراجعة العرض"></div><div class="form-group"><label>النوع</label><select class="select" name="type">@foreach(\App\Models\SalesLead::TASK_TYPES as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div><div class="form-group"><label>الأولوية</label><select class="select" name="priority">@foreach(\App\Models\SalesLead::PRIORITIES as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div><div class="form-group span-2"><label>الموعد</label><input class="input" type="datetime-local" name="due_at" required value="{{ now()->addDay()->setTime(10,0)->format('Y-m-d\TH:i') }}"></div><div class="form-group span-2"><label>المسؤول</label><select class="select" name="assigned_to"><option value="">مسؤول العميل</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></div></div><button class="btn btn-light">إضافة المهمة</button>
                </form>@endif
                <div class="sales-task-list">@forelse($lead->tasks as $task)<div class="sales-task-row {{ $task->status==='completed'?'done':'' }}"><div class="sales-task-type">{{ \App\Models\SalesLead::TASK_TYPES[$task->type]??'مهمة' }}</div><div class="sales-task-body"><strong>{{ $task->title }}</strong><small>{{ $task->due_at?->format('Y-m-d H:i') }} · {{ $task->assignee?->name ?: $lead->owner?->name ?: 'غير معين' }}</small></div><span class="sales-task-priority p-{{ $task->priority }}">{{ \App\Models\SalesLead::PRIORITIES[$task->priority]??$task->priority }}</span>@if($task->status==='open'&&auth()->user()->hasPermission('sales-leads.tasks'))<form method="post" action="{{ route('sales-leads.tasks.complete',[$lead,$task]) }}">@csrf<button class="btn btn-sm btn-light">تمت</button></form>@else<span class="badge badge-success">مكتملة</span>@endif</div>@empty<div class="empty-state">لا توجد مهام.</div>@endforelse</div>
            </section>
        </div>

        <aside class="sales-profile-side">
            <section class="card sales-contact-card"><div class="card-header"><div><h2>بيانات العميل المحتمل</h2></div></div><div class="sales-info-grid">
                @foreach([['الجوال',$lead->phone],['البريد',$lead->email],['المدينة',$lead->city],['القطاع',$lead->sector],['المصدر',$lead->sourceLabel()],['المسؤول',$lead->owner?->name]] as [$label,$value])<div><span>{{ $label }}</span><strong>{{ $value ?: '—' }}</strong></div>@endforeach
            </div></section>

            <section class="card"><div class="card-header"><div><h2>الخصائص المهتم بها</h2><p>ما الذي يبحث عنه العميل داخل النظام؟</p></div></div>
                @if(auth()->user()->hasPermission('sales-leads.update'))<form method="post" action="{{ route('sales-leads.interests.store',$lead) }}" class="sales-interest-add">@csrf<input class="input" name="name" required placeholder="مثال: المخزون أو ZATCA"><button class="btn btn-primary">+</button></form>@endif
                <div class="sales-interest-list">@forelse($lead->interests as $interest)<span>{{ $interest->name }}@if(auth()->user()->hasPermission('sales-leads.update'))<form method="post" action="{{ route('sales-leads.interests.destroy',[$lead,$interest]) }}">@csrf @method('delete')<button aria-label="حذف"><x-ui-icon name="x" /></button></form>@endif</span>@empty<small class="muted">لم تسجل اهتمامات بعد.</small>@endforelse</div>
            </section>

            <section class="sales-next-card {{ $lead->next_follow_up_at && $lead->next_follow_up_at->isPast()?'is-overdue':'' }}">
                <span>المتابعة القادمة</span><h3>{{ $lead->next_follow_up_title ?: 'لا توجد متابعة مجدولة' }}</h3>
                @if($lead->next_follow_up_at)<div class="sales-next-meta"><b>{{ $lead->next_follow_up_at->format('Y-m-d') }}</b><b>{{ $lead->next_follow_up_at->format('H:i') }}</b><b>{{ \App\Models\SalesLead::TASK_TYPES[$lead->next_follow_up_type]??'متابعة' }}</b></div>@endif
                @if($nextOpen&&auth()->user()->hasPermission('sales-leads.tasks'))<form method="post" action="{{ route('sales-leads.tasks.complete',[$lead,$nextOpen]) }}">@csrf<button class="btn btn-primary full">تمت المتابعة</button></form>@endif
            </section>

            <section class="sales-convert-card {{ $lead->customer_id?'converted':($lead->qualification==='qualified'?'ready':'blocked') }}">
                @if($lead->customer_id)<span>تم التحويل</span><h3>أصبح عميلًا فعليًا</h3><p>الرحلة قبل التعاقد محفوظة ويمكن الرجوع لها.</p><a class="btn btn-primary full" href="{{ route('customers.show',$lead->customer_id) }}">فتح ملف العميل</a>
                @elseif($lead->qualification==='qualified' && auth()->user()->hasPermission('sales-leads.convert'))<span>جاهز للتحويل</span><h3>تحويل إلى عميل</h3><p>سيتم إنشاء ملف عميل جديد وربطه بهذه الرحلة.</p><form method="post" action="{{ route('sales-leads.convert',$lead) }}" data-confirm="تحويل العميل المحتمل إلى عميل فعلي؟">@csrf<button class="btn btn-success full">تحويل إلى عميل</button></form>
                @else<span>التحويل غير متاح</span><h3>أكمل التأهيل أولًا</h3><p>حالة التأهيل الحالية: {{ $lead->qualificationLabel() }}.</p>@endif
            </section>
        </aside>
    </div>
</div>
@endsection
