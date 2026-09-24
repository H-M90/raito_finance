@extends('layouts.app')
@php($editing=$voucher->exists)
@section('title',$editing?'تعديل سند خصم':'سند خصم جديد')
@section('page-title',$editing?'تعديل سند الخصم':'إنشاء سند خصم')
@section('page-subtitle','سند الخصم مرتبط باستحقاق محدد ولا يتجاوز رصيده')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ $editing?route('discount-vouchers.update',$voucher):route('discount-vouchers.store') }}">@csrf @if($editing)@method('put')@endif
<div class="form-section"><div class="form-grid">
<x-reference-picker label="العميل" name="customer_id" wrapper-class="col-4" :required="true" data-remote-url="{{ route('lookup.customers') }}" :create-url="route('customers.create')" create-label="عميل جديد" :can-create="auth()->user()->hasPermission('customers.create')"><option value="">اختر العميل</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id',$selectedCustomer)==$c->id)>{{ $c->name }}</option>@endforeach</x-reference-picker>
<div class="form-group col-2"><label>العملة</label><select class="select" name="currency">@foreach($currencies as $code=>$label)<option value="{{ $code }}" @selected(old('currency',$selectedCurrency)===$code)>{{ $label }}</option>@endforeach</select></div>
<div class="form-group col-3"><label>الاستحقاق</label><select class="select" name="receivable_id" required><option value="">اختر</option>@foreach($receivables as $r)<option value="{{ $r->id }}" @selected(old('receivable_id',$voucher->receivable_id)==$r->id)>{{ $r->name }} · {{ number_format((float)$r->remaining_amount,2) }}</option>@endforeach</select></div>
<div class="form-group col-3"><label>التاريخ</label><input class="input" type="date" name="voucher_date" value="{{ old('voucher_date',$voucher->voucher_date?->format('Y-m-d')??today()->format('Y-m-d')) }}" required></div>
<div class="form-group col-3"><label>القيمة</label><input class="input" type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount',$voucher->amount) }}" required></div>
<div class="form-group col-5"><label>سبب الخصم</label><input class="input" name="reason" value="{{ old('reason',$voucher->reason) }}" required></div>
<div class="form-group col-4"><label>المرفق</label><input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
<div class="form-group col-12"><label>ملاحظات</label><textarea class="textarea" name="notes">{{ old('notes',$voucher->notes) }}</textarea></div>
</div></div><div class="form-actions"><button class="btn btn-primary">{{ $editing?'حفظ التعديل':'تأكيد سند الخصم' }}</button><a class="btn btn-light" href="{{ route('discount-vouchers.index') }}">إلغاء</a></div></form>
@endsection
