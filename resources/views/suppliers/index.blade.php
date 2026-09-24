@extends('layouts.app')
@section('title','الموردون')
@section('page-title','الموردون')
@section('page-subtitle','دليل الموردين المستخدم في المشتريات وتوزيع تكاليف العقود')
@section('content')
<div class="entity-stats">
    <div class="entity-stat"><span>إجمالي الموردين</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="entity-stat is-success"><span>نشط</span><strong>{{ $stats['active'] }}</strong></div>
    <div class="entity-stat"><span>متوقف</span><strong>{{ $stats['inactive'] }}</strong></div>
</div>

@if(auth()->user()->hasPermission('suppliers.create'))
<details id="new-supplier" class="card master-create-card" @if($errors->any() && old('name')) open @endif>
    <summary class="master-create-summary"><div><strong>+ إضافة مورد جديد</strong><span>أدخل البيانات الأساسية فقط ويمكن تحديثها لاحقًا.</span></div><span class="btn btn-sm btn-primary">فتح النموذج</span></summary>
    <form method="post" action="{{ route('suppliers.store') }}" class="master-create-body">@csrf
        <div class="form-grid">
            <div class="form-group col-4"><label class="required">اسم المورد</label><input class="input" name="name" value="{{ old('name') }}" required autofocus></div>
            <div class="form-group col-3"><label>الهاتف</label><input class="input" name="phone" value="{{ old('phone') }}"></div>
            <div class="form-group col-3"><label>البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email') }}"></div>
            <div class="form-group col-2"><label>الحالة</label><label class="switch-label switch-box"><input type="checkbox" name="is_active" value="1" checked> نشط</label></div>
            <div class="form-group col-10"><label>ملاحظات</label><textarea class="textarea textarea-compact" name="notes">{{ old('notes') }}</textarea></div>
            <div class="form-group col-2 action-align"><button class="btn btn-primary">حفظ المورد</button></div>
        </div>
    </form>
</details>
@endif

<div class="card entity-list-card">
    <div class="card-header entity-list-head">
        <div><h2>دليل الموردين</h2><p>{{ $suppliers->total() }} نتيجة حسب البحث الحالي</p></div>
        <form class="entity-search"><input class="input" name="q" value="{{ request('q') }}" placeholder="ابحث بالاسم أو الهاتف أو البريد"><button class="btn btn-light">بحث</button>@if(request('q'))<a class="btn btn-light" href="{{ route('suppliers.index') }}">مسح</a>@endif</form>
    </div>
    <div class="table-wrap"><table class="entity-table"><thead><tr><th>المورد</th><th>بيانات التواصل</th><th>الحالة</th><th class="actions-col">الإجراء</th></tr></thead><tbody>
    @forelse($suppliers as $supplier)
        <tr>
            <td><div class="entity-cell"><span class="entity-avatar">{{ mb_substr($supplier->name,0,1) }}</span><div><strong>{{ $supplier->name }}</strong>@if($supplier->notes)<small>{{ \Illuminate\Support\Str::limit($supplier->notes,70) }}</small>@endif</div></div></td>
            <td><div class="contact-stack"><span>{{ $supplier->phone ?: 'بدون هاتف' }}</span><small>{{ $supplier->email ?: 'بدون بريد' }}</small></div></td>
            <td><span class="badge {{ $supplier->is_active?'badge-success':'badge-dark' }}">{{ $supplier->is_active?'نشط':'متوقف' }}</span></td>
            <td>
            @if(auth()->user()->hasPermission('suppliers.update'))
                <details class="inline-edit"><summary class="btn btn-sm btn-outline">تعديل</summary>
                    <form method="post" action="{{ route('suppliers.update',$supplier) }}" class="inline-edit-panel">@csrf @method('put')
                        <div class="form-grid"><div class="form-group col-4"><label>الاسم</label><input class="input" name="name" value="{{ $supplier->name }}" required></div><div class="form-group col-3"><label>الهاتف</label><input class="input" name="phone" value="{{ $supplier->phone }}"></div><div class="form-group col-3"><label>البريد</label><input class="input" type="email" name="email" value="{{ $supplier->email }}"></div><div class="form-group col-2"><label>الحالة</label><label class="switch-label switch-box"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> نشط</label></div><div class="form-group col-9"><label>ملاحظات</label><input class="input" name="notes" value="{{ $supplier->notes }}"></div><div class="form-group col-3 action-align"><button class="btn btn-primary">حفظ التعديل</button></div></div>
                    </form>
                </details>
            @else — @endif
            </td>
        </tr>
    @empty<tr><td colspan="4" class="empty-state">لا يوجد موردون مطابقون للبحث.</td></tr>@endforelse
    </tbody></table></div>
    {{ $suppliers->links() }}
</div>
@endsection
