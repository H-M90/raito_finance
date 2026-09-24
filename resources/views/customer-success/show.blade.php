@extends('layouts.app')
@section('title','نجاح العميل - '.$customer->name)
@section('page-title','نجاح العميل: '.$customer->name)
@section('page-subtitle','Lifecycle + Health + Flags + Signals + Playbooks + Tasks')
@section('content')
@php
$profile=$customer->successProfile;
$attentionLabels=['critical'=>'حرج','high'=>'مرتفع','medium'=>'متوسط','normal'=>'طبيعي','closed'=>'مغلق'];
$healthLabels=['VERY_SATISFIED'=>'راضٍ جدًا','SATISFIED'=>'راضٍ','NEUTRAL'=>'محايد','DISSATISFIED'=>'غير راضٍ','VERY_DISSATISFIED'=>'غير راضٍ جدًا'];
$signalLabels=['REFERRAL_READY'=>'مرشح Referral','TESTIMONIAL_READY'=>'مرشح Testimonial','AMBASSADOR'=>'عميل سفير','UPSELL'=>'Upsell','CROSS_SELL'=>'Cross-sell','EXPANSION_SALE'=>'فرصة توسع','RENEWAL'=>'تجديد'];
$signalResults=['won'=>'Won','lost'=>'Lost','deferred'=>'Deferred','closed'=>'Closed'];
$roleLabels=['ACCOUNTANT'=>'محاسب','FINANCE_MANAGER'=>'مدير مالي','DECISION_MAKER'=>'صاحب قرار','KEY_USER'=>'Key User','OWNER'=>'مالك/إدارة','OTHER'=>'أخرى'];
@endphp
<div class="card">
    <div class="card-header"><div><h2>{{ $customer->name }}</h2><p>{{ $customer->code }} · {{ $customer->phone ?: 'بدون هاتف' }}</p></div><div class="inline-actions"><a class="btn btn-light" href="{{ route('customers.show',$customer) }}">ملف العميل المالي</a><a class="btn btn-outline" href="{{ route('customer-success.dashboard') }}">لوحة نجاح العملاء</a></div></div>
    <div class="grid grid-4">
        <div class="detail-item"><span>Lifecycle</span><strong>{{ $profile->lifecycleStatus?->name ?: '—' }}</strong></div>
        <div class="detail-item"><span>Health</span><strong>{{ $healthLabels[$profile->health]??$profile->health }}</strong></div>
        <div class="detail-item"><span>Attention</span><strong class="{{ $profile->attention_level==='critical'?'text-danger':'' }}">{{ $attentionLabels[$profile->attention_level]??$profile->attention_level }}</strong></div>
        <div class="detail-item"><span>Account Owner</span><strong>{{ $profile->owner?->name ?: '—' }}</strong></div>
    </div>
</div>

<div class="grid grid-3" style="margin-top:18px">
@if(auth()->user()->hasPermission('customer-success.update'))
<div class="card"><div class="card-header"><h2>تغيير المرحلة</h2></div><form method="post" action="{{ route('customer-success.transition',$customer) }}">@csrf
<div class="field"><label>المرحلة الجديدة</label><select name="status_code" required>@foreach($statuses as $status)<option value="{{ $status->code }}" @selected($profile->lifecycle_status_id===$status->id)>{{ $status->name }}</option>@endforeach</select></div>
<div class="field"><label>سبب التغيير</label><input name="reason" maxlength="500" placeholder="سبب أو ملاحظة"></div><button class="btn btn-primary">تحديث المرحلة</button></form></div>
@endif
@if(auth()->user()->hasPermission('customer-success.health.update'))
<div class="card"><div class="card-header"><h2>Health / الرضا</h2></div><form method="post" action="{{ route('customer-success.health',$customer) }}">@csrf
<div class="field"><label>مستوى الرضا</label><select name="health" required>@foreach($health as $h)<option value="{{ $h }}" @selected($profile->health===$h)>{{ $healthLabels[$h]??$h }}</option>@endforeach</select></div>
<div class="field"><label>ملاحظة</label><input name="note" maxlength="1000" placeholder="سبب التقييم"></div><button class="btn btn-primary">حفظ Health</button></form></div>
@endif
@if(auth()->user()->hasPermission('customer-success.update'))
<div class="card"><div class="card-header"><h2>إدارة الحساب</h2></div><form method="post" action="{{ route('customer-success.profile.update',$customer) }}">@csrf @method('PUT')
<div class="field"><label>Account Owner</label><select name="owner_id"><option value="">—</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($profile->owner_id===$user->id)>{{ $user->name }}</option>@endforeach</select></div>
<div class="field"><label>المراجعة القادمة</label><input type="datetime-local" name="next_review_at" value="{{ $profile->next_review_at?->format('Y-m-d\TH:i') }}"></div>
<div class="field"><label>ملاحظات</label><textarea name="notes" rows="2">{{ $profile->notes }}</textarea></div><button class="btn btn-primary">حفظ</button></form></div>
@endif
</div>

<div class="grid grid-2" style="margin-top:18px">
<div class="card">
    <div class="card-header"><div><h2>Attention Flags</h2><p>يمكن وجود أكثر من Flag مع نفس Lifecycle.</p></div></div>
    @if(auth()->user()->hasPermission('customer-success.flags.manage'))
    <form method="post" action="{{ route('customer-success.flags.store',$customer) }}" class="filter-bar" style="margin-bottom:16px">@csrf
        <div class="field"><label>النوع</label><select name="flag_code" required>@foreach($flagTypes as $type)<option value="{{ $type->code }}">{{ $type->name }} · {{ $type->default_severity }}</option>@endforeach</select></div>
        <div class="field"><label>ملاحظة</label><input name="notes" placeholder="سبب فتح التنبيه"></div>
        <div class="field" style="align-self:end"><button class="btn btn-primary">فتح Flag</button></div>
    </form>
    @endif
    <div class="table-wrap"><table><thead><tr><th>النوع</th><th>الأولوية</th><th>الموعد</th><th>الحالة</th><th></th></tr></thead><tbody>
    @forelse($customer->attentionFlags as $flag)<tr><td><strong>{{ $flag->type?->name }}</strong><div class="muted">{{ $flag->notes }}</div></td><td>{{ $flag->severity }}</td><td>{{ $flag->due_at?->format('Y-m-d') ?: '—' }}</td><td>{{ $flag->status }}</td><td>@if($flag->status==='open'&&auth()->user()->hasPermission('customer-success.flags.manage'))<form method="post" action="{{ route('customer-success.flags.resolve',$flag) }}">@csrf<button class="btn btn-sm btn-light">إغلاق</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="empty-state">لا توجد Flags.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="card">
    <div class="card-header"><div><h2>Commercial Signals</h2><p>Referral / Testimonial / Upsell / Cross-sell / Expansion / Renewal.</p></div></div>
    @if(auth()->user()->hasPermission('customer-success.signals.manage'))
    <form method="post" action="{{ route('customer-success.signals.store',$customer) }}" class="grid grid-2" style="margin-bottom:16px">@csrf
        <div class="field"><label>الإشارة</label><select name="code" required>@foreach($signalTypes as $code)<option value="{{ $code }}">{{ $signalLabels[$code]??$code }}</option>@endforeach</select></div>
        <div class="field"><label>المسؤول</label><select name="owner_id"><option value="">المستخدم الحالي</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
        <div class="field"><label>قيمة متوقعة</label><input type="number" min="0" step="0.01" name="estimated_value"></div>
        <div class="field"><label>العملة</label><input name="currency" value="SAR" maxlength="3"></div>
        <div class="field"><label>الموعد</label><input type="datetime-local" name="due_at"></div>
        <div class="field"><label>ملاحظة</label><input name="notes"></div>
        <div><button class="btn btn-primary">إضافة Signal</button></div>
    </form>
    @endif
    <div class="table-wrap"><table><thead><tr><th>الإشارة</th><th>المسؤول</th><th>القيمة</th><th>الحالة</th><th></th></tr></thead><tbody>
    @forelse($customer->commercialSignals as $signal)<tr><td><strong>{{ $signalLabels[$signal->code]??$signal->code }}</strong><div class="muted">{{ $signal->notes }}</div></td><td>{{ $signal->owner?->name ?: '—' }}</td><td class="money">{{ $signal->estimated_value!==null?number_format((float)$signal->estimated_value,2).' '.$signal->currency:'—' }}</td><td>{{ $signal->status }}</td><td>@if($signal->status==='active'&&auth()->user()->hasPermission('customer-success.signals.manage'))<form method="post" action="{{ route('customer-success.signals.close',$signal) }}" class="inline-actions">@csrf<select name="status" style="min-width:95px">@foreach($signalResults as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select><button class="btn btn-sm btn-light">إغلاق</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="empty-state">لا توجد Signals.</td></tr>@endforelse
    </tbody></table></div>
</div>
</div>

<div class="grid grid-2" style="margin-top:18px">
<div class="card">
    <div class="card-header"><div><h2>Contacts & Stakeholders</h2><p>تغيير المحاسب/المدير المالي/صاحب القرار يفتح Flag تلقائيًا.</p></div></div>
    @if(auth()->user()->hasPermission('customer-success.contacts.manage'))
    <form method="post" action="{{ route('customer-success.contacts.store',$customer) }}" class="grid grid-2" style="margin-bottom:16px">@csrf
        <div class="field"><label>الاسم</label><input name="name" required></div>
        <div class="field"><label>الدور</label><select name="role_code"><option value="">—</option>@foreach($roleLabels as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label>الهاتف</label><input name="phone"></div><div class="field"><label>البريد</label><input type="email" name="email"></div>
        <label><input type="checkbox" name="is_primary" value="1"> جهة الاتصال الأساسية</label><div><button class="btn btn-primary">إضافة جهة اتصال</button></div>
    </form>
    @endif
    <div class="table-wrap"><table><thead><tr><th>الاسم</th><th>الدور</th><th>التواصل</th><th>الحالة</th></tr></thead><tbody>
    @forelse($customer->contacts as $contact)<tr><td><strong>{{ $contact->name }}</strong>@if($contact->is_primary)<span class="badge badge-info">أساسي</span>@endif</td><td>{{ $roleLabels[$contact->role_code]??($contact->role_code?:'—') }}</td><td>{{ $contact->phone ?: $contact->email ?: '—' }}</td><td>{{ $contact->is_active?'نشط':'سابق' }}</td></tr>@empty<tr><td colspan="4" class="empty-state">لا توجد جهات اتصال.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="card">
    <div class="card-header"><div><h2>تشغيل Playbook يدويًا</h2><p>للحالات التي لا يأتي Trigger لها من تكامل خارجي بعد.</p></div></div>
    @if(auth()->user()->hasPermission('customer-success.playbooks.run'))
    <form method="post" action="{{ route('customer-success.playbooks.run',$customer) }}" class="filter-bar" style="margin-bottom:16px">@csrf
        <div class="field"><label>Playbook</label><select name="playbook_id" required>@foreach($playbooks as $pb)<option value="{{ $pb->id }}">{{ $pb->name }}</option>@endforeach</select></div><div class="field" style="align-self:end"><button class="btn btn-primary">تشغيل</button></div>
    </form>
    @endif
    <div class="table-wrap"><table><thead><tr><th>الخطة</th><th>Trigger</th><th>المالك</th><th>SLA</th><th>الحالة</th></tr></thead><tbody>
    @forelse($customer->playbookRuns as $run)
    @php $resultChoices=$runResultOptions[$run->playbook?->result_code]??[]; @endphp
    <tr>
        <td><strong>{{ $run->playbook?->name }}</strong><div class="muted">{{ $run->playbook?->exit_criteria }}</div></td>
        <td>{{ $run->trigger_type }} · {{ $run->trigger_code }}</td>
        <td>{{ $run->owner?->name ?: '—' }}</td>
        <td class="{{ in_array($run->status,['active','awaiting_resolution'],true)&&$run->due_at&&$run->due_at->isPast()?'text-danger':'' }}">{{ $run->due_at?->format('Y-m-d H:i') ?: '—' }}</td>
        <td>
            <div>{{ $run->status }}</div>
            @if($run->status==='awaiting_resolution' && !empty($resultChoices) && auth()->user()->hasPermission('customer-success.playbooks.manage'))
            <form method="post" action="{{ route('customer-success.runs.complete',$run) }}" class="inline-actions" style="margin-top:6px">@csrf
                <select name="result_code" required style="min-width:150px"><option value="">اختر النتيجة</option>@foreach($resultChoices as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                <button class="btn btn-sm btn-primary">حسم</button>
            </form>
            @elseif($run->status==='awaiting_resolution' && $run->playbook?->result_code==='CLOSE_SIGNAL')
            <div class="muted">احسم النتيجة من Commercial Signals.</div>
            @endif
        </td>
    </tr>
    @empty<tr><td colspan="5" class="empty-state">لا يوجد تشغيل سابق.</td></tr>@endforelse
    </tbody></table></div>
</div>
</div>

<div class="card" style="margin-top:18px">
<div class="card-header"><div><h2>مهام الـPlaybooks</h2><p>SLA واضح لكل خطوة؛ النتائج الحتمية تُغلق تلقائيًا والنتائج متعددة الاحتمالات تنتظر الحسم.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>المهمة</th><th>Playbook</th><th>المسؤول</th><th>Priority</th><th>Due</th><th>الحالة</th><th></th></tr></thead><tbody>
@php $allTasks=$customer->playbookRuns->flatMap->tasks->sortBy(fn($t)=>$t->due_at?->timestamp??PHP_INT_MAX); @endphp
@forelse($allTasks as $task)
<tr>
    <td><strong>{{ $task->title }}</strong><div class="muted">{{ $task->description }}</div></td>
    <td>{{ $task->playbookRun?->playbook?->name }}</td>
    <td>
        <div>{{ $task->assignee?->name ?: '—' }}</div>
        @if($task->status!=='completed'&&auth()->user()->hasPermission('customer-success.tasks.assign'))
        <form method="post" action="{{ route('customer-success.tasks.assign',$task) }}" class="inline-actions" style="margin-top:5px;flex-wrap:wrap">@csrf @method('PUT')
            <select name="assigned_to" style="min-width:120px"><option value="">غير مسند</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($task->assigned_to===$user->id)>{{ $user->name }}</option>@endforeach</select>
            <select name="priority"><option value="normal" @selected($task->priority==='normal')>Normal</option><option value="medium" @selected($task->priority==='medium')>Medium</option><option value="high" @selected($task->priority==='high')>High</option><option value="critical" @selected($task->priority==='critical')>Critical</option></select>
            <input type="datetime-local" name="due_at" value="{{ $task->due_at?->format('Y-m-d\TH:i') }}" style="min-width:170px"><button class="btn btn-sm btn-light">حفظ</button>
        </form>
        @endif
    </td>
    <td>{{ $task->priority }}</td>
    <td class="{{ $task->status!=='completed'&&$task->due_at&&$task->due_at->isPast()?'text-danger':'' }}">{{ $task->due_at?->format('Y-m-d H:i') ?: '—' }}</td>
    <td>{{ $task->status }}</td>
    <td>@if($task->status!=='completed'&&auth()->user()->hasPermission('customer-success.tasks.complete'))<form method="post" action="{{ route('customer-success.tasks.complete',$task) }}">@csrf<button class="btn btn-sm btn-primary">تم</button></form>@endif</td>
</tr>
@empty<tr><td colspan="7" class="empty-state">لا توجد مهام.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="grid grid-2" style="margin-top:18px">
<div class="card"><div class="card-header"><h2>تاريخ Lifecycle</h2></div><div class="timeline-list">@forelse($customer->successStatusHistory as $row)<div class="timeline-row"><div><strong>{{ $row->fromStatus?->name ?: 'بداية' }} ← {{ $row->toStatus?->name }}</strong><div class="muted">{{ $row->reason }}</div></div><div class="muted">{{ $row->changed_at?->format('Y-m-d H:i') }}</div></div>@empty<div class="empty-state">لا توجد انتقالات مسجلة.</div>@endforelse</div></div>
<div class="card"><div class="card-header"><h2>Customer Success Events</h2></div><div class="timeline-list">@forelse($customer->successEvents as $event)<div class="timeline-row"><div><strong>{{ $event->event_code }}</strong><div class="muted">{{ $event->event_type }}</div></div><div class="muted">{{ $event->occurred_at?->format('Y-m-d H:i') }}</div></div>@empty<div class="empty-state">لا توجد Events.</div>@endforelse</div></div>
</div>
@endsection
