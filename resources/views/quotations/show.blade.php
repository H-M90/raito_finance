@extends('layouts.app')
@section('title',$quotation->number)
@section('page-title','عرض المبيعات '.$quotation->number)
@section('page-subtitle',$quotation->customer->name.' · الإصدار V'.$quotation->version_number)
@section('content')
<div class="card quotation-overview-card">
    <div class="card-header">
        <div>
            <h2>{{ $quotation->customer->name }} @if($quotation->customer->segment==='startup')<span class="badge badge-info">شركة ناشئة</span>@endif</h2>
            <p>تاريخ العرض {{ $quotation->quotation_date->format('Y-m-d') }} · الإصدار V{{ $quotation->version_number }}</p>
        </div>
        <div class="inline-actions page-no-print">
            @if(auth()->user()->hasPermission('quotations.convert')&&!$quotation->contract&&$quotation->status==='accepted'&&$quotation->is_current_version)<a class="btn btn-primary" href="{{ route('contracts.create',['quotation_id'=>$quotation->id]) }}">تحويل إلى عقد</a>@endif
            @if($quotation->status==='draft'&&auth()->user()->hasPermission('quotations.update'))<a class="btn btn-light" href="{{ route('quotations.edit',$quotation) }}">تعديل المسودة</a>@endif
            @if($quotation->status!=='converted'&&$quotation->status!=='draft'&&$quotation->is_current_version&&auth()->user()->hasPermission('quotations.update'))<form method="post" action="{{ route('quotations.revise',$quotation) }}">@csrf<button class="btn btn-outline">+ إصدار جديد</button></form>@endif
            <button class="btn btn-outline" data-print-page>طباعة</button>
        </div>
    </div>

    @if($quotation->status!=='draft'&&$quotation->status!=='converted')
        <div class="version-lock-note"><strong>هذه النسخة محفوظة تاريخيًا.</strong><span>لتغيير البنود أو الأسعار أنشئ إصدارًا جديدًا بدل تعديل النسخة التي تم إرسالها.</span></div>
    @endif

    <div class="detail-list">
        <div class="detail-item"><span>النشاط</span><strong>{{ \App\Support\FinanceOptions::activityTypes()[$quotation->activity_type] }}</strong></div>
        <div class="detail-item"><span>الدورية</span><strong>{{ \App\Support\FinanceOptions::billingCycles()[$quotation->billing_cycle] }}</strong></div>
        <div class="detail-item"><span>الحالة</span><strong>{{ \App\Support\FinanceOptions::quotationStatuses()[$quotation->status] }}</strong></div>
        <div class="detail-item"><span>مسؤول المبيعات</span><strong>{{ $quotation->salesOwner?->name ?: 'غير محدد' }}</strong></div>
        @if($quotation->activity_type==='stations')
            <div class="detail-item"><span>عدد المحطات المتوقع</span><strong>{{ (int)$quotation->station_count }}</strong></div>
            <div class="detail-item"><span>الأجهزة في البنود</span><strong>{{ (int)$quotation->pts_count }} PTS · {{ (int)$quotation->sensor_count }} حساس</strong></div>
        @endif
        <div class="detail-item"><span>إجمالي الخصومات</span><strong class="money">{{ number_format((float)$quotation->discount_total,2) }} {{ $quotation->currency }}</strong></div>
        <div class="detail-item"><span>صافي العرض قبل الضريبة</span><strong class="money">{{ number_format((float)$quotation->net_total,2) }} {{ $quotation->currency }}</strong></div>
        <div class="detail-item"><span>الإجمالي شامل الضريبة</span><strong class="money">{{ number_format((float)$quotation->grand_total,2) }} {{ $quotation->currency }}</strong></div>
    </div>

    @if($quotation->intermediary)
        <div class="context-panel section-tight">
            <div><span class="context-label">الوسيط</span><strong>{{ $quotation->intermediary->name }}</strong></div>
            <div><span class="context-label">العمولة</span><strong>{{ $quotation->commission_type==='percentage' ? number_format((float)$quotation->commission_value,2).'%' : 'قيمة ثابتة' }}</strong></div>
            <div><span class="context-label">القيمة المحسوبة</span><strong>{{ number_format((float)$quotation->commission_total,2) }} {{ $quotation->currency }}</strong><small>على صافي العرض بعد الخصومات وقبل الضريبة.</small></div>
        </div>
    @endif

    @if($quotation->pricingOffers->isNotEmpty())<div class="summary-strip section-tight">@foreach($quotation->pricingOffers as $offer)<span class="summary-chip">{{ $offer->name }}</span>@endforeach</div>@endif
</div>

<div class="card section-spaced"><div class="card-header"><div><h2>بنود العرض والمستخدمون/المناديب</h2><p>الموديول بلا كمية؛ الأجهزة والخدمات الكمية فقط تظهر لها كمية.</p></div></div><div class="table-wrap"><table><thead><tr><th>البند</th><th>الكمية</th><th>المستخدمون/المناديب</th><th>مجاني</th><th>مدفوع</th><th>تكلفة المستخدمين</th><th>خصم يدوي</th><th>خصم عروض</th><th>الصافي</th><th>الصيانة</th></tr></thead><tbody>@foreach($quotation->items as $item)<tr><td>{{ $item->product->name }}</td><td>{{ ($item->product?->type==='erp_module'||$item->product?->supports_user_pricing)?'—':$item->quantity }}</td><td>{{ $item->requested_users }}</td><td>{{ $item->included_users+$item->promotional_free_users }}</td><td>{{ $item->billable_users }}</td><td class="money">{{ number_format((float)$item->user_total,2) }}</td><td class="money">{{ number_format((float)$item->discount_value,2) }}</td><td class="money text-success">{{ number_format((float)$item->promotional_discount_value,2) }}</td><td class="money">{{ number_format((float)$item->line_net,2) }}</td><td class="money">{{ number_format((float)$item->maintenance_annual,2) }}</td></tr>@endforeach</tbody></table></div><div class="totals-box"><div class="total-line"><span>إجمالي البنود والمستخدمين</span><strong>{{ number_format((float)$quotation->subtotal,2) }}</strong></div><div class="total-line"><span>إجمالي الخصومات</span><strong>{{ number_format((float)$quotation->discount_total,2) }}</strong></div><div class="total-line"><span>صافي العرض</span><strong>{{ number_format((float)$quotation->net_total,2) }}</strong></div><div class="total-line"><span>الضريبة</span><strong>{{ number_format((float)$quotation->tax_total,2) }}</strong></div><div class="total-line grand"><span>الإجمالي</span><strong>{{ number_format((float)$quotation->grand_total,2) }} {{ $quotation->currency }}</strong></div>@if($quotation->billing_cycle==='one_time')<div class="total-line"><span>الصيانة السنوية</span><strong>{{ number_format((float)$quotation->maintenance_total,2) }}</strong></div>@endif</div></div>

@if(auth()->user()->hasPermission('attachments.download')&&$quotation->attachments->isNotEmpty())
<div class="card section-spaced"><div class="card-header"><h2>مرفقات العرض</h2></div><div class="attachment-list">@foreach($quotation->attachments as $attachment)<a class="attachment-link" href="{{ route('attachments.download',$attachment) }}" target="_blank" rel="noopener"><span>📎</span><span><strong>{{ $attachment->label ?: $attachment->original_name }}</strong><small>{{ $attachment->original_name }}</small></span></a>@endforeach</div></div>
@endif

@if($quotation->status!=='converted'&&auth()->user()->hasPermission('quotations.status'))
<div class="card page-no-print section-spaced"><div class="card-header"><div><h2>تحديث حالة العرض</h2><p>تغيير الحالة لا يغير محتوى النسخة المحفوظة.</p></div></div><form method="post" action="{{ route('quotations.status',$quotation) }}" class="form-grid">@csrf @method('patch')<div class="form-group col-3"><label>الحالة</label><select class="select" name="status">@foreach(\App\Support\FinanceOptions::quotationStatuses() as $v=>$l)@if($v!=='converted')<option value="{{ $v }}" @selected($quotation->status===$v)>{{ $l }}</option>@endif @endforeach</select></div><div class="form-group col-4"><label>سبب الرفض</label><input class="input" name="rejection_reason" value="{{ $quotation->rejection_reason }}"></div><div class="form-group col-3"><label>المنافس</label><input class="input" name="lost_to_competitor" value="{{ $quotation->lost_to_competitor }}"></div><div class="form-group col-2 action-align"><button class="btn btn-primary full-width">تحديث</button></div></form></div>
@endif
@endsection
