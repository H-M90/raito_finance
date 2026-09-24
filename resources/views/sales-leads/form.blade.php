@extends('layouts.app')
@php $editing=$lead->exists; @endphp
@section('title',$editing?'تعديل عميل محتمل':'إضافة عميل محتمل')
@section('page-title',$editing?'تعديل العميل المحتمل':'إضافة عميل محتمل')
@section('page-subtitle','بيانات العميل وبداية رحلة المبيعات والمتابعة الأولى')
@section('content')
<form class="card sales-lead-form" method="post" action="{{ $editing?route('sales-leads.update',$lead):route('sales-leads.store') }}">
    @csrf @if($editing) @method('put') @endif
    <div class="card-header"><div><h2>{{ $editing?$lead->company_name:'بيانات العميل المحتمل' }}</h2><p>اكتب أقل بيانات لازمة وعيّن متابعة قادمة حتى لا يسقط العميل من المتابعة.</p></div></div>
    <div class="sales-form-section"><h3>بيانات أساسية</h3><div class="form-grid cols-3">
        <div class="form-group"><label>اسم الشركة *</label><input class="input" name="company_name" required value="{{ old('company_name',$lead->company_name) }}"></div>
        <div class="form-group"><label>اسم المسؤول</label><input class="input" name="contact_name" value="{{ old('contact_name',$lead->contact_name) }}"></div>
        <div class="form-group"><label>الجوال</label><input class="input" name="phone" value="{{ old('phone',$lead->phone) }}"></div>
        <div class="form-group"><label>البريد</label><input class="input" type="email" name="email" value="{{ old('email',$lead->email) }}"></div>
        <div class="form-group"><label>المدينة</label><input class="input" name="city" value="{{ old('city',$lead->city) }}"></div>
        <div class="form-group"><label>القطاع</label><input class="input" name="sector" value="{{ old('sector',$lead->sector) }}" placeholder="مثال: توزيع، دواجن، تصنيع"></div>
        <div class="form-group"><label>المصدر</label><select class="select" name="source"><option value="">—</option>@foreach(\App\Models\SalesLead::SOURCES as $code=>$label)<option value="{{ $code }}" @selected(old('source',$lead->source)===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>المسؤول</label><select class="select" name="owner_id"><option value="">المستخدم الحالي</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string)old('owner_id',$lead->owner_id)===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
        @if(!$editing)<div class="form-group span-3"><label>ملاحظة أولية</label><textarea class="textarea" name="initial_note" rows="3">{{ old('initial_note') }}</textarea></div>@endif
    </div></div>
    <div class="sales-form-section"><h3>حالة المبيعات</h3><div class="form-grid cols-4">
        <div class="form-group"><label>المرحلة</label><select class="select" name="stage">@foreach(\App\Models\SalesLead::STAGES as $code=>$label) @if($code==='won' && !$lead->customer_id) @continue @endif <option value="{{ $code }}" @selected(old('stage',$lead->stage?:'lead')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>أولوية العميل</label><select class="select" name="rating">@foreach(\App\Models\SalesLead::RATINGS as $code=>$label)<option value="{{ $code }}" @selected(old('rating',$lead->rating?:'medium')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>التأهيل</label><select class="select" name="qualification">@foreach(\App\Models\SalesLead::QUALIFICATIONS as $code=>$label)<option value="{{ $code }}" @selected(old('qualification',$lead->qualification?:'evaluating')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>تم الرد؟</label><select class="select" name="responded"><option value="0" @selected(!old('responded',$lead->responded))>لا</option><option value="1" @selected(old('responded',$lead->responded))>نعم</option></select></div>
        <div class="form-group"><label>تمييز العميل المحتمل</label><select class="select" name="favorite"><option value="0" @selected(!old('favorite',$lead->favorite))>عادي</option><option value="1" @selected(old('favorite',$lead->favorite))>مميز</option></select></div>
        <div class="form-group span-3"><label>سبب الفقد أو عدم التأهيل <span class="muted">(عند الحاجة)</span></label><textarea class="textarea" name="lost_reason" rows="2">{{ old('lost_reason',$lead->lost_reason) }}</textarea></div>
    </div></div>
    <div class="sales-form-section is-followup"><h3>المتابعة القادمة</h3><div class="form-grid cols-4">
        <div class="form-group span-2"><label>المطلوب في المتابعة</label><input class="input" name="next_follow_up_title" value="{{ old('next_follow_up_title',$lead->next_follow_up_title?:'متابعة العميل وتحديد الخطوة التالية') }}"></div>
        <div class="form-group"><label>نوع المتابعة</label><select class="select" name="next_follow_up_type">@foreach(\App\Models\SalesLead::FOLLOW_UP_TYPES as $code=>$label)<option value="{{ $code }}" @selected(old('next_follow_up_type',$lead->next_follow_up_type?:'call')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label>الأولوية</label><select class="select" name="next_follow_up_priority">@foreach(\App\Models\SalesLead::PRIORITIES as $code=>$label)<option value="{{ $code }}" @selected(old('next_follow_up_priority',$lead->next_follow_up_priority?:'medium')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group span-2"><label>الموعد</label><input class="input" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at',$lead->next_follow_up_at?->format('Y-m-d\TH:i') ?: now()->addDay()->setTime(10,0)->format('Y-m-d\TH:i')) }}"></div>
    </div></div>
    <div class="form-actions"><a class="btn btn-light" href="{{ $editing?route('sales-leads.show',$lead):route('sales-leads.index') }}">رجوع</a><button class="btn btn-primary">{{ $editing?'حفظ التعديلات':'إنشاء العميل المحتمل' }}</button></div>
</form>
@endsection
