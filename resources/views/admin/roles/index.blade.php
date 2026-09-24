@extends('layouts.app')
@section('title','الأدوار والصلاحيات')
@section('page-title','الأدوار والصلاحيات')
@section('page-subtitle','تحكم واضح في صلاحيات كل دور بدون تغيير وظائف النظام')
@section('content')
@php
    $requestedRole = request('role');
    $activeRoleCode = $roles->firstWhere('code', $requestedRole)?->code ?? $roles->first()?->code;
@endphp

<div class="permissions-page" data-permissions-page data-active-role="{{ $activeRoleCode }}">
    <section class="permissions-overview" aria-label="ملخص الصلاحيات">
        <div class="permissions-overview-copy">
            <span class="permissions-kicker">إدارة الوصول</span>
            <h2>الصلاحيات حسب الدور</h2>
            <p>اختر الدور ثم فعّل فقط الشاشات والعمليات التي يحتاجها. جميع أسماء الصلاحيات معروضة بالعربية، بينما الأكواد الداخلية ثابتة ولا تتغير.</p>
        </div>
        <div class="permissions-overview-stats">
            <div><strong>{{ $roles->count() }}</strong><span>أدوار</span></div>
            <div><strong>{{ $permissionTotal }}</strong><span>صلاحية</span></div>
            <div><strong>{{ $permissions->count() }}</strong><span>مجموعات</span></div>
        </div>
    </section>

    <div class="permissions-layout">
        <aside class="permissions-role-panel" aria-label="الأدوار">
            <div class="permissions-role-panel-head">
                <div><strong>الأدوار</strong><span>اختر دورًا للتعديل</span></div>
            </div>
            <div class="permissions-role-list" role="tablist" aria-orientation="vertical">
                @foreach($roles as $role)
                    @php $selectedCount = $role->code === 'admin' ? $permissionTotal : $role->permissions->count(); @endphp
                    <button
                        type="button"
                        class="permissions-role-item {{ $role->code === $activeRoleCode ? 'active' : '' }}"
                        data-role-selector="{{ $role->code }}"
                        role="tab"
                        aria-selected="{{ $role->code === $activeRoleCode ? 'true' : 'false' }}"
                        aria-controls="role-panel-{{ $role->id }}"
                    >
                        <span class="permissions-role-avatar">{{ mb_substr($role->name,0,1) }}</span>
                        <span class="permissions-role-copy">
                            <strong>{{ $role->name }}</strong>
                            <small>{{ $selectedCount }} من {{ $permissionTotal }} صلاحية</small>
                        </span>
                        @if($role->code === 'admin')
                            <span class="permissions-role-state is-admin">كامل</span>
                        @else
                            <span class="permissions-role-state">{{ $selectedCount }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </aside>

        <section class="permissions-editor">
            @foreach($roles as $role)
                @php
                    $isAdmin = $role->code === 'admin';
                    $selectedIds = $isAdmin ? collect($permissions)->flatten(1)->pluck('id') : $role->permissions->pluck('id');
                @endphp
                <form
                    method="post"
                    action="{{ route('admin.roles.update',$role) }}"
                    id="role-panel-{{ $role->id }}"
                    class="permissions-role-form {{ $role->code === $activeRoleCode ? 'active' : '' }}"
                    data-role-panel="{{ $role->code }}"
                    data-total-permissions="{{ $permissionTotal }}"
                    role="tabpanel"
                    @if($role->code !== $activeRoleCode) hidden @endif
                >
                    @csrf
                    @method('put')

                    <header class="permissions-editor-head">
                        <div class="permissions-editor-title">
                            <div class="permissions-editor-avatar">{{ mb_substr($role->name,0,1) }}</div>
                            <div>
                                <span>تعديل الدور</span>
                                <h2>{{ $role->name }}</h2>
                                <p>{{ $isAdmin ? 'مدير النظام يمتلك جميع الصلاحيات تلقائيًا ولا يمكن تقليلها.' : 'اختر الصلاحيات المطلوبة لهذا الدور ثم احفظ التغييرات.' }}</p>
                            </div>
                        </div>
                        <div class="permissions-editor-count">
                            <strong data-role-selected-count>{{ $selectedIds->count() }}</strong>
                            <span>من {{ $permissionTotal }} صلاحية</span>
                        </div>
                    </header>

                    @if($isAdmin)
                        <div class="permissions-admin-note"><span class="permissions-admin-mark"><x-ui-icon name="check" /></span><div><strong>صلاحيات كاملة</strong><p>دور مدير النظام يصل تلقائيًا إلى جميع الشاشات والعمليات، لذلك لا يمكن تعديل اختياراته.</p></div></div>
                    @endif

                    <div class="permissions-controls">
                        <div class="permissions-role-name">
                            <label for="role-name-{{ $role->id }}">اسم الدور</label>
                            <input id="role-name-{{ $role->id }}" class="input" name="name" value="{{ $role->name }}" {{ $isAdmin ? 'readonly' : '' }}>
                        </div>
                        <div class="permissions-search-wrap">
                            <label for="permission-search-{{ $role->id }}">بحث في الصلاحيات</label>
                            <div class="permissions-search-box">
                                <span aria-hidden="true"></span>
                                <input id="permission-search-{{ $role->id }}" type="search" placeholder="مثال: العقود، إضافة عميل، التقارير..." data-permission-search autocomplete="off">
                                <kbd>/</kbd>
                            </div>
                        </div>
                        @unless($isAdmin)
                            <div class="permissions-bulk-actions">
                                <span>تحديد سريع</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-light" data-select-visible>تحديد الظاهر</button>
                                    <button type="button" class="btn btn-sm btn-light" data-clear-visible>إلغاء الظاهر</button>
                                </div>
                            </div>
                        @endunless
                    </div>

                    <div class="permissions-no-results" data-permission-empty hidden>لا توجد صلاحيات مطابقة لعبارة البحث.</div>

                    <div class="permissions-groups" data-permission-groups>
                        @foreach($permissions as $group => $items)
                            @php
                                $groupMeta = $permissionCatalog[$group] ?? null;
                                $groupLabel = $groupMeta['label'] ?? $group;
                                $groupDescription = $groupMeta['description'] ?? 'صلاحيات هذه المجموعة.';
                                $groupSelected = $isAdmin ? $items->count() : $items->whereIn('id',$selectedIds)->count();
                            @endphp
                            <details class="permission-group-card" data-permission-group data-search-text="{{ mb_strtolower($groupLabel.' '.$groupDescription) }}" open>
                                <summary>
                                    <div class="permission-group-heading">
                                        <span class="permission-group-marker" aria-hidden="true"></span>
                                        <div><strong>{{ $groupLabel }}</strong><small>{{ $groupDescription }}</small></div>
                                    </div>
                                    <div class="permission-group-summary">
                                        <span><b data-group-selected>{{ $groupSelected }}</b>/{{ $items->count() }}</span>
                                        @unless($isAdmin)
                                            <label class="permission-group-toggle">
                                                <input type="checkbox" data-group-toggle @checked($groupSelected === $items->count())>
                                                <span>الكل</span>
                                            </label>
                                        @endunless
                                        <i aria-hidden="true"></i>
                                    </div>
                                </summary>
                                <div class="permission-grid">
                                    @foreach($items as $permission)
                                        @php
                                            $definition = $groupMeta['permissions'][$permission->code] ?? null;
                                            $permissionDescription = $definition[1] ?? null;
                                            $checked = $isAdmin || $selectedIds->contains($permission->id);
                                            $searchText = mb_strtolower($permission->name.' '.($permissionDescription ?? '').' '.$groupLabel);
                                        @endphp
                                        <label class="permission-option {{ $checked ? 'is-selected' : '' }}" data-permission-option data-search-text="{{ $searchText }}">
                                            <input
                                                type="checkbox"
                                                name="permission_ids[]"
                                                value="{{ $permission->id }}"
                                                @checked($checked)
                                                @disabled($isAdmin)
                                            >
                                            <span class="permission-option-copy">
                                                <strong>{{ $permission->name }}</strong>
                                                @if($permissionDescription)<small>{{ $permissionDescription }}</small>@endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <footer class="permissions-savebar">
                        <div><strong>{{ $role->name }}</strong><span><b data-role-selected-count-footer>{{ $selectedIds->count() }}</b> صلاحية مفعلة</span></div>
                        @if($isAdmin)
                            <span class="badge badge-info">جميع الصلاحيات مفعلة</span>
                        @else
                            <button class="btn btn-primary" type="submit">حفظ صلاحيات الدور</button>
                        @endif
                    </footer>
                </form>
            @endforeach
        </section>
    </div>
</div>
@endsection
