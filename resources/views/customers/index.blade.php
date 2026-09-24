@extends('layouts.app')
@section('title','العملاء')
@section('page-title','العملاء')
@section('page-subtitle','ملف موحد للعقود والاستحقاقات والتحصيلات والمتابعة')
@section('content')
<div class="card entity-list-card">
    <div class="card-header">
        <div><h2>قائمة العملاء</h2><p>{{ $customers->total() }} عميل</p></div>
        <div class="card-header-actions">
            @if(auth()->user()->hasPermission('customer-success.dashboard'))
                <a class="btn btn-light" href="{{ route('customer-success.dashboard') }}">قائمة المتابعة اليوم</a>
            @endif
            @if(auth()->user()->hasPermission('customers.create'))
                <a class="btn btn-primary" href="{{ route('customers.create') }}">+ إضافة عميل</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get">
        <div class="form-group search"><label>البحث</label><input class="input" name="q" value="{{ request('q') }}" placeholder="الاسم، الكود، الهاتف، الرقم الضريبي أو السجل التجاري"></div>
        <div class="form-group"><label>التصنيف</label><select class="select" name="segment"><option value="">الكل</option>@foreach($segments as $segment)<option value="{{ $segment }}" @selected(request('segment')===$segment)>{{ $segment === 'startup' ? 'شركة ناشئة' : ($segment === 'standard' ? 'عادي' : $segment) }}</option>@endforeach</select></div>
        <div class="form-group"><label>الحالة التشغيلية</label><select class="select" name="status"><option value="">الكل</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>غير نشط</option></select></div>
        <div class="form-group"><label>المدينة</label><select class="select" name="city"><option value="">كل المدن</option>@foreach($cities as $city)<option value="{{ $city }}" @selected(request('city')===$city)>{{ $city }}</option>@endforeach</select></div>
        <div class="form-group"><label>مسؤول المبيعات</label><select class="select" name="sales_owner_id"><option value="">الكل</option>@foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(request('sales_owner_id')==(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
        <div class="form-group"><label>العقود</label><select class="select" name="contracts"><option value="">الكل</option><option value="yes" @selected(request('contracts')==='yes')>لديه عقود</option><option value="no" @selected(request('contracts')==='no')>بلا عقود</option></select></div>
        @if(auth()->user()->hasPermission('customer-success.view'))
            <div class="form-group"><label>حالة المتابعة</label><select class="select" name="success_status"><option value="">الكل</option>@foreach($successStatuses as $status)<option value="{{ $status->code }}" @selected(request('success_status')===$status->code)>{{ $status->name }}</option>@endforeach</select></div>
        @endif
        <button class="btn btn-secondary">بحث</button><a class="btn btn-light" href="{{ route('customers.index') }}">مسح</a>
    </form>

    <div class="table-wrap entity-table-wrap"><table class="entity-table">
        <thead><tr><th>الكود</th><th>العميل</th><th>التواصل</th><th>العقود</th><th>المحطات</th>@if(auth()->user()->hasPermission('customer-success.view'))<th>حالة المتابعة</th>@endif<th>التصنيف</th><th>الحالة</th><th class="customer-actions-col">إجراءات</th></tr></thead>
        <tbody>
        @forelse($customers as $customer)
            @php
                $profile = null;
                $primaryFlag = null;
                $needsAction = false;
                if (auth()->user()->hasPermission('customer-success.view')) {
                    $profile = $customer->successProfile;
                    $primaryFlag = $customer->attentionFlags
                        ->sortByDesc(fn($flag) => ['critical'=>4,'high'=>3,'medium'=>2,'normal'=>1][$flag->severity] ?? 0)
                        ->first();
                    $needsAction = $primaryFlag || in_array($profile?->attention_level,['critical','high'],true) || $customer->open_success_tasks_count > 0;
                }
            @endphp
            <tr>
                <td>{{ $customer->code }}</td>
                <td><strong>{{ $customer->name }}</strong><div class="muted">{{ collect([$customer->country,$customer->city])->filter()->join(' - ') }}</div></td>
                <td>{{ $customer->contact_name ?: '—' }}<div class="muted">{{ $customer->phone }}</div></td>
                <td>{{ $customer->contracts_count }}</td>
                <td>{{ $customer->stations_count }}</td>
                @if(auth()->user()->hasPermission('customer-success.view'))
                    <td class="customer-followup-cell">
                        @if($profile)
                            <div class="customer-followup-line">
                                <span class="badge {{ $needsAction ? 'badge-warning' : 'badge-success' }}">{{ $profile->lifecycleStatus?->name ?: 'غير محدد' }}</span>
                            </div>
                            <div class="customer-followup-reason">
                                <span>سبب المتابعة:</span>
                                <strong>{{ $primaryFlag?->type?->name ?: ($customer->open_success_tasks_count ? 'متابعة مجدولة' : 'لا يوجد تنبيه حالي') }}</strong>
                            </div>
                        @else
                            <span class="muted">لم يتم تقييم العميل بعد</span>
                        @endif
                    </td>
                @endif
                <td><span class="badge {{ $customer->segment==='startup'?'badge-warning':'badge-info' }}">{{ $customer->segment==='startup'?'شركة ناشئة':($customer->segment==='standard'?'عادي':$customer->segment) }}</span></td>
                <td><span class="badge {{ $customer->status==='active'?'badge-success':'badge-dark' }}">{{ $customer->status==='active'?'نشط':'غير نشط' }}</span></td>
                <td>
                    <div class="customer-row-actions">
                        <a class="icon-action" href="{{ route('customers.show',$customer) }}" title="فتح ملف العميل" aria-label="فتح ملف العميل">
                            <x-ui-icon name="user-round" />
                        </a>
                        @if(auth()->user()->hasPermission('customer-success.update') && $profile)
                            <button class="icon-action" type="button" data-reassess-open="reassess-{{ $customer->id }}" title="إعادة تقييم العميل" aria-label="إعادة تقييم العميل"><x-ui-icon name="refresh-cw" /></button>
                            @include('customers._reassess_dialog', ['dialogId' => 'reassess-'.$customer->id, 'statuses' => $successStatuses])
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ auth()->user()->hasPermission('customer-success.view') ? 9 : 8 }}" class="empty-state">لا توجد بيانات مطابقة.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $customers->links() }}
</div>
@endsection
