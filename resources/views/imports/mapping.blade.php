@extends('layouts.app')
@section('title','مطابقة أعمدة الاستيراد')
@section('page-title','مطابقة المنتجات غير المعروفة')
@section('page-subtitle',$batch->original_filename)
@section('content')
<div class="card"><div class="card-header"><div><h2>اختر المنتج المقابل لكل عمود</h2><p>لن يُنشئ النظام منتجاً جديداً؛ حفظ المطابقة يعيد بناء المعاينة قبل الاعتماد.</p></div><a class="btn btn-outline" href="{{ route('imports.show',$batch) }}">العودة للمعاينة</a></div>
@php($headers = $batch->summary['unmapped_product_headers'] ?? [])
@if(empty($headers))<div class="alert alert-info">لا توجد أعمدة منتجات غير مطابقة في هذه الدفعة.</div>@else
<form method="post" action="{{ route('imports.mapping.update',$batch) }}">@csrf @method('put')
<div class="form-grid">@foreach($headers as $header)<div class="form-group col-6"><label>{{ $header }}</label><select class="input" name="mapping[{{ $header }}]"><option value="">اتركه غير مطابق</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($batch->summary['product_mapping'][$header]??null)===$product->code)>{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></div>@endforeach</div>
<button class="btn btn-primary">حفظ المطابقة وإعادة الفحص</button></form>@endif</div>
@endsection
