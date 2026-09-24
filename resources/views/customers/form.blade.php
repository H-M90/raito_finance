@extends('layouts.app')
@section('title',$customer->exists?'تعديل العميل':'إضافة عميل')
@section('page-title',$customer->exists?'تعديل بيانات العميل':'إضافة عميل جديد')
@section('page-subtitle','البيانات الأساسية ويمكن استكمالها في أي وقت')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ $customer->exists?route('customers.update',$customer):route('customers.store') }}">
@csrf @if($customer->exists) @method('put') @endif
<div class="card"><div class="form-grid">
<div class="form-group col-6"><label class="required">اسم العميل</label><input class="input" name="name" value="{{ old('name',$customer->name) }}" required></div>

<div class="form-group col-3"><label>الدولة</label><input class="input" name="country" value="{{ old('country',$customer->country) }}"></div>
<div class="form-group col-3"><label>المدينة</label><input class="input" name="city" value="{{ old('city',$customer->city) }}"></div>
<div class="form-group col-6"><label>العنوان</label><input class="input" name="address" value="{{ old('address',$customer->address) }}"></div>
<div class="form-group col-3"><label>السجل التجاري</label><input class="input" name="commercial_registration_no" value="{{ old('commercial_registration_no',$customer->commercial_registration_no) }}"></div>
<div class="form-group col-3"><label>الرقم الضريبي</label><input class="input" name="tax_no" value="{{ old('tax_no',$customer->tax_no) }}"></div>
<div class="form-group col-4"><label>مسؤول التواصل</label><input class="input" name="contact_name" value="{{ old('contact_name',$customer->contact_name) }}"></div>
<div class="form-group col-4"><label>الهاتف</label><input class="input" name="phone" value="{{ old('phone',$customer->phone) }}"></div>
<div class="form-group col-4"><label>البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email',$customer->email) }}"></div>
<x-reference-picker label="مسؤول المبيعات" name="sales_owner_id" wrapper-class="col-4"><option value="">غير محدد</option>@foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(old('sales_owner_id',$customer->sales_owner_id)==$owner->id)>{{ $owner->name }}</option>@endforeach</x-reference-picker>
<div class="form-group col-3"><label>تصنيف العميل</label><select class="select" name="segment"><option value="standard" @selected(old('segment',$customer->segment?:'standard')==='standard')>عميل عادي</option><option value="startup" @selected(old('segment',$customer->segment)==='startup')>شركة ناشئة - خصم 50%</option></select></div><div class="form-group col-3"><label>الحالة</label><select class="select" name="status"><option value="active" @selected(old('status',$customer->status?:'active')==='active')>نشط</option><option value="inactive" @selected(old('status',$customer->status)==='inactive')>غير نشط</option></select></div>
<div class="form-group col-9"><label>سبب الإيقاف</label><input class="input" name="inactive_reason" value="{{ old('inactive_reason',$customer->inactive_reason) }}"></div>
<div class="form-group col-9"><label>ملاحظات</label><textarea class="textarea" name="notes">{{ old('notes',$customer->notes) }}</textarea></div>
<div class="form-group col-3"><label>مرفق</label><input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.csv"><small class="help">اختياري · بحد أقصى 10MB</small></div>
</div></div>
<div class="form-actions"><button class="btn btn-primary">حفظ العميل</button><a class="btn btn-light" href="{{ route('customers.index') }}">إلغاء</a></div>
</form>
@endsection
