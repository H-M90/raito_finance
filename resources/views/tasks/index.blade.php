@extends('layouts.app')
@section('title','المهام والمتابعات')
@section('page-title','المهام والمتابعات')
@section('page-subtitle','مركز عمل موحد لمهام المبيعات وإدارة الحسابات')

@section('content')
@php
    use App\Support\TaskCatalog;
    $qs = request()->except('page','view');
@endphp
<div class="task-center-page">
    <div class="task-center-head">
        <div>
            <span class="task-center-kicker">مركز العمل</span>
            <h2>المهام والمتابعات</h2>
            <p>إدارة مهام المبيعات وإدارة الحسابات من مكان واحد، مع أولوية وموعد ومسؤول واضح لكل مهمة.</p>
        </div>
        @if(auth()->user()->hasPermission('tasks.create'))
            <button class="btn btn-primary task-center-add" type="button" data-task-create>+ إضافة مهمة</button>
        @endif
    </div>

    <section class="task-focus-grid">
        <a class="task-focus-card {{ $view==='today'?'active':'' }}" href="{{ route('tasks.index',array_merge($qs,['view'=>'today'])) }}">
            <span class="task-focus-icon is-today"><x-ui-icon name="calendar-clock" /></span><span><small>مهام اليوم</small><strong>{{ number_format($metrics['today']) }}</strong><em>مهمة مفتوحة</em></span>
        </a>
        <a class="task-focus-card {{ $view==='overdue'?'active':'' }}" href="{{ route('tasks.index',array_merge($qs,['view'=>'overdue'])) }}">
            <span class="task-focus-icon is-overdue"><x-ui-icon name="triangle-alert" /></span><span><small>متأخرة</small><strong>{{ number_format($metrics['overdue']) }}</strong><em>تحتاج متابعة</em></span>
        </a>
        <a class="task-focus-card" href="{{ route('tasks.index',array_merge($qs,['view'=>'today','type'=>'meeting'])) }}">
            <span class="task-focus-icon is-meeting"><x-ui-icon name="calendar-days" /></span><span><small>اجتماعات اليوم</small><strong>{{ number_format($metrics['meetings']) }}</strong><em>اجتماع مجدول</em></span>
        </a>
        <a class="task-focus-card {{ $view==='done'?'active':'' }}" href="{{ route('tasks.index',array_merge($qs,['view'=>'done'])) }}">
            <span class="task-focus-icon is-done"><x-ui-icon name="circle-check" /></span><span><small>مكتملة اليوم</small><strong>{{ number_format($metrics['done_today']) }}</strong><em>تم إنجازها</em></span>
        </a>
    </section>

    <section class="card task-command-card">
        <div class="task-view-tabs">
            @foreach(['today'=>'اليوم','overdue'=>'متأخرة','upcoming'=>'قادمة','done'=>'مكتملة','all'=>'كل المهام'] as $key=>$label)
                <a class="{{ $view===$key?'active':'' }}" href="{{ route('tasks.index',array_merge($qs,['view'=>$key])) }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="get" class="task-filter-grid">
            <input type="hidden" name="view" value="{{ $view }}">
            <label><span>الفريق</span><select class="select" name="team"><option value="">كل الفرق</option>@foreach($teams as $code=>$label)<option value="{{ $code }}" @selected(request('team')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label><span>نوع المهمة</span><select class="select" name="type"><option value="">كل الأنواع</option>@foreach($types as $code=>$label)<option value="{{ $code }}" @selected(request('type')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label><span>المسؤول</span><select class="select" name="owner_id"><option value="">كل المسؤولين</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string)request('owner_id')===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
            <label><span>الأولوية</span><select class="select" name="priority"><option value="">كل الأولويات</option>@foreach($priorities as $code=>$label)<option value="{{ $code }}" @selected(request('priority')===$code)>{{ $label }}</option>@endforeach</select></label>
            <label class="task-search-control"><span>بحث</span><div><input class="input" name="q" value="{{ request('q') }}" placeholder="المهمة، العميل، المسؤول..."><button class="btn btn-light" type="submit">بحث</button></div></label>
        </form>
    </section>

    <section class="card task-workspace-card">
        <div class="task-workspace-head">
            <div><h3>{{ ['today'=>'مهام اليوم','overdue'=>'المهام المتأخرة','upcoming'=>'المهام القادمة','done'=>'المهام المكتملة','all'=>'كل المهام'][$view] }}</h3><p>{{ ['today'=>'المهام المطلوب تنفيذها اليوم.','overdue'=>'مهام تحتاج تدخلًا سريعًا.','upcoming'=>'المهام المجدولة بعد اليوم.','done'=>'المهام التي تم تنفيذها.','all'=>'عرض شامل لكل المهام والفرق.'][$view] }}</p></div>
            <span>{{ number_format($tasks->total()) }} مهمة</span>
        </div>

        <div class="task-table-wrap">
            <table class="task-center-table">
                <thead><tr><th></th><th>نوع المهمة</th><th>المهمة</th><th>العميل</th><th>الفريق</th><th>المسؤول</th><th>الموعد</th><th>الأولوية</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                @forelse($tasks as $task)
                    @php
                        $isOpen=in_array($task->status,['open','in_progress'],true);
                        $isOverdue=$isOpen && $task->due_at && \Carbon\Carbon::parse($task->due_at)->isPast();
                        $targetUrl=$task->source==='sales' && $task->target_id ? route('sales-leads.show',$task->target_id) : ($task->source==='customer' && $task->target_id ? route('customers.show',$task->target_id) : null);
                        $typeLabel=TaskCatalog::typeLabel($task->raw_type);
                        $priorityCode=TaskCatalog::normalizedPriority($task->priority);
                    @endphp
                    <tr class="{{ $isOverdue?'is-overdue':'' }} {{ $task->status==='completed'?'is-completed':'' }}">
                        <td class="task-check-cell">
                            @if($isOpen && auth()->user()->hasPermission('tasks.complete'))
                                <form method="post" action="{{ route('tasks.complete',[$task->source,$task->task_id]) }}">@csrf<button class="task-check-btn" title="إتمام المهمة" aria-label="إتمام المهمة"></button></form>
                            @elseif($task->status==='completed')<span class="task-check-done"><x-ui-icon name="check" /></span>@else<span class="task-check-muted"></span>@endif
                        </td>
                        <td><span class="task-type-pill t-{{ $task->normalized_type }}">{{ $typeLabel }}</span></td>
                        <td><div class="task-title-cell"><strong>{{ $task->title }}</strong><small>{{ $task->description ?: ($task->protected_task?'مهمة مرتبطة بمتابعة آلية أو خطة عمل':'') }}</small></div></td>
                        <td>@if($targetUrl)<a class="task-target" href="{{ $targetUrl }}"><strong>{{ $task->target_name }}</strong><small>{{ $task->target_contact }}</small></a>@else<div class="task-target"><strong>{{ $task->target_name }}</strong><small>بدون عميل مرتبط</small></div>@endif</td>
                        <td><span class="task-team-badge team-{{ $task->team }}">{{ TaskCatalog::teamLabel($task->team) }}</span></td>
                        <td>{{ $task->assignee_name ?: 'غير معين' }}</td>
                        <td><div class="task-date-cell {{ $isOverdue?'overdue':'' }}">@if($task->due_at)<strong>{{ \Carbon\Carbon::parse($task->due_at)->format('Y-m-d') }}</strong><small>{{ \Carbon\Carbon::parse($task->due_at)->format('H:i') }}</small>@else<strong>بدون موعد</strong><small>—</small>@endif</div></td>
                        <td><span class="task-priority-badge p-{{ $priorityCode }}">{{ TaskCatalog::priorityLabel($task->priority) }}</span></td>
                        <td><span class="task-status-badge s-{{ $task->status }}">{{ $task->status==='completed'?'مكتملة':($task->status==='cancelled'?'ملغاة':'مفتوحة') }}</span></td>
                        <td>
                            <button type="button" class="task-row-action" data-task-open
                                data-source="{{ $task->source }}" data-id="{{ $task->task_id }}" data-title="{{ $task->title }}"
                                data-description="{{ $task->description }}" data-team="{{ $task->team }}" data-type="{{ $task->normalized_type }}"
                                data-priority="{{ $priorityCode }}" data-due="{{ $task->due_at ? \Carbon\Carbon::parse($task->due_at)->format('Y-m-d\TH:i') : '' }}"
                                data-owner="{{ $task->assigned_to }}" data-owner-name="{{ $task->assignee_name }}" data-status="{{ $task->status }}"
                                data-target="{{ $task->target_name }}" data-target-url="{{ $targetUrl }}" data-protected="{{ $task->protected_task?1:0 }}">•••</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="task-empty-state"><strong>لا توجد مهام مطابقة</strong><span>جرّب تغيير الفترة أو الفلاتر، أو أضف مهمة جديدة.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="task-center-pagination">{{ $tasks->links() }}</div>
    </section>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasPermission('tasks.create'))
<div class="task-modal" data-task-modal aria-hidden="true">
    <div class="task-modal-card" role="dialog" aria-modal="true" aria-labelledby="task-create-title">
        <div class="task-modal-head"><div><span>مهمة جديدة</span><h3 id="task-create-title">إضافة مهمة متابعة</h3></div><button type="button" class="icon-button" data-task-modal-close><x-ui-icon name="x" /></button></div>
        <form method="post" action="{{ route('tasks.store') }}" data-task-create-form>@csrf
            <div class="task-form-grid">
                <div class="task-form-field span-2"><label>ترتبط بـ</label><div class="task-target-tabs"><button type="button" class="active" data-target-type="lead">عميل محتمل</button><button type="button" data-target-type="customer">عميل</button><button type="button" data-target-type="internal">مهمة داخلية</button></div><input type="hidden" name="target_type" value="lead" data-target-type-input></div>
                <div class="task-form-field span-2" data-target-lookup-wrap><label>العميل / العميل المحتمل</label><div class="task-target-lookup"><input class="input" type="search" autocomplete="off" placeholder="اكتب اسم الشركة، الشخص المسؤول أو رقم الجوال" data-target-search><input type="hidden" name="target_id" data-target-id><div class="task-target-results" data-target-results></div></div><small data-target-selected>لم يتم اختيار سجل بعد.</small></div>
                <div class="task-form-field span-2"><label>عنوان المهمة</label><input class="input" name="title" required maxlength="200" placeholder="مثال: مكالمة لمراجعة العرض"></div>
                <div class="task-form-field span-2"><label>تفاصيل إضافية <small>اختياري</small></label><textarea class="textarea" name="description" rows="3" maxlength="4000" placeholder="أي تفاصيل يحتاجها المسؤول قبل تنفيذ المهمة"></textarea></div>
                <div class="task-form-field"><label>الفريق</label><select class="select" name="team" required data-create-team>@foreach($teams as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                <div class="task-form-field"><label>نوع المهمة</label><select class="select" name="type" required>@foreach($types as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                <div class="task-form-field"><label>الأولوية</label><select class="select" name="priority" required><option value="high">عالية</option><option value="medium" selected>متوسطة</option><option value="low">منخفضة</option></select></div>
                <div class="task-form-field"><label>المسؤول</label><select class="select" name="assigned_to"><option value="">المسؤول الافتراضي</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></div>
                <div class="task-form-field"><label>التاريخ</label><input class="input" type="date" data-create-date required value="{{ now()->addDay()->format('Y-m-d') }}"></div>
                <div class="task-form-field"><label>الوقت</label><input class="input" type="time" data-create-time required value="10:00"></div>
                <input type="hidden" name="due_at" data-create-due>
            </div>
            <div class="task-modal-actions"><button class="btn btn-light" type="button" data-task-modal-close>إلغاء</button><button class="btn btn-primary" type="submit">حفظ المهمة</button></div>
        </form>
    </div>
</div>
@endif

<div class="task-detail-backdrop" data-task-detail-backdrop></div>
<aside class="task-detail-drawer" data-task-detail-drawer aria-hidden="true">
    <div class="task-detail-head"><button type="button" class="icon-button" data-task-detail-close><x-ui-icon name="x" /></button><div><span>تفاصيل المهمة</span><h3 data-detail-heading>—</h3></div></div>
    <div class="task-detail-body">
        <div class="task-detail-target"><span>مرتبطة بـ</span><a href="#" data-detail-target>—</a></div>
        <div class="task-protected-note" data-protected-note hidden>هذه المهمة ناتجة من متابعة آلية أو خطة عمل؛ يمكنك تعديل الموعد والأولوية والمسؤول بدون تغيير طبيعة المهمة.</div>
        @if(auth()->user()->hasPermission('tasks.update'))
        <form method="post" data-task-update-form>@csrf @method('PUT')
            <div class="task-form-grid one-col">
                <div class="task-form-field"><label>عنوان المهمة</label><input class="input" name="title" data-detail-title required maxlength="200"></div>
                <div class="task-form-field"><label>التفاصيل</label><textarea class="textarea" name="description" rows="4" data-detail-description></textarea></div>
                <div class="task-form-field"><label>الفريق</label><select class="select" name="team" data-detail-team>@foreach($teams as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                <div class="task-form-field"><label>نوع المهمة</label><select class="select" name="type" data-detail-type>@foreach($types as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
                <div class="task-detail-two"><div class="task-form-field"><label>الأولوية</label><select class="select" name="priority" data-detail-priority><option value="critical">حرجة</option><option value="high">عالية</option><option value="medium">متوسطة</option><option value="low">منخفضة</option></select></div><div class="task-form-field"><label>المسؤول</label><select class="select" name="assigned_to" data-detail-owner><option value="">غير معين</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></div></div>
                <div class="task-detail-two"><div class="task-form-field"><label>التاريخ</label><input class="input" type="date" data-detail-date required></div><div class="task-form-field"><label>الوقت</label><input class="input" type="time" data-detail-time required></div></div>
                <input type="hidden" name="due_at" data-detail-due>
            </div>
            <button class="btn btn-primary full" type="submit">حفظ التعديلات</button>
        </form>
        @endif
        <div class="task-detail-actions">
            @if(auth()->user()->hasPermission('tasks.complete'))
            <form method="post" data-task-complete-form>@csrf<button class="btn btn-success full" type="submit" data-complete-label>تمت المهمة</button></form>
            <form method="post" data-task-reopen-form>@csrf<button class="btn btn-light full" type="submit">إعادة فتح المهمة</button></form>
            @endif
        </div>
    </div>
</aside>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const modal=document.querySelector('[data-task-modal]');
    const openBtn=document.querySelector('[data-task-create]');
    const closeBtns=document.querySelectorAll('[data-task-modal-close]');
    const targetTabs=document.querySelectorAll('[data-target-type]');
    const targetTypeInput=document.querySelector('[data-target-type-input]');
    const lookupWrap=document.querySelector('[data-target-lookup-wrap]');
    const search=document.querySelector('[data-target-search]');
    const targetId=document.querySelector('[data-target-id]');
    const results=document.querySelector('[data-target-results]');
    const selected=document.querySelector('[data-target-selected]');
    const createForm=document.querySelector('[data-task-create-form]');
    const createTeam=document.querySelector('[data-create-team]');
    let targetType='lead', timer=null;

    function openModal(){ if(!modal)return; modal.classList.add('show');modal.setAttribute('aria-hidden','false');setTimeout(()=>search?.focus(),60); }
    function closeModal(){ if(!modal)return; modal.classList.remove('show');modal.setAttribute('aria-hidden','true'); }
    openBtn?.addEventListener('click',openModal);closeBtns.forEach(b=>b.addEventListener('click',closeModal));
    modal?.addEventListener('click',e=>{if(e.target===modal)closeModal()});

    function resetTarget(){if(targetId)targetId.value='';if(search)search.value='';if(selected)selected.textContent=targetType==='internal'?'لا تحتاج المهمة الداخلية إلى عميل مرتبط.':'لم يتم اختيار سجل بعد.';if(results){results.innerHTML='';results.classList.remove('show')}}
    targetTabs.forEach(btn=>btn.addEventListener('click',()=>{targetType=btn.dataset.targetType;targetTabs.forEach(x=>x.classList.toggle('active',x===btn));if(targetTypeInput)targetTypeInput.value=targetType;if(lookupWrap)lookupWrap.hidden=targetType==='internal';if(createTeam&&targetType==='lead')createTeam.value='sales';if(createTeam&&targetType==='customer')createTeam.value='account_management';resetTarget();}));

    function safeText(value){return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));}
    async function runSearch(){
        if(!search||!results||targetType==='internal')return;
        const q=search.value.trim();
        if(q.length<1){results.classList.remove('show');return;}
        try{
            const url=new URL(@json(route('tasks.targets')),window.location.origin);url.searchParams.set('type',targetType);url.searchParams.set('q',q);
            const response=await fetch(url,{headers:{'Accept':'application/json'}});const data=await response.json();
            results.innerHTML=(data.items||[]).map(item=>`<button type="button" data-target-result data-id="${Number(item.id)}" data-name="${safeText(item.name)}"><strong>${safeText(item.name)}</strong><small>${safeText(item.meta||'')}</small></button>`).join('')||'<div class="task-lookup-empty">لا توجد نتائج.</div>';
            results.classList.add('show');
        }catch(e){results.innerHTML='<div class="task-lookup-empty">تعذر البحث الآن.</div>';results.classList.add('show');}
    }
    search?.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(runSearch,220)});
    results?.addEventListener('click',e=>{const b=e.target.closest('[data-target-result]');if(!b)return;targetId.value=b.dataset.id;search.value=b.dataset.name;selected.textContent='تم اختيار: '+b.dataset.name;results.classList.remove('show');});
    createForm?.addEventListener('submit',e=>{if(targetType!=='internal'&&!targetId.value){e.preventDefault();selected.textContent='اختر العميل أو العميل المحتمل أولًا.';selected.classList.add('text-danger');return;}const d=document.querySelector('[data-create-date]').value,t=document.querySelector('[data-create-time]').value||'00:00';document.querySelector('[data-create-due]').value=d+'T'+t;});

    const drawer=document.querySelector('[data-task-detail-drawer]'),backdrop=document.querySelector('[data-task-detail-backdrop]');
    function closeDrawer(){drawer?.classList.remove('show');backdrop?.classList.remove('show');drawer?.setAttribute('aria-hidden','true')}
    document.querySelector('[data-task-detail-close]')?.addEventListener('click',closeDrawer);backdrop?.addEventListener('click',closeDrawer);
    document.querySelectorAll('[data-task-open]').forEach(btn=>btn.addEventListener('click',()=>{
        const source=btn.dataset.source,id=btn.dataset.id,status=btn.dataset.status,protectedTask=btn.dataset.protected==='1';
        drawer.classList.add('show');backdrop.classList.add('show');drawer.setAttribute('aria-hidden','false');
        document.querySelector('[data-detail-heading]').textContent=btn.dataset.title||'مهمة';
        const target=document.querySelector('[data-detail-target]');target.textContent=btn.dataset.target||'مهمة داخلية';target.href=btn.dataset.targetUrl||'#';target.classList.toggle('disabled',!btn.dataset.targetUrl);
        const title=document.querySelector('[data-detail-title]'),desc=document.querySelector('[data-detail-description]'),team=document.querySelector('[data-detail-team]'),type=document.querySelector('[data-detail-type]'),priority=document.querySelector('[data-detail-priority]'),owner=document.querySelector('[data-detail-owner]');
        if(title){title.value=btn.dataset.title||'';title.readOnly=protectedTask} if(desc)desc.value=btn.dataset.description||'';if(team){team.value=btn.dataset.team||'sales';team.classList.toggle('is-locked',protectedTask)}if(type){type.value=btn.dataset.type||'other';type.classList.toggle('is-locked',protectedTask)}if(priority)priority.value=btn.dataset.priority||'medium';if(owner)owner.value=btn.dataset.owner||'';
        document.querySelector('[data-protected-note]')?.toggleAttribute('hidden',!protectedTask);
        const due=(btn.dataset.due||'').split('T');const detailDate=document.querySelector('[data-detail-date]'),detailTime=document.querySelector('[data-detail-time]');if(detailDate)detailDate.value=due[0]||'';if(detailTime)detailTime.value=(due[1]||'').slice(0,5);
        const update=document.querySelector('[data-task-update-form]');if(update)update.action=`{{ url('/tasks') }}/${source}/${id}`;
        const complete=document.querySelector('[data-task-complete-form]'),reopen=document.querySelector('[data-task-reopen-form]');if(complete)complete.action=`{{ url('/tasks') }}/${source}/${id}/complete`;if(reopen)reopen.action=`{{ url('/tasks') }}/${source}/${id}/reopen`;
        if(complete)complete.hidden=status==='completed';if(reopen)reopen.hidden=status!=='completed'||protectedTask;
    }));
    document.querySelector('[data-task-update-form]')?.addEventListener('submit',()=>{const d=document.querySelector('[data-detail-date]')?.value||'',t=document.querySelector('[data-detail-time]')?.value||'00:00';const due=document.querySelector('[data-detail-due]');if(due)due.value=d+'T'+t;});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeDrawer();}});
});
</script>
@endpush
