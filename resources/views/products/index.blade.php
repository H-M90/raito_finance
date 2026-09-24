@extends('layouts.app')
@section('title','المنتجات والموديولات')
@section('page-title','المنتجات والموديولات')
@section('page-subtitle','الأسعار ونسب الصيانة وتسعير المستخدمين')
@section('content')
<div class="card"><div class="card-header"><div><h2>دليل البنود</h2><p>{{ $products->total() }} بند</p></div>@if(auth()->user()->hasPermission('products.create'))<a class="btn btn-primary" href="{{ route('products.create') }}">+ إضافة بند</a>@endif</div>
<form class="filters" method="get"><div class="form-group search"><label>البحث</label><input class="input" name="q" value="{{ request('q') }}" placeholder="الكود أو اسم البند"></div><button class="btn btn-secondary">بحث</button></form>
<div class="table-wrap"><table><thead><tr><th>الكود</th><th style="min-width:240px">البند</th><th>النوع</th><th>السعر الأساسي</th><th>تسعير المستخدمين</th><th>يعتمد على</th><th>الصيانة</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($products as $product)<tr><td>{{ $product->code }}</td><td style="min-width:240px"><strong>{{ $product->name }}</strong><div class="muted">{{ \App\Support\FinanceOptions::billingCycles()[$product->billing_cycle] ?? $product->billing_cycle }}</div></td><td>{{ \App\Support\FinanceOptions::productTypes()[$product->type] ?? $product->type }}</td><td class="money">{{ number_format((float)$product->default_sale_price,2) }}</td><td>@if($product->supports_user_pricing)<span class="badge badge-info">{{ (int)$product->included_users_one_time }} مجاني</span><div class="muted">رخصة: {{ number_format((float)$product->extra_user_price_one_time,2) }} · شهري: {{ number_format((float)$product->user_price_monthly,2) }} · سنوي: {{ number_format((float)$product->user_price_annual,2) }}</div>@else<span class="muted">غير مطبق</span>@endif</td><td>{{ $product->requiredProduct?->name ?: '—' }}</td><td>{{ number_format((float)$product->default_maintenance_rate,0) }}%</td><td><span class="badge {{ $product->is_active?'badge-success':'badge-dark' }}">{{ $product->is_active?'نشط':'متوقف' }}</span></td><td>@if(auth()->user()->hasPermission('products.update'))<a class="btn btn-sm btn-light" href="{{ route('products.edit',$product) }}">تعديل</a>@endif</td></tr>
@empty<tr><td colspan="9" class="empty-state">لا توجد بنود.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</div>
@endsection
