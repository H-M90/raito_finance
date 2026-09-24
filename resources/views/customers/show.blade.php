@extends('layouts.app')
@section('title',$customer->name)
@section('page-title','بروفايل العميل')
@section('page-subtitle','ملف العميل والمتابعة والماليات في شاشة واحدة')
@section('content')
@php
    $latestContract = $customer->contracts->firstWhere('status','active') ?: $customer->contracts->first();
    $location = collect([$customer->city,$customer->country])->filter()->join('، ');
    $activityLabel = $latestContract ? (\App\Support\FinanceOptions::activityTypes()[$latestContract->activity_type] ?? $latestContract->activity_type) : null;
    $customerInitial = mb_substr(trim($customer->name),0,1);
@endphp

<div class="customer-profile-page representative-profile">
    <section class="cpf-hero cpf-enter">
        <div class="cpf-hero-main">
            <div class="cpf-avatar" aria-hidden="true">{{ $customerInitial }}</div>
            <div class="cpf-identity">
                <div class="cpf-pills">
                    <span class="cpf-pill {{ $customer->status==='active'?'is-active':'is-inactive' }}">{{ $customer->status==='active'?'عميل نشط':'عميل غير نشط' }}</span>
                    @if($customer->segment==='startup')<span class="cpf-pill is-startup">شركة ناشئة</span>@endif
                </div>
                <h2>{{ $customer->name }}</h2>
                <div class="cpf-meta">
                    @if($activityLabel)<span>{{ $activityLabel }}</span>@endif
                    @if($location)<span>{{ $location }}</span>@endif
                    <span>رقم العميل: {{ $customer->code }}</span>
                </div>
            </div>
            <div class="cpf-hero-stats">
                <div><span>العقود</span><strong>{{ $customer->contracts_count }}</strong></div>
                <div><span>العروض</span><strong>{{ $customer->quotations_count }}</strong></div>
                <div><span>المحطات</span><strong>{{ $customer->stations_count }}</strong></div>
            </div>
        </div>
        <div class="cpf-contact-strip">
            <div><span>مسؤول التواصل</span><strong>{{ $customer->contact_name ?: 'غير محدد' }}</strong></div>
            <div><span>الهاتف</span><strong dir="ltr">{{ $customer->phone ?: '—' }}</strong></div>
            <div><span>البريد</span><strong dir="ltr">{{ $customer->email ?: '—' }}</strong></div>
            <div><span>آخر عقد</span><strong>{{ $latestContract?->number ?: 'لا يوجد عقد' }}</strong></div>
        </div>
    </section>

    <section class="cpf-actions cpf-enter cpf-delay-1" aria-label="إجراءات العميل">
        @if(auth()->user()->hasPermission('customer-success.view'))
            <a class="cpf-action is-primary" href="#customer-success"><span>متابعة العميل</span></a>
        @endif
        @if(auth()->user()->hasPermission('reports.statements'))
            <a class="cpf-action" href="{{ route('customers.statement',$customer) }}"><span>كشف الحساب</span></a>
        @endif
        <a class="cpf-action" href="{{ route('customers.history',$customer) }}"><span>التاريخ الكامل</span></a>
        @if(auth()->user()->hasPermission('quotations.create'))
            <a class="cpf-action" href="{{ route('quotations.create',['customer_id'=>$customer->id]) }}"><span>عرض جديد</span></a>
        @endif
        @if(auth()->user()->hasPermission('contracts.create'))
            <a class="cpf-action" href="{{ route('contracts.create',['customer_id'=>$customer->id]) }}"><span>عقد جديد</span></a>
        @endif
        @if(auth()->user()->hasPermission('customers.update'))
            <a class="cpf-profile-action" href="{{ route('customers.edit',$customer) }}" title="تعديل بيانات العميل" aria-label="تعديل بيانات العميل">تعديل</a>
        @endif
    </section>

    @if(auth()->user()->hasPermission('sales-leads.view') && $customer->relationLoaded('sourceSalesLead') && $customer->sourceSalesLead)
        @php $sourceLead = $customer->sourceSalesLead; @endphp
        <section class="cpf-section cpf-enter cpf-delay-2 customer-sales-origin">
            <div class="cpf-section-head">
                <div><span>قبل التعاقد</span><h3>رحلة المبيعات التي سبقت التحويل</h3></div>
                <a href="{{ route('sales-leads.show',$sourceLead) }}">عرض الرحلة كاملة</a>
            </div>
            <div class="customer-sales-origin-grid">
                <div><span>رقم العميل المحتمل</span><strong>{{ $sourceLead->code }}</strong></div>
                <div><span>المصدر</span><strong>{{ $sourceLead->sourceLabel() }}</strong></div>
                <div><span>التقييم قبل البيع</span><strong>{{ $sourceLead->ratingLabel() }}</strong></div>
                <div><span>تاريخ التحويل</span><strong>{{ $sourceLead->converted_at?->format('Y-m-d H:i') ?: '—' }}</strong></div>
            </div>
        </section>
    @endif

    @if(auth()->user()->hasPermission('customer-success.view') && $successData)
        <div class="cpf-enter cpf-delay-2">
            @include('customers._success')
        </div>
    @endif

    <section class="cpf-section cpf-enter cpf-delay-2">
        <div class="cpf-section-head">
            <div><span>الملف الأساسي</span><h3>بيانات العميل</h3></div>
        </div>
        <div class="cpf-info-grid">
            <div><span>السجل التجاري</span><strong>{{ $customer->commercial_registration_no ?: '—' }}</strong></div>
            <div><span>الرقم الضريبي</span><strong>{{ $customer->tax_no ?: '—' }}</strong></div>
            <div><span>الدولة والمدينة</span><strong>{{ $location ?: '—' }}</strong></div>
            <div><span>التصنيف</span><strong>{{ $customer->segment==='startup'?'شركة ناشئة':'قياسي' }}</strong></div>
            <div class="is-wide"><span>العنوان</span><strong>{{ $customer->address ?: '—' }}</strong></div>
        </div>
    </section>

    @if($financeSummary->isNotEmpty())
        <section class="cpf-section cpf-enter cpf-delay-3">
            <div class="cpf-section-head">
                <div><span>الماليات</span><h3>الملخص المالي حسب العملة</h3></div>
                @if(auth()->user()->hasPermission('collections.create'))<a class="btn btn-sm btn-primary" href="{{ route('collections.create',['customer_id'=>$customer->id]) }}">تسجيل تحصيل</a>@endif
            </div>
            <div class="cpf-finance-grid">
                @foreach($financeSummary as $row)
                    <article class="cpf-finance-card">
                        <div class="cpf-finance-card-head"><strong>{{ $row->currency }}</strong><span>{{ $row->contracts_count }} عقد</span></div>
                        <div class="cpf-finance-metrics">
                            <div><span>قيمة العقود</span><strong>{{ number_format($row->contract_net,2) }}</strong></div>
                            <div><span>المحصل</span><strong class="is-good">{{ number_format($row->collected,2) }}</strong></div>
                            <div><span>المتبقي</span><strong>{{ number_format($row->outstanding,2) }}</strong></div>
                            <div><span>المتأخر</span><strong class="{{ $row->overdue>0?'is-danger':'' }}">{{ number_format($row->overdue,2) }}</strong></div>
                        </div>
                        <div class="cpf-cost-line"><span>المشتريات {{ number_format($row->purchases,2) }}</span><span>المصروفات {{ number_format($row->expenses,2) }}</span><span>العمولات {{ number_format($row->commissions,2) }}</span></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div class="cpf-grid-2 cpf-enter cpf-delay-3">
        <section class="cpf-section">
            <div class="cpf-section-head"><div><span>التعاقدات</span><h3>آخر العقود</h3></div></div>
            <div class="cpf-list">
                @forelse($customer->contracts as $contract)
                    <a class="cpf-list-row" href="{{ route('contracts.show',$contract) }}">
                        <div><strong>{{ $contract->number }}</strong><span>{{ \App\Support\FinanceOptions::activityTypes()[$contract->activity_type]??$contract->activity_type }} · {{ $contract->service_start_date->format('Y-m-d') }}</span></div>
                        <b>{{ number_format((float)$contract->grand_total,2) }} {{ $contract->currency }}</b>
                    </a>
                @empty<div class="cpf-empty">لا توجد عقود.</div>@endforelse
            </div>
        </section>

        <section class="cpf-section">
            <div class="cpf-section-head"><div><span>التحصيل</span><h3>الاستحقاقات المفتوحة</h3></div></div>
            <div class="cpf-list">
                @forelse($customer->receivables as $row)
                    <div class="cpf-list-row is-static">
                        <div><strong>{{ $row->name }}</strong><span>الاستحقاق {{ $row->due_date->format('Y-m-d') }}</span></div>
                        <b class="{{ $row->status==='overdue'?'is-danger':'' }}">{{ number_format((float)$row->remaining_amount,2) }} {{ $row->currency }}</b>
                    </div>
                @empty<div class="cpf-empty">لا توجد استحقاقات مفتوحة.</div>@endforelse
            </div>
        </section>
    </div>

    <div class="cpf-grid-2 cpf-enter cpf-delay-3">
        <section class="cpf-section">
            <div class="cpf-section-head"><div><span>المبيعات</span><h3>آخر عروض المبيعات</h3></div></div>
            <div class="cpf-list">
                @forelse($customer->quotations as $q)
                    <a class="cpf-list-row" href="{{ route('quotations.show',$q) }}">
                        <div><strong>{{ $q->number }}</strong><span>{{ \App\Support\FinanceOptions::quotationStatuses()[$q->status]??$q->status }}</span></div>
                        <b>{{ number_format((float)$q->grand_total,2) }} {{ $q->currency }}</b>
                    </a>
                @empty<div class="cpf-empty">لا توجد عروض.</div>@endforelse
            </div>
        </section>

        <section class="cpf-section">
            <div class="cpf-section-head"><div><span>السجل</span><h3>آخر الأحداث</h3></div><a href="{{ route('customers.history',$customer) }}">عرض الكل</a></div>
            <div class="cpf-timeline">
                @forelse($customer->timelineEvents as $event)
                    <div class="cpf-event"><i></i><div><strong>{{ $event->title }}</strong><span>{{ $event->description }}</span><small>{{ $event->event_at?->format('Y-m-d H:i') }}</small></div>@if($event->url)<a href="{{ $event->url }}">فتح</a>@endif</div>
                @empty<div class="cpf-empty">لا توجد أحداث بعد.</div>@endforelse
            </div>
        </section>
    </div>

    <section class="cpf-section cpf-enter cpf-delay-3">
        <div class="cpf-section-head"><div><span>التشغيل</span><h3>المحطات والفروع</h3></div></div>
        <div class="cpf-list cpf-branches">
            @forelse($customer->stations as $station)
                <div class="cpf-list-row is-static"><div><strong>{{ $station->name }}</strong><span>{{ $station->city ?: 'بدون مدينة' }} · {{ $station->contact_name ?: 'لا يوجد مسؤول' }} · {{ $station->phone ?: 'بدون هاتف' }}</span></div><span class="cpf-state {{ $station->is_active?'is-green':'is-gray' }}">{{ $station->is_active?'نشطة':'متوقفة' }}</span></div>
            @empty<div class="cpf-empty">لا توجد محطات لهذا العميل.</div>@endforelse
        </div>
    </section>

    @if($customer->attachments->isNotEmpty())
        <section class="cpf-section cpf-enter cpf-delay-3">
            <div class="cpf-section-head"><div><span>المرفقات</span><h3>ملفات العميل</h3></div></div>
            <div class="cpf-attachments">
                @foreach($customer->attachments as $attachment)
                    @if(auth()->user()->hasPermission('attachments.download'))
                        <a href="{{ route('attachments.download',$attachment) }}"><span class="cpf-file-mark" aria-hidden="true"></span><strong>{{ $attachment->label ?: $attachment->original_name }}</strong></a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
