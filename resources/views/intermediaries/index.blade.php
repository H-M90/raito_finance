@extends('layouts.app')
@section('title','الوسطاء')
@section('page-title','الوسطاء')
@section('page-subtitle','دليل الوسطاء المستخدم في عروض المبيعات والعقود؛ العمولة تُحدد داخل المستند نفسه')
@section('content')
<div class="entity-stats">
    <div class="entity-stat"><span>إجمالي الوسطاء</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="entity-stat is-success"><span>نشط</span><strong>{{ $stats['active'] }}</strong></div>
    <div class="entity-stat"><span>متوقف</span><strong>{{ $stats['inactive'] }}</strong></div>
</div>

<details id="new-intermediary" class="card master-create-card" @if($errors->any() && old('name')) open @endif>
    <summary class="master-create-summary"><div><strong>+ إضافة وسيط جديد</strong><span>أدخل بيانات التواصل فقط؛ النسبة أو القيمة تحدد في العرض أو العقد.</span></div><span class="btn btn-sm btn-primary">فتح النموذج</span></summary>
    <form method="post" action="{{ route('intermediaries.store') }}" class="master-create-body">@csrf
        <div class="form-grid">
            <div class="form-group col-4"><label class="required">اسم الوسيط</label><input class="input" name="name" value="{{ old('name') }}" required></div>
            <div class="form-group col-3"><label>الهاتف</label><input class="input" name="phone" value="{{ old('phone') }}"></div>
            <div class="form-group col-3"><label>البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email') }}"></div>
            <div class="form-group col-10"><label>ملاحظات</label><textarea class="textarea textarea-compact" name="notes">{{ old('notes') }}</textarea></div>
            <div class="form-group col-2 action-align"><button class="btn btn-primary">حفظ الوسيط</button></div>
        </div>
    </form>
</details>
@endif

<div class="card entity-list-card">
    <div class="card-header entity-list-head">
        <div><h2>دليل الوسطاء</h2><p>{{ $intermediaries->total() }} نتيجة حسب البحث الحالي</p></div>
        <form class="entity-search"><input class="input" name="q" value="{{ request('q') }}" placeholder="ابحث بالاسم أو الهاتف"><button class="btn btn-light">بحث</button>@if(request('q'))<a class="btn btn-light" href="{{ route('intermediaries.index') }}">مسح</a>@endif</form>
    </div>
    <div class="table-wrap"><table class="entity-table"><thead><tr><th>الوسيط</th><th>بيانات التواصل</th><th>الحالة</th><th class="actions-col">الإجراء</th></tr></thead><tbody>
    @forelse($intermediaries as $intermediary)
        <tr>
            <td><div class="entity-cell"><span class="entity-avatar">{{ mb_substr($intermediary->name,0,1) }}</span><div><strong>{{ $intermediary->name }}</strong>@if($intermediary->notes)<small>{{ \Illuminate\Support\Str::limit($intermediary->notes,70) }}</small>@endif</div></div></td>
            <td><div class="contact-stack"><span>{{ $intermediary->phone ?: 'بدون هاتف' }}</span><small>{{ $intermediary->email ?: 'بدون بريد' }}</small></div></td>
            <td><span class="badge {{ $intermediary->is_active?'badge-success':'badge-dark' }}">{{ $intermediary->is_active?'نشط':'متوقف' }}</span></td>
            <td>
            @if(auth()->user()->hasPermission('intermediaries.update'))
                <details class="inline-edit"><summary class="btn btn-sm btn-outline">تعديل</summary>
                    <form method="post" action="{{ route('intermediaries.update',$intermediary) }}" class="inline-edit-panel">@csrf @method('put')
                        <div class="form-grid"><div class="form-group col-4"><label>الاسم</label><input class="input" name="name" value="{{ $intermediary->name }}" required></div><div class="form-group col-3"><label>الهاتف</label><input class="input" name="phone" value="{{ $intermediary->phone }}"></div><div class="form-group col-3"><label>البريد</label><input class="input" type="email" name="email" value="{{ $intermediary->email }}"></div><div class="form-group col-2"><label>الحالة</label><label class="switch-label switch-box"><input type="checkbox" name="is_active" value="1" @checked($intermediary->is_active)> نشط</label></div><div class="form-group col-9"><label>ملاحظات</label><input class="input" name="notes" value="{{ $intermediary->notes }}"></div><div class="form-group col-3 action-align"><button class="btn btn-primary">حفظ التعديل</button></div></div>
                    </form>
                </details>
            @else — @endif
            </td>
        </tr>
    @empty<tr><td colspan="4" class="empty-state">لا يوجد وسطاء مطابقون للبحث.</td></tr>@endforelse
    </tbody></table></div>
    {{ $intermediaries->links() }}
</div>
@endsection
