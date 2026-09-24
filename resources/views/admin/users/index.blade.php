@extends('layouts.app')
@section('title','المستخدمون')
@section('page-title','المستخدمون')
@section('page-subtitle','إدارة حسابات المستخدمين وأدوارهم وحالة الوصول للنظام')
@section('content')
@php
    $canCreate = auth()->user()->hasPermission('users.create');
    $canUpdate = auth()->user()->hasPermission('users.update');
    $hasFilters = request()->filled('q') || request()->filled('role_id') || request()->filled('status');
    $validationContext = old('_user_form');
@endphp

<div
    class="users-page"
    data-users-page
    data-validation-context="{{ $validationContext }}"
    data-validation-user-id="{{ old('_user_id') }}"
    data-old-name="{{ old('name') }}"
    data-old-email="{{ old('email') }}"
    data-old-role-id="{{ old('role_id') }}"
    data-old-active="{{ old('is_active') }}"
>
    <section class="users-hero">
        <div class="users-hero-copy">
            <span class="users-kicker">الإدارة والأمان</span>
            <h2>إدارة المستخدمين</h2>
            <p>أنشئ حسابات الفريق، اربط كل مستخدم بدوره المناسب، وأوقف الوصول أو أعد تفعيله بدون حذف الحساب.</p>
        </div>
        @if($canCreate)
            <button class="btn btn-primary users-create-button" type="button" data-modal-open="#user-create-modal">
                <x-ui-icon name="plus" />
                إضافة مستخدم
            </button>
        @endif
    </section>

    <section class="users-summary" aria-label="ملخص المستخدمين">
        <div class="users-summary-card">
            <span class="users-summary-mark total" aria-hidden="true"></span>
            <div><strong>{{ number_format($stats['total']) }}</strong><span>إجمالي المستخدمين</span></div>
        </div>
        <div class="users-summary-card">
            <span class="users-summary-mark active" aria-hidden="true"></span>
            <div><strong>{{ number_format($stats['active']) }}</strong><span>مستخدمون نشطون</span></div>
        </div>
        <div class="users-summary-card">
            <span class="users-summary-mark inactive" aria-hidden="true"></span>
            <div><strong>{{ number_format($stats['inactive']) }}</strong><span>حسابات موقوفة</span></div>
        </div>
        <div class="users-summary-card">
            <span class="users-summary-mark roles" aria-hidden="true"></span>
            <div><strong>{{ number_format($stats['roles_used']) }}</strong><span>أدوار مستخدمة</span></div>
        </div>
    </section>

    <section class="users-workspace card">
        <header class="users-workspace-head">
            <div>
                <h2>قائمة المستخدمين</h2>
                <p>ابحث أو صفِّ القائمة ثم افتح المستخدم المطلوب لتعديل بياناته.</p>
            </div>
            <span class="users-result-count">{{ number_format($users->total()) }} مستخدم</span>
        </header>

        <form class="users-filters" method="get" action="{{ route('admin.users.index') }}">
            <label class="users-search-field">
                <span>بحث</span>
                <div class="users-search-box">
                    <x-ui-icon name="search" class="users-search-icon" />
                    <input name="q" value="{{ request('q') }}" placeholder="الاسم أو البريد الإلكتروني..." autocomplete="off">
                </div>
            </label>

            <label>
                <span>الدور</span>
                <select class="select" name="role_id">
                    <option value="">كل الأدوار</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected((string)request('role_id') === (string)$role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>الحالة</span>
                <select class="select" name="status">
                    <option value="">كل الحالات</option>
                    <option value="active" @selected(request('status') === 'active')>نشط</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>موقوف</option>
                </select>
            </label>

            <div class="users-filter-actions">
                <button class="btn btn-secondary" type="submit">تطبيق</button>
                @if($hasFilters)<a class="btn btn-light" href="{{ route('admin.users.index') }}">مسح</a>@endif
            </div>
        </form>

        <div class="users-table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>تاريخ الإضافة</th>
                        @if($canUpdate)<th class="users-actions-col">الإجراء</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="users-identity">
                                    <span class="users-avatar">{{ mb_substr($user->name,0,1) }}</span>
                                    <div>
                                        <strong>{{ $user->name }}</strong>
                                        <small>{{ $user->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($user->role)
                                    <span class="users-role-badge"><span aria-hidden="true"></span>{{ $user->role->name }}</span>
                                @else
                                    <span class="badge badge-warning">بدون دور</span>
                                @endif
                            </td>
                            <td>
                                <span class="users-status {{ $user->is_active ? 'is-active' : 'is-inactive' }}">
                                    <i aria-hidden="true"></i>
                                    {{ $user->is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td>
                                <div class="users-date">
                                    <strong>{{ optional($user->created_at)->format('Y-m-d') ?: '—' }}</strong>
                                    <small>{{ optional($user->created_at)->format('H:i') ?: '' }}</small>
                                </div>
                            </td>
                            @if($canUpdate)
                                <td class="users-actions-col">
                                    <button
                                        class="users-edit-button"
                                        type="button"
                                        data-user-edit
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-email="{{ $user->email }}"
                                        data-user-role-id="{{ $user->role_id }}"
                                        data-user-active="{{ $user->is_active ? '1' : '0' }}"
                                        data-user-update-url="{{ route('admin.users.update',$user) }}"
                                        aria-label="تعديل المستخدم {{ $user->name }}"
                                    >
                                        <x-ui-icon name="pencil" class="users-edit-icon" />
                                        تعديل
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canUpdate ? 5 : 4 }}"><div class="users-empty"><strong>لا توجد نتائج</strong><span>جرّب تعديل البحث أو الفلاتر الحالية.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="pagination-bar users-pagination">
                <p class="pagination-summary">عرض {{ $users->firstItem() }}–{{ $users->lastItem() }} من {{ $users->total() }} مستخدم</p>
                {{ $users->links() }}
            </div>
        @endif
    </section>
</div>
@endsection

@push('modals')
@if($canCreate)
<div class="modal user-form-modal" id="user-create-modal" aria-hidden="true">
    <div class="modal-card users-modal-card" role="dialog" aria-modal="true" aria-labelledby="user-create-title">
        <form method="post" action="{{ route('admin.users.store') }}" data-user-create-form>
            @csrf
            <input type="hidden" name="_user_form" value="create">
            <div class="modal-head users-modal-head">
                <div class="users-modal-title">
                    <span class="users-modal-avatar"><x-ui-icon name="user-round-plus" /></span>
                    <div><span>حساب جديد</span><h3 id="user-create-title">إضافة مستخدم</h3><p>أدخل بيانات الدخول وحدد الدور وحالة الحساب.</p></div>
                </div>
                <button class="icon-button" type="button" data-modal-close aria-label="إغلاق"><x-ui-icon name="x" /></button>
            </div>
            <div class="modal-body users-modal-body">
                <div class="users-form-grid">
                    <label class="users-form-field span-2"><span>اسم المستخدم <b>*</b></span><input class="input" name="name" value="{{ old('_user_form') === 'create' ? old('name') : '' }}" required autocomplete="name"></label>
                    <label class="users-form-field span-2"><span>البريد الإلكتروني <b>*</b></span><input class="input" type="email" name="email" value="{{ old('_user_form') === 'create' ? old('email') : '' }}" required autocomplete="email" dir="ltr"></label>
                    <label class="users-form-field"><span>الدور <b>*</b></span><select class="select" name="role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('_user_form') === 'create' && (string)old('role_id') === (string)$role->id)>{{ $role->name }}</option>@endforeach</select><small>الدور هو الذي يحدد صلاحيات الوصول.</small></label>
                    <label class="users-form-field"><span>كلمة المرور <b>*</b></span><input class="input" type="password" name="password" minlength="8" required autocomplete="new-password" dir="ltr"><small>8 أحرف على الأقل.</small></label>
                </div>
                <label class="users-active-control">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('_user_form') === 'create' ? old('is_active','1') : true)>
                    <span class="users-switch" aria-hidden="true"></span>
                    <span><strong>الحساب نشط</strong><small>يسمح للمستخدم بتسجيل الدخول واستخدام صلاحيات دوره.</small></span>
                </label>
            </div>
            <div class="modal-foot users-modal-foot"><button class="btn btn-light" type="button" data-modal-close>إلغاء</button><button class="btn btn-primary" type="submit">إضافة المستخدم</button></div>
        </form>
    </div>
</div>
@endif

@if($canUpdate)
<div class="modal user-form-modal" id="user-edit-modal" aria-hidden="true">
    <div class="modal-card users-modal-card" role="dialog" aria-modal="true" aria-labelledby="user-edit-title">
        <form method="post" action="" id="user-edit-form">
            @csrf
            @method('put')
            <input type="hidden" name="_user_form" value="edit">
            <input type="hidden" name="_user_id" value="" data-edit-user-id>
            <div class="modal-head users-modal-head">
                <div class="users-modal-title">
                    <span class="users-modal-avatar" data-edit-user-initial>م</span>
                    <div><span>بيانات المستخدم</span><h3 id="user-edit-title">تعديل المستخدم</h3><p data-edit-user-caption>حدّث البيانات أو الدور أو حالة الحساب.</p></div>
                </div>
                <button class="icon-button" type="button" data-modal-close aria-label="إغلاق"><x-ui-icon name="x" /></button>
            </div>
            <div class="modal-body users-modal-body">
                <div class="users-form-grid">
                    <label class="users-form-field span-2"><span>اسم المستخدم <b>*</b></span><input class="input" name="name" data-edit-user-name required autocomplete="name"></label>
                    <label class="users-form-field span-2"><span>البريد الإلكتروني <b>*</b></span><input class="input" type="email" name="email" data-edit-user-email required autocomplete="email" dir="ltr"></label>
                    <label class="users-form-field"><span>الدور <b>*</b></span><select class="select" name="role_id" data-edit-user-role required>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select><small>تغيير الدور يغيّر الصلاحيات الممنوحة للمستخدم.</small></label>
                    <label class="users-form-field"><span>كلمة مرور جديدة</span><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" dir="ltr" placeholder="اتركها فارغة بدون تغيير"><small>اكتبها فقط لو عايز تغيّر كلمة المرور.</small></label>
                </div>
                <label class="users-active-control">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" data-edit-user-active>
                    <span class="users-switch" aria-hidden="true"></span>
                    <span><strong>الحساب نشط</strong><small>إيقاف الحساب يمنع تسجيل الدخول بدون حذف المستخدم أو تاريخه.</small></span>
                </label>
            </div>
            <div class="modal-foot users-modal-foot"><button class="btn btn-light" type="button" data-modal-close>إلغاء</button><button class="btn btn-primary" type="submit">حفظ التعديلات</button></div>
        </form>
    </div>
</div>
@endif
@endpush
