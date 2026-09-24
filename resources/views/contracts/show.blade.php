@extends('layouts.app')
@section('title',$contract->number)
@section('page-title','العقد '.$contract->number)
@section('page-subtitle',$contract->customer->name.' · بداية الخدمة '.$contract->service_start_date->format('Y-m-d'))
@section('content')
<div class="card contract-overview-card">
    <div class="card-header">
        <div>
            <h2>{{ $contract->customer->name }} @if($contract->customer->segment==='startup')<span class="badge badge-info">شركة ناشئة</span>@endif</h2>
            <p>{{ \App\Support\FinanceOptions::activityTypes()[$contract->activity_type] }} · {{ \App\Support\FinanceOptions::billingCycles()[$contract->billing_cycle] }} · {{ $contract->currency }}</p>
        </div>
        <div class="inline-actions page-no-print">
            @if(auth()->user()->hasPermission('contracts.update')&&$contract->status!=='cancelled')<a class="btn btn-light" href="{{ route('contracts.edit',$contract) }}">تعديل</a>@endif
            @if(auth()->user()->hasPermission('addendums.create')&&$contract->status!=='cancelled')<a class="btn btn-outline" href="{{ route('addendums.create',$contract) }}">+ إضافة ملحق</a>@endif
            @if($contract->activity_type==='stations'&&auth()->user()->hasPermission('installations.create')&&$contract->status!=='cancelled')<a class="btn btn-outline" href="{{ route('installations.create',['contract_id'=>$contract->id]) }}">+ تركيب</a>@endif
            @if(auth()->user()->hasPermission('reports.statements'))<a class="btn btn-outline" href="{{ route('customers.statement',['customer'=>$contract->customer_id,'currency'=>$contract->currency,'contract_id'=>$contract->id]) }}">كشف الحساب</a>@endif
            @if($contract->status!=='cancelled'&&auth()->user()->hasPermission('collections.create'))<a class="btn btn-primary" href="{{ route('collections.create',['customer_id'=>$contract->customer_id,'contract_id'=>$contract->id]) }}">+ سند قبض</a>@endif
            @if($contract->status==='cancelled'&&auth()->user()->hasPermission('contracts.reopen'))
                <form method="post" action="{{ route('contracts.reopen',$contract) }}" class="inline-form">@csrf<button class="btn btn-outline">إعادة فتح</button></form>
            @elseif(auth()->user()->hasPermission('contracts.cancel'))
                <form method="post" action="{{ route('contracts.cancel',$contract) }}" class="inline-form" data-confirm="سيتم إلغاء العقد واستحقاقاته غير المحصلة. متابعة؟">@csrf<input type="hidden" name="cancellation_reason" value="إلغاء بواسطة المستخدم"><button class="btn btn-danger">إلغاء العقد</button></form>
            @endif
        </div>
    </div>

    <div class="detail-list">
        <div class="detail-item"><span>نوع النشاط</span><strong>{{ \App\Support\FinanceOptions::activityTypes()[$contract->activity_type] }}</strong></div>
        <div class="detail-item"><span>مسؤول المبيعات</span><strong>{{ $contract->salesOwner?->name ?: 'غير محدد' }}</strong></div>
        @if($contract->domain)<div class="detail-item"><span>الدومين</span><strong>{{ $contract->domain }}</strong></div>@endif
        <div class="detail-item"><span>صافي العقد الأصلي قبل الضريبة</span><strong class="money">{{ number_format((float)$contract->net_total,2) }} {{ $contract->currency }}</strong></div>
        <div class="detail-item"><span>إجمالي الخصومات</span><strong class="money">{{ number_format((float)$contract->discount_total,2) }} {{ $contract->currency }}</strong></div>
        <div class="detail-item"><span>الإجمالي شامل الضريبة</span><strong class="money">{{ number_format((float)$contract->grand_total,2) }} {{ $contract->currency }}</strong></div>
        @if(in_array($contract->billing_cycle,['monthly','annual'],true))
            <div class="detail-item"><span>{{ $contract->billing_cycle==='monthly'?'الاشتراك الشهري الحالي':'الاشتراك السنوي الحالي' }}</span><strong class="money">{{ number_format((float)$currentRecurringNet,2) }} {{ $contract->currency }}</strong></div>
        @endif
        @if($contract->billing_cycle==='one_time')
            <div class="detail-item"><span>الصيانة السنوية الأصلية</span><strong class="money">{{ number_format((float)$contract->maintenance_total,2) }} {{ $contract->currency }}</strong></div>
            <div class="detail-item"><span>الصيانة السنوية الحالية</span><strong class="money">{{ number_format((float)$currentMaintenanceNet,2) }} {{ $contract->currency }}</strong></div>
            <div class="detail-item"><span>الصيانة القادمة</span><strong>{{ optional($contract->next_maintenance_date)->format('Y-m-d')?:'لا توجد' }}</strong></div>
        @endif
        @if($contract->activity_type==='stations')
            <div class="detail-item"><span>سعة الأجهزة من البنود</span><strong>{{ (int)$contract->pts_count }} PTS · {{ (int)$contract->sensor_count }} حساس</strong></div>
        @endif
    </div>

    @if($contract->intermediary)
        <div class="context-panel section-tight">
            <div><span class="context-label">الوسيط</span><strong>{{ $contract->intermediary->name }}</strong></div>
            <div><span class="context-label">طريقة العمولة</span><strong>{{ $contract->commission_type==='percentage' ? number_format((float)$contract->commission_value,2).'%' : 'قيمة ثابتة' }}</strong></div>
            <div><span class="context-label">قيمة العمولة</span><strong>{{ number_format((float)$contract->commission_total,2) }} {{ $contract->currency }}</strong><small>محسوبة من صافي العقد بعد الخصم وقبل الضريبة، والاستحقاق مرتبط بالتحصيل.</small></div>
        </div>
    @endif

    @if($contract->pricingOffers->isNotEmpty())<div class="summary-strip section-tight">@foreach($contract->pricingOffers as $offer)<span class="summary-chip">{{ $offer->name }}</span>@endforeach</div>@endif
    @if($contract->is_imported)<div class="alert alert-success section-tight">عقد مستورد؛ يبدأ الحساب التفصيلي من {{ optional($contract->calculation_start_date)->format('Y-m-d') }}، والتحصيلات السابقة: {{ number_format((float)$contract->previous_collections_total,2) }} {{ $contract->currency }}.</div>@endif
    @if(auth()->user()->hasPermission('contracts.update')&&$contract->status==='active')
        <details class="historical-generation-panel section-tight">
            <summary><strong>توليد استحقاقات تاريخية</strong><span>لإدخال البيانات القديمة من تاريخ موثوق بدون الرجوع لبداية العقد.</span></summary>
            <form method="post" action="{{ route('contracts.generate-historical',$contract) }}" class="historical-generation-form">
                @csrf
                <div><label class="required">من تاريخ</label><input class="input" type="date" name="from_date" min="{{ $contract->service_start_date->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" value="{{ old('from_date',optional($contract->calculation_start_date)->format('Y-m-d')?:today()->format('Y-m-d')) }}" required></div>
                <div><label>حتى تاريخ</label><input class="input" type="date" name="until_date" max="{{ today()->format('Y-m-d') }}" value="{{ old('until_date',today()->format('Y-m-d')) }}"></div>
                <button class="btn btn-primary" type="submit">توليد الاستحقاقات</button>
                <small>التوليد Duplicate-safe: الاستحقاقات الموجودة لا تتكرر، ويتم إنشاء الشهري/السنوي أو الصيانة حسب نوع العقد.</small>
            </form>
        </details>
    @endif
</div>

@if($contract->billing_cycle==='one_time')
<div class="card section-spaced">
    <div class="card-header"><div><h2>إعداد صيانة العقد</h2><p>يمكن ترك الصيانة محسوبة تلقائيًا من البنود الفعالة أو تثبيت قيمة سنوية يدوية من تاريخ محدد.</p></div><span class="badge {{ $currentMaintenanceSetting?->mode==='manual'?'badge-warning':'badge-success' }}">{{ $currentMaintenanceSetting?->mode==='manual'?'يدوي':'تلقائي' }}</span></div>
    <div class="detail-list">
        <div class="detail-item"><span>القيمة الحالية قبل الضريبة</span><strong class="money">{{ number_format((float)$currentMaintenanceNet,2) }} {{ $contract->currency }}</strong></div>
        <div class="detail-item"><span>طريقة الحساب الحالية</span><strong>{{ $currentMaintenanceSetting?->mode==='manual'?'قيمة يدوية ثابتة':'من مجموع صيانة البنود الفعالة' }}</strong></div>
        @if($currentMaintenanceSetting)<div class="detail-item"><span>سارية من</span><strong>{{ $currentMaintenanceSetting->effective_from->format('Y-m-d') }}</strong></div>@endif
    </div>
    @if($currentMaintenanceSetting?->mode==='manual')<div class="alert alert-warning section-tight">الوضع اليدوي يتغلب على حساب البنود. إيقاف منتج لن يغير قيمة الصيانة أثناء سريان الـOverride اليدوي؛ ارجع إلى «تلقائي» إذا أردت أن تتغير الصيانة حسب المنتجات الفعالة.</div>@endif
    @if(auth()->user()->hasPermission('contracts.update')&&$contract->status==='active')
    <form method="post" action="{{ route('contracts.maintenance-setting',$contract) }}" class="filters page-no-print">
        @csrf
        <div class="form-group"><label>طريقة الحساب</label><select class="select" name="mode" id="maintenance-mode"><option value="auto" @selected(old('mode',$currentMaintenanceSetting?->mode?:'auto')==='auto')>تلقائي من البنود</option><option value="manual" @selected(old('mode',$currentMaintenanceSetting?->mode)==='manual')>قيمة يدوية</option></select></div>
        <div class="form-group"><label>القيمة السنوية قبل الضريبة</label><input class="input" type="number" step="0.01" min="0" name="annual_amount" value="{{ old('annual_amount',$currentMaintenanceSetting?->mode==='manual'?$currentMaintenanceSetting->annual_amount:$currentMaintenanceNet) }}"></div>
        <div class="form-group"><label>ساري من</label><input class="input" type="date" name="effective_from" min="{{ $contract->service_start_date->format('Y-m-d') }}" value="{{ old('effective_from',today()->format('Y-m-d')) }}" required></div>
        <div class="form-group search"><label>سبب التعديل</label><input class="input" name="reason" value="{{ old('reason') }}" placeholder="اختياري"></div>
        <button class="btn btn-primary" type="submit">حفظ قيمة الصيانة</button>
    </form>
    @endif
    @if($contract->maintenanceSettings->isNotEmpty())
    <details class="historical-generation-panel section-tight"><summary><strong>سجل تغييرات الصيانة</strong><span>{{ $contract->maintenanceSettings->count() }} تغيير</span></summary><div class="table-wrap"><table><thead><tr><th>ساري من</th><th>الطريقة</th><th>القيمة</th><th>السبب</th><th>بواسطة</th></tr></thead><tbody>@foreach($contract->maintenanceSettings->sortByDesc('effective_from') as $setting)<tr><td>{{ $setting->effective_from->format('Y-m-d') }}</td><td>{{ $setting->mode==='manual'?'يدوي':'تلقائي' }}</td><td class="money">{{ $setting->mode==='manual'?number_format((float)$setting->annual_amount,2).' '.$contract->currency:'حسب البنود' }}</td><td>{{ $setting->reason?:'—' }}</td><td>{{ $setting->creator?->name?:'—' }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</div>
@endif

@if(auth()->user()->hasPermission('attachments.download')&&$contract->attachments->isNotEmpty())
<div class="card section-spaced"><div class="card-header"><h2>مرفقات العقد</h2></div><div class="attachment-list">@foreach($contract->attachments as $attachment)<a class="attachment-link" href="{{ route('attachments.download',$attachment) }}" target="_blank" rel="noopener"><span>📎</span><span><strong>{{ $attachment->label ?: $attachment->original_name }}</strong><small>{{ $attachment->original_name }}</small></span></a>@endforeach</div></div>
@endif

<div class="grid grid-4 section-spaced">
    <div class="stat-card"><div class="label">الإيراد مع الملحقات</div><div class="value">{{ number_format($revenue,2) }}</div><div class="hint">{{ $contract->currency }}</div></div>
    <div class="stat-card"><div class="label">تكلفة المشتريات</div><div class="value">{{ number_format($purchaseCost,2) }}</div><div class="hint">{{ $contract->currency }}</div></div>
    <div class="stat-card"><div class="label">المصروفات + العمولة</div><div class="value">{{ number_format($expenseCost+(float)$contract->commission_total,2) }}</div><div class="hint">{{ $contract->currency }}</div></div>
    <div class="stat-card"><div class="label">الربح التقديري</div><div class="value {{ $profit>=0?'profit-positive':'profit-negative' }}">{{ number_format($profit,2) }}</div><div class="hint">قبل المصروفات العامة</div></div>
</div>

<div class="card section-spaced">
    <div class="card-header"><div><h2>بنود العقد والمستخدمون</h2><p>إيقاف البند لا يحذفه من العقد؛ يظل ظاهرًا تاريخيًا ويخرج من الاشتراك أو الصيانة من تاريخ الإيقاف.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>البند</th><th>الكمية</th><th>المستخدمون/المناديب</th><th>المجاني الكلي</th><th>المحسوب بسعر</th><th>تكلفة المستخدمين</th><th>صافي البند</th><th>الصيانة</th><th>الحالة</th>@if(auth()->user()->hasPermission('contracts.update')&&$contract->status==='active')<th class="page-no-print">إجراء</th>@endif</tr></thead><tbody>
    @foreach($contract->items as $item)
        @php($effectiveStart=$item->active_from?:$contract->service_start_date)
        @php($activeToday=$item->isActiveOn(today()))
        <tr class="{{ $activeToday?'':'contract-item-stopped' }}">
            <td><strong>{{ $item->product->name }}</strong><small class="contract-item-period">فعال من {{ $effectiveStart->format('Y-m-d') }}@if($item->stopped_at) · الإيقاف من {{ $item->stopped_at->format('Y-m-d') }}@endif</small></td>
            <td>{{ ($item->product?->type==='erp_module'||$item->product?->supports_user_pricing)?'—':$item->quantity }}</td>
            <td>{{ $item->requested_users }}</td><td>{{ $item->included_users+$item->promotional_free_users }}</td><td>{{ $item->billable_users }}</td>
            <td class="money">{{ number_format((float)$item->user_total,2) }}</td><td class="money">{{ number_format((float)$item->line_net,2) }}</td><td class="money">{{ number_format((float)$item->maintenance_annual,2) }}</td>
            <td>
                @if(!$item->stopped_at)<span class="badge badge-success">فعال</span>
                @elseif($item->stopped_at->isFuture())<span class="badge badge-info">يتوقف {{ $item->stopped_at->format('Y-m-d') }}</span>
                @else<span class="badge badge-danger">متوقف</span>@endif
                @if($item->stop_reason)<small class="contract-item-period">{{ $item->stop_reason }}</small>@endif
                @if($item->stopped_at&&$item->stoppedBy)<small class="contract-item-period">بواسطة {{ $item->stoppedBy->name }}</small>@endif
            </td>
            @if(auth()->user()->hasPermission('contracts.update')&&$contract->status==='active')
                <td class="page-no-print">
                    @if(!$item->stopped_at)
                        <details class="inline-correction contract-item-stop-control">
                            <summary class="btn btn-sm btn-outline">إيقاف</summary>
                            <form method="post" action="{{ route('contracts.items.stop',[$contract,$item]) }}" class="inline-correction-form contract-item-stop-form" data-confirm="سيتم استبعاد هذا البند من الاستحقاقات اعتبارًا من التاريخ المحدد. متابعة؟">
                                @csrf
                                <label>تاريخ سريان الإيقاف</label><input class="input" type="date" name="effective_date" min="{{ $effectiveStart->format('Y-m-d') }}" value="{{ today()->format('Y-m-d') }}" required>
                                <label>سبب الإيقاف</label><textarea class="textarea textarea-compact" name="stop_reason" placeholder="اختياري"></textarea>
                                <button class="btn btn-danger" type="submit">تأكيد إيقاف البند</button>
                            </form>
                        </details>
                    @else
                        <span class="muted">مسجل تاريخيًا</span>
                    @endif
                </td>
            @endif
        </tr>
    @endforeach
    </tbody></table></div>
</div>

@if($contract->activity_type==='stations')
<div class="card section-spaced"><div class="card-header"><div><h2>محطات العقد</h2><p>PTS واحد كحد أقصى لكل محطة، والحساسات يمكن توزيعها بعدد أكبر.</p></div></div><div class="station-card-grid">@forelse($contract->stations as $station)<div class="station-mini-card"><div><strong>{{ $station->name }}</strong><span>{{ $station->city ?: 'مدينة غير محددة' }}</span><small>PTS: {{ (int)($station->pivot?->pts_count ?? 0)===1?'نعم':'لا' }} · حساسات: {{ (int)($station->pivot?->sensor_count ?? 0) }}</small></div><div><span>{{ $station->contact_name ?: 'بدون مسؤول' }}</span><small>{{ $station->phone ?: 'بدون هاتف' }}</small></div></div>@empty<div class="empty-state">لم تتم إضافة محطات للعقد بعد.</div>@endforelse</div></div>
@endif

<div class="grid grid-2 section-spaced">
    <div class="card"><div class="card-header"><div><h2>جدول الدفعات</h2><p>قيمة وتاريخ استحقاق كل دفعة من العقد.</p></div></div><div class="table-wrap"><table><thead><tr><th>الدفعة</th><th>تاريخ الاستحقاق</th><th>قبل الضريبة</th><th>الإجمالي</th></tr></thead><tbody>@forelse($contract->installments as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->due_date->format('Y-m-d') }}</td><td class="money">{{ number_format((float)$row->net_amount,2) }}</td><td class="money">{{ number_format((float)$row->total_amount,2) }}</td></tr>@empty<tr><td colspan="4" class="empty-state">لا يوجد جدول دفعات لهذا النوع من العقود.</td></tr>@endforelse</tbody></table></div></div>
    <div class="card"><div class="card-header"><div><h2>ملحقات العقد</h2><p>أي إضافة لاحقة تسجل كملحق مستقل ببنوده ودفعاته.</p></div>@if(auth()->user()->hasPermission('addendums.create')&&$contract->status!=='cancelled')<a class="btn btn-sm btn-primary" href="{{ route('addendums.create',$contract) }}">+ إضافة ملحق</a>@endif</div><div class="table-wrap"><table><thead><tr><th>الملحق</th><th>البداية</th><th>الصافي</th><th>الصيانة</th></tr></thead><tbody>@forelse($contract->addendums as $addendum)<tr><td><a href="{{ route('addendums.show',[$contract,$addendum]) }}">{{ $addendum->number }}</a></td><td>{{ $addendum->service_start_date->format('Y-m-d') }}</td><td class="money">{{ number_format((float)$addendum->net_total,2) }}</td><td class="money">{{ number_format((float)$addendum->maintenance_total,2) }}</td></tr>@empty<tr><td colspan="4" class="empty-state">لا توجد ملحقات.</td></tr>@endforelse</tbody></table></div></div>
</div>

<div class="card section-spaced"><div class="card-header"><div><h2>الاستحقاقات والتحصيل</h2><p>دفعات العقد والصيانة والاشتراكات ودفعات الملحقات؛ سند القبض يختار من هذه الاستحقاقات.</p></div>@if($contract->status!=='cancelled'&&auth()->user()->hasPermission('collections.create'))<a class="btn btn-sm btn-primary" href="{{ route('collections.create',['customer_id'=>$contract->customer_id,'contract_id'=>$contract->id]) }}">+ سند قبض لهذا العقد</a>@endif</div><div class="table-wrap"><table><thead><tr><th>الرقم</th><th>البيان</th><th>التاريخ</th><th>الإجمالي</th><th>المحصل</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>@forelse($contract->receivables as $row)<tr><td>{{ $row->number }}</td><td>{{ $row->name }}</td><td>{{ $row->due_date->format('Y-m-d') }}</td><td class="money">{{ number_format((float)$row->total_amount,2) }}</td><td class="money text-success">{{ number_format((float)$row->collected_amount,2) }}</td><td class="money {{ $row->remaining_amount>0?'text-danger':'' }}">{{ number_format((float)$row->remaining_amount,2) }}</td><td><span class="badge {{ $row->status==='paid'?'badge-success':($row->status==='overdue'?'badge-danger':'badge-info') }}">{{ $row->status }}</span></td></tr>@empty<tr><td colspan="7" class="empty-state">لا توجد استحقاقات.</td></tr>@endforelse</tbody></table></div></div>
@endsection
