@php
$profile = $customer->successProfile;
$healthLabels = \App\Support\CustomerSuccessOptions::healthLevels();
$signalLabels = \App\Support\CustomerSuccessOptions::signalTypes();
$signalStatuses = \App\Support\CustomerSuccessOptions::signalStatuses();
$roleLabels = \App\Support\CustomerSuccessOptions::contactRoles();
$priorityLabels = \App\Support\CustomerSuccessOptions::priorities();
$followUpTypes = \App\Support\CustomerSuccessOptions::followUpTypes();
$autoPrefix = \App\Services\CustomerSuccessService::AUTO_FOLLOW_UP_PREFIX;

$openFlags = $customer->attentionFlags->where('status','open');
$activeSignals = $customer->commercialSignals->where('status','active');
$allTasks = $customer->successTasks;
$openTasks = $allTasks->whereIn('status',['open','in_progress'])->sortBy(fn($task) => $task->due_at?->timestamp ?? PHP_INT_MAX);
$completedTasks = $allTasks->where('status','completed')->sortByDesc(fn($task) => $task->completed_at?->timestamp ?? 0);
$overdueTasks = $openTasks->filter(fn($task) => $task->due_at && $task->due_at->isPast());
$nextTask = $openTasks->first();
$nextAutoCadence = null;
if ($nextTask && str_starts_with($nextTask->title,$autoPrefix)) {
    $nextAutoCadence = str_contains((string)$nextTask->description,'التكرار: يوميًا') ? 'يومية'
        : (str_contains((string)$nextTask->description,'التكرار: كل يومين') ? 'كل يومين'
        : (str_contains((string)$nextTask->description,'التكرار: كل 3 أيام') ? 'كل 3 أيام'
        : (str_contains((string)$nextTask->description,'التكرار: أسبوعيًا') ? 'أسبوعية' : 'دورية')));
}
$activeRuns = $customer->playbookRuns->whereIn('status',['active','awaiting_resolution']);
$pendingRun = $activeRuns->firstWhere('status','awaiting_resolution');
$primaryFlag = $openFlags->sortByDesc(fn($flag) => ['critical'=>4,'high'=>3,'medium'=>2,'normal'=>1][$flag->severity] ?? 0)->first();
$reviewOverdue = $profile?->next_review_at && $profile->next_review_at->isPast();

if ($overdueTasks->isNotEmpty()) {
    $simpleState = ['label'=>'متابعة متأخرة','class'=>'danger','hint'=>$overdueTasks->count().' متابعة تجاوزت موعدها'];
} elseif ($primaryFlag && in_array($primaryFlag->severity,['critical','high'],true)) {
    $simpleState = ['label'=>'يحتاج تدخل','class'=>'warning','hint'=>$primaryFlag->type?->name ?: 'يوجد سبب مهم للمتابعة'];
} elseif ($nextTask) {
    $simpleState = ['label'=>'متابعة مجدولة','class'=>'info','hint'=>'الإجراء القادم محدد بالفعل'];
} elseif ($pendingRun) {
    $simpleState = ['label'=>'يحتاج قرار','class'=>'warning','hint'=>'انتهت خطوات متابعة وتحتاج حسم النتيجة'];
} elseif ($reviewOverdue) {
    $simpleState = ['label'=>'موعد مراجعة','class'=>'warning','hint'=>'حان موعد إعادة تقييم العميل'];
} else {
    $simpleState = ['label'=>'لا يوجد إجراء عاجل','class'=>'success','hint'=>'لا توجد متابعة مستحقة الآن'];
}

$statusHistoryItems = $customer->successStatusHistory->map(function($row){
    $toName = $row->toStatus?->name ?: 'غير محدد';
    $title = ! $row->from_status_id
        ? 'التقييم الأولي للعميل: '.$toName
        : ((int)$row->from_status_id === (int)$row->to_status_id
            ? 'إعادة تقييم العميل — الحالة ما زالت '.$toName
            : 'تغيير حالة العميل من '.($row->fromStatus?->name ?: 'غير محدد').' إلى '.$toName);
    return [
        'at'=>$row->changed_at,
        'kind'=>'status',
        'title'=>$title,
        'detail'=>$row->reason,
        'actor'=>$row->changedBy?->name,
    ];
});
$followUpHistoryItems = $completedTasks->map(function($task) use ($autoPrefix){
    return [
        'at'=>$task->completed_at,
        'kind'=>'followup',
        'title'=>'تمت متابعة: '.str_replace($autoPrefix,'',$task->title),
        'detail'=>$task->completion_notes ?: $task->description,
        'actor'=>$task->completedBy?->name,
    ];
});
$customerHistory = $statusHistoryItems->concat($followUpHistoryItems)
    ->filter(fn($item)=>$item['at'])
    ->sortByDesc(fn($item)=>$item['at']->timestamp)
    ->take(20)
    ->values();
@endphp

<section class="card cs-simple-card" id="customer-success">
    <div class="cs-simple-header">
        <div>
            <div class="cs-simple-eyebrow">متابعة العميل</div>
            <div class="cs-simple-title-row">
                <h2>{{ $simpleState['label'] }}</h2>
                <span class="cs-simple-state cs-simple-state-{{ $simpleState['class'] }}">{{ $simpleState['hint'] }}</span>
            </div>
            <p>الحالة تصف وضع العميل، والتنبيه يوضح سبب التدخل الحالي، والمتابعة تحدد ما يجب تنفيذه.</p>
        </div>
        <div class="cs-header-actions">
            @if(auth()->user()->hasPermission('customer-success.update'))
                <details class="cs-reassess-menu">
                    <summary class="btn btn-outline">إعادة تقييم العميل</summary>
                    <form method="post" action="{{ route('customer-success.transition',$customer) }}" class="cs-reassess-popover">
                        @csrf
                        <strong>تغيير حالة العميل</strong>
                        <div class="form-group">
                            <label>الحالة الجديدة</label>
                            <select class="select" name="status_code" required>
                                @foreach($successData['statuses'] as $status)
                                    <option value="{{ $status->code }}" @selected($profile?->lifecycleStatus?->code===$status->code)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>سبب التغيير</label>
                            <input class="input" name="reason" maxlength="500" placeholder="مثال: العميل عاد للاستخدام الطبيعي">
                        </div>
                        <button class="btn btn-primary" type="submit">حفظ التقييم</button>
                    </form>
                </details>
            @endif
            @if(auth()->user()->hasPermission('customer-success.dashboard'))
                <a class="btn btn-light" href="{{ route('customer-success.dashboard') }}">قائمة المتابعة اليوم</a>
            @endif
        </div>
    </div>

    <div class="cs-simple-summary cs-summary-3">
        <div>
            <span>حالة العميل</span>
            <strong>{{ $profile?->lifecycleStatus?->name ?: 'غير محدد' }}</strong>
            <small>تعبر عن مرحلة العلاقة مع العميل.</small>
        </div>
        <div>
            <span>سبب المتابعة الحالي</span>
            <strong class="{{ $primaryFlag && $primaryFlag->severity==='critical' ? 'text-danger' : '' }}">{{ $primaryFlag?->type?->name ?: 'لا يوجد تنبيه حالي' }}</strong>
            <small>{{ $primaryFlag?->notes ?: 'أي مشكلة مؤقتة تظهر هنا، بدون تغيير معنى حالة العميل.' }}</small>
        </div>
        <div>
            <span>المتابعة القادمة</span>
            <strong class="{{ $nextTask?->due_at && $nextTask->due_at->isPast() ? 'text-danger' : '' }}">{{ $nextTask?->due_at?->format('Y-m-d H:i') ?: 'لا توجد متابعة مجدولة' }}</strong>
            <small>{{ $nextTask ? str_replace($autoPrefix,'',$nextTask->title) : 'سجل متابعة جديدة عند الحاجة.' }}</small>
        </div>
    </div>

    <div class="cs-daily-grid">
        <div class="cs-action-card {{ $overdueTasks->isNotEmpty() ? 'is-overdue' : '' }}">
            <div class="cs-action-card-head">
                <div><span class="cs-step-number">1</span><strong>المطلوب الآن</strong></div>
                @if($openTasks->count() > 1)<small>{{ $openTasks->count() }} متابعات مفتوحة</small>@endif
            </div>

            @if($nextTask)
                <div class="cs-next-action">
                    @if(str_starts_with($nextTask->title,$autoPrefix))<span class="cs-auto-badge">متابعة تلقائية {{ $nextAutoCadence }}</span>@endif
                    <h3>{{ str_replace($autoPrefix,'',$nextTask->title) }}</h3>
                    @if($nextTask->description)<p>{{ $nextTask->description }}</p>@endif
                    <div class="cs-next-meta">
                        <span>المسؤول: <strong>{{ $nextTask->assignee?->name ?: 'غير محدد' }}</strong></span>
                        <span class="{{ $nextTask->due_at && $nextTask->due_at->isPast() ? 'text-danger' : '' }}">الموعد: <strong>{{ $nextTask->due_at?->format('Y-m-d H:i') ?: 'بدون موعد' }}</strong></span>
                    </div>
                    @if(auth()->user()->hasPermission('customer-success.tasks.complete'))
                        <form method="post" action="{{ route('customer-success.tasks.complete',[$customer,$nextTask]) }}" class="cs-complete-form">
                            @csrf
                            <input class="input" name="completion_notes" maxlength="3000" placeholder="ماذا تم في المتابعة؟ - اختياري">
                            <button class="btn btn-primary" type="submit">تمت المتابعة</button>
                        </form>
                    @endif
                </div>
            @elseif($pendingRun)
                @php $resultChoices=$successData['runResultOptions'][$pendingRun->playbook?->result_code]??[]; @endphp
                <div class="cs-next-action">
                    <h3>{{ $pendingRun->playbook?->name ?: 'متابعة تحتاج حسم' }}</h3>
                    <p>تمت كل الخطوات. اختر النتيجة النهائية.</p>
                    @if(!empty($resultChoices) && auth()->user()->hasPermission('customer-success.playbooks.manage'))
                        <form method="post" action="{{ route('customer-success.runs.complete',[$customer,$pendingRun]) }}" class="cs-decision-form">
                            @csrf
                            <select class="select" name="result_code" required><option value="">اختر النتيجة</option>@foreach($resultChoices as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                            <button class="btn btn-primary">حفظ النتيجة</button>
                        </form>
                    @endif
                </div>
            @elseif($reviewOverdue)
                <div class="cs-next-action cs-next-empty"><h3>أعد تقييم العميل</h3><p>مر موعد المراجعة. استخدم زر «إعادة تقييم العميل» وحدد وضعه الحالي.</p></div>
            @else
                <div class="cs-next-action cs-next-empty"><div class="cs-done-icon" aria-hidden="true"></div><h3>لا يوجد شيء مطلوب الآن</h3><p>أي متابعة تلقائية مطلوبة حسب حالة العميل ستظهر هنا وحدها.</p></div>
            @endif
        </div>

        <div class="cs-action-card cs-add-followup">
            <div class="cs-action-card-head"><div><span class="cs-step-number">2</span><strong>سجل متابعة قادمة</strong></div></div>
            @if(auth()->user()->hasPermission('customer-success.update'))
                <form method="post" action="{{ route('customer-success.followups.store',$customer) }}" class="cs-quick-form">
                    @csrf
                    <div class="form-group"><label>نوع المتابعة</label><select class="select" name="type" required>@foreach($followUpTypes as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="form-group"><label>الموعد</label><input class="input" type="datetime-local" name="due_at" value="{{ now()->addDay()->setTime(10,0)->format('Y-m-d\TH:i') }}" required></div>
                    <div class="form-group"><label>المسؤول عن المتابعة</label><select class="select" name="assigned_to"><option value="">أنا</option>@foreach($successData['users'] as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label>ملاحظة مختصرة</label><textarea class="textarea" name="notes" rows="2" placeholder="مثال: التواصل بعد تجربة الموديول الجديد"></textarea></div>
                    <button class="btn btn-primary cs-save-followup" type="submit">حفظ المتابعة</button>
                </form>
            @else
                <div class="empty-state">ليس لديك صلاحية تسجيل متابعة جديدة.</div>
            @endif
        </div>
    </div>

    @if($openFlags->isNotEmpty() || $activeSignals->isNotEmpty())
        <div class="cs-important-row">
            <div class="cs-important-box">
                <div class="cs-important-head"><strong>تنبيهات تحتاج معالجة</strong><span>{{ $openFlags->count() }}</span></div>
                @forelse($openFlags->take(4) as $flag)
                    <div class="cs-important-item"><div><strong>{{ $flag->type?->name ?: 'تنبيه' }}</strong><small>{{ $flag->notes ?: 'سبب مؤقت يحتاج معالجة.' }}</small></div>@if(auth()->user()->hasPermission('customer-success.flags.manage'))<form method="post" action="{{ route('customer-success.flags.resolve',[$customer,$flag]) }}">@csrf<button class="btn btn-sm btn-light">تم الحل</button></form>@endif</div>
                @empty<div class="muted">لا توجد تنبيهات.</div>@endforelse
            </div>
            <div class="cs-important-box">
                <div class="cs-important-head"><strong>فرص مع العميل</strong><span>{{ $activeSignals->count() }}</span></div>
                @forelse($activeSignals->take(4) as $signal)
                    <div class="cs-important-item"><div><strong>{{ $signalLabels[$signal->code] ?? $signal->code }}</strong><small>{{ $signal->notes ?: 'فرصة تجارية مفتوحة.' }}</small></div><span class="badge badge-info">{{ $signalStatuses[$signal->status] ?? $signal->status }}</span></div>
                @empty<div class="muted">لا توجد فرص مفتوحة.</div>@endforelse
            </div>
        </div>
    @endif

    <div class="cs-history-visible">
        <div class="cs-history-visible-head"><div><h3>تاريخ حالات العميل والمتابعات</h3><p>تغييرات الحالة والمتابعات التي تمت مرتبة من الأحدث.</p></div><span>{{ $customerHistory->count() }} حدث</span></div>
        <div class="cs-history-timeline">
            @forelse($customerHistory as $item)
                <div class="cs-history-entry {{ $item['kind']==='status'?'is-status':'is-followup' }}">
                    <div class="cs-history-dot"></div>
                    <div class="cs-history-content">
                        <strong>{{ $item['title'] }}</strong>
                        @if($item['detail'])<p>{{ $item['detail'] }}</p>@endif
                        <small>{{ $item['at']->format('Y-m-d H:i') }}@if($item['actor']) · بواسطة {{ $item['actor'] }}@endif</small>
                    </div>
                </div>
            @empty
                <div class="empty-state">لم يتم تسجيل تغييرات حالة أو متابعات مكتملة بعد.</div>
            @endforelse
        </div>
    </div>

    <details class="cs-advanced" id="customer-success-advanced">
        <summary>إعدادات وتفاصيل متقدمة <span>الرضا، التنبيهات، الفرص، المهام وجهات الاتصال</span></summary>
        <div class="cs-advanced-body">
            <div class="grid grid-2">
                <div class="cs-section-box">
                    <div class="cs-section-title"><div><h3>رضا العميل</h3><p>مؤشر مستقل عن حالة العميل وعن التنبيهات.</p></div></div>
                    @if(auth()->user()->hasPermission('customer-success.health.update'))
                    <form method="post" action="{{ route('customer-success.health',$customer) }}" class="cs-quick-form">
                        @csrf
                        <div class="form-group"><label>مستوى الرضا</label><select class="select" name="health" required>@foreach($healthLabels as $code=>$label)<option value="{{ $code }}" @selected($profile?->health===$code)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-group"><label>ملاحظة</label><input class="input" name="note" maxlength="1000"></div>
                        <button class="btn btn-secondary">حفظ الرضا</button>
                    </form>
                    @endif
                </div>
                <div class="cs-section-box">
                    <div class="cs-section-title"><div><h3>إضافة تنبيه</h3><p>لسبب مؤقت يحتاج تدخل؛ لا تستخدمه بدل حالة العميل.</p></div></div>
                    @if(auth()->user()->hasPermission('customer-success.flags.manage'))
                    <form method="post" action="{{ route('customer-success.flags.store',$customer) }}" class="cs-quick-form">
                        @csrf
                        <div class="form-group"><label>سبب التنبيه</label><select class="select" name="flag_code" required>@foreach($successData['flagTypes'] as $type)<option value="{{ $type->code }}">{{ $type->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label>ملاحظة</label><textarea class="textarea" name="notes" rows="2"></textarea></div>
                        <button class="btn btn-secondary">إضافة التنبيه</button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="grid grid-2 cs-mt-14">
                <div class="cs-section-box">
                    <div class="cs-section-title"><div><h3>إضافة فرصة بيع</h3><p>للتوسع أو التجديد أو خدمة إضافية.</p></div></div>
                    @if(auth()->user()->hasPermission('customer-success.signals.manage'))
                    <form method="post" action="{{ route('customer-success.signals.store',$customer) }}" class="cs-quick-form">
                        @csrf
                        <div class="form-group"><label>نوع الفرصة</label><select class="select" name="code" required>@foreach($signalLabels as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="form-grid"><div class="form-group col-6"><label>قيمة تقديرية</label><input class="input" type="number" step="0.01" min="0" name="estimated_value"></div><div class="form-group col-6"><label>العملة</label><input class="input" name="currency" value="SAR" maxlength="3" required></div></div>
                        <div class="form-group"><label>موعد المتابعة</label><input class="input" type="datetime-local" name="due_at"></div>
                        <div class="form-group"><label>ملاحظة</label><textarea class="textarea" name="notes" rows="2"></textarea></div>
                        <button class="btn btn-secondary">إضافة الفرصة</button>
                    </form>
                    @endif
                </div>
                <div class="cs-section-box">
                    <div class="cs-section-title"><div><h3>خطط المتابعة الجارية</h3><p>تعمل في الخلفية عند ظهور حالة أو تنبيه.</p></div></div>
                    <div class="cs-run-list">
                        @forelse($activeRuns as $run)
                            <div class="cs-run-item is-active"><div class="cs-run-main"><strong>{{ $run->playbook?->name ?: 'خطة متابعة' }}</strong><span>{{ $run->status==='awaiting_resolution'?'تحتاج حسم':'جارية' }}</span></div><div class="cs-run-meta"><span>المهام المفتوحة: {{ $run->tasks->whereIn('status',['open','in_progress'])->count() }}</span><span>موعد الخطة: {{ $run->due_at?->format('Y-m-d H:i') ?: '—' }}</span></div></div>
                        @empty<div class="empty-state">لا توجد خطط جارية.</div>@endforelse
                    </div>
                </div>
            </div>

            <div class="cs-section-box cs-mt-14">
                <div class="cs-section-title"><div><h3>كل المتابعات المفتوحة</h3><p>للتفاصيل أو إعادة الإسناد.</p></div></div>
                <div class="cs-task-list">
                    @forelse($openTasks as $task)
                        <article class="cs-task-item {{ $task->due_at&&$task->due_at->isPast()?'is-overdue':'' }}">
                            <div class="cs-task-check" aria-hidden="true"></div>
                            <div class="cs-task-body">
                                <div class="cs-task-title"><strong>{{ str_replace($autoPrefix,'',$task->title) }}</strong><span class="badge {{ $task->priority==='critical'?'badge-danger':($task->priority==='high'?'badge-warning':'badge-info') }}">{{ $priorityLabels[$task->priority] ?? 'عادية' }}</span></div>
                                <div class="cs-task-meta"><span>{{ $task->assignee?->name ?: 'غير مسندة' }}</span><span>{{ $task->due_at?->format('Y-m-d H:i') ?: 'بدون موعد' }}</span></div>
                                @if($task->description)<div class="muted">{{ $task->description }}</div>@endif
                                <div class="inline-actions cs-task-actions">
                                    @if(auth()->user()->hasPermission('customer-success.tasks.complete'))<form method="post" action="{{ route('customer-success.tasks.complete',[$customer,$task]) }}">@csrf<button class="btn btn-sm btn-primary">تمت</button></form>@endif
                                    @if(auth()->user()->hasPermission('customer-success.tasks.assign'))
                                    <details class="cs-task-edit"><summary class="btn btn-sm btn-light">تعديل</summary><form method="post" action="{{ route('customer-success.tasks.assign',[$customer,$task]) }}" class="cs-task-edit-form">@csrf @method('PUT')<div class="form-group"><label>المسؤول</label><select class="select" name="assigned_to"><option value="">غير مسندة</option>@foreach($successData['users'] as $user)<option value="{{ $user->id }}" @selected($task->assigned_to===$user->id)>{{ $user->name }}</option>@endforeach</select></div><div class="form-group"><label>الأولوية</label><select class="select" name="priority">@foreach($priorityLabels as $code=>$label)<option value="{{ $code }}" @selected($task->priority===$code)>{{ $label }}</option>@endforeach</select></div><div class="form-group"><label>الموعد</label><input class="input" type="datetime-local" name="due_at" value="{{ $task->due_at?->format('Y-m-d\TH:i') }}"></div><button class="btn btn-sm btn-primary">حفظ</button></form></details>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty<div class="empty-state">لا توجد متابعات مفتوحة.</div>@endforelse
                </div>
            </div>

            <div class="cs-section-box cs-mt-14">
                <div class="cs-section-title"><div><h3>جهات الاتصال</h3><p>المحاسب والمدير المالي وصاحب القرار والمستخدمون الرئيسيون.</p></div></div>
                @if(auth()->user()->hasPermission('customer-success.contacts.manage'))
                <form method="post" action="{{ route('customer-success.contacts.store',$customer) }}" class="form-grid cs-form-card">
                    @csrf
                    <div class="form-group col-3"><label>الاسم</label><input class="input" name="name" required></div>
                    <div class="form-group col-3"><label>الدور</label><select class="select" name="role_code"><option value="">غير محدد</option>@foreach($roleLabels as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="form-group col-2"><label>الهاتف</label><input class="input" name="phone"></div>
                    <div class="form-group col-3"><label>البريد الإلكتروني</label><input class="input" type="email" name="email"></div>
                    <div class="form-group col-1 cs-checkbox-group"><label><input type="checkbox" name="is_primary" value="1"> أساسي</label></div>
                    <div class="form-group col-10"><label>ملاحظات</label><input class="input" name="notes"></div>
                    <div class="col-2 cs-align-end"><button class="btn btn-secondary">حفظ جهة الاتصال</button></div>
                </form>
                @endif
                <div class="table-wrap"><table><thead><tr><th>الاسم</th><th>الدور</th><th>الهاتف</th><th>البريد</th><th>الحالة</th></tr></thead><tbody>
                    @forelse($customer->contacts as $contact)<tr><td><strong>{{ $contact->name }}</strong>@if($contact->is_primary) <span class="badge badge-info">أساسي</span>@endif</td><td>{{ $roleLabels[$contact->role_code]??\App\Support\CustomerSuccessOptions::label($contact->role_code) }}</td><td>{{ $contact->phone ?: '—' }}</td><td>{{ $contact->email ?: '—' }}</td><td>{{ $contact->is_active?'حالي':'سابق' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">لا توجد جهات اتصال إضافية.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    </details>
</section>
