<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Raito Finance')</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-5-theme.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ @filemtime(public_path('assets/app.css')) ?: 1 }}">
</head>
<body class="raito-ui">
<a class="skip-link" href="#main-content">انتقل إلى المحتوى</a>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">R</div>
            <div class="brand-copy"><strong>Raito Finance</strong><small>مساحة العمل المالية</small></div>
            <button type="button" class="icon-button mobile-only sidebar-close" data-sidebar-toggle aria-label="إغلاق القائمة" aria-controls="sidebar"><x-ui-icon name="x"/></button>
        </div>

        <nav class="nav-list" aria-label="القائمة الرئيسية">
            @if(auth()->user()->hasPermission('dashboard.view'))
                <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><x-ui-icon name="layout-dashboard"/><b>لوحة القيادة</b></a>
            @endif

            @if(auth()->user()->hasPermission('sales-leads.view')||auth()->user()->hasPermission('quotations.view')||auth()->user()->hasPermission('contracts.view')||auth()->user()->hasPermission('customers.view'))<div class="nav-label">المبيعات والعقود</div>@endif
            @if(auth()->user()->hasPermission('sales-leads.view'))<a class="nav-link {{ request()->routeIs('sales-leads.*')?'active':'' }}" href="{{ route('sales-leads.index') }}"><x-ui-icon name="users-round"/><b>العملاء المحتملون</b></a>@endif
            @if(auth()->user()->hasPermission('tasks.view'))<a class="nav-link {{ request()->routeIs('tasks.*')?'active':'' }}" href="{{ route('tasks.index') }}"><x-ui-icon name="list-checks"/><b>المهام</b></a>@endif
            @if(auth()->user()->hasPermission('quotations.view'))<a class="nav-link {{ request()->routeIs('quotations.*')?'active':'' }}" href="{{ route('quotations.index') }}"><x-ui-icon name="file-text"/><b>عروض المبيعات</b></a>@endif
            @if(auth()->user()->hasPermission('pricing-offers.view'))<a class="nav-link {{ request()->routeIs('pricing-offers.*')?'active':'' }}" href="{{ route('pricing-offers.index') }}"><x-ui-icon name="tags"/><b>العروض والخصومات</b></a>@endif
            @if(auth()->user()->hasPermission('contracts.view'))<a class="nav-link {{ request()->routeIs('contracts.*')||request()->routeIs('addendums.*')?'active':'' }}" href="{{ route('contracts.index') }}"><x-ui-icon name="file-text"/><b>العقود</b></a>@endif
            @if(auth()->user()->hasPermission('customers.view'))<a class="nav-link {{ request()->routeIs('customers.*')||request()->routeIs('customer-success.*')?'active':'' }}" href="{{ route('customers.index') }}"><x-ui-icon name="users-round"/><b>العملاء</b></a>@endif

            @if(auth()->user()->hasPermission('receivables.view')||auth()->user()->hasPermission('collections.view')||auth()->user()->hasPermission('discount-vouchers.view')||auth()->user()->hasPermission('bank-statements.view'))<div class="nav-label">المالية</div>@endif
            @if(auth()->user()->hasPermission('receivables.view'))<a class="nav-link {{ request()->routeIs('receivables.*')?'active':'' }}" href="{{ route('receivables.index') }}"><x-ui-icon name="calendar-days"/><b>الاستحقاقات</b></a>@endif
            @if(auth()->user()->hasPermission('collections.view'))<a class="nav-link {{ request()->routeIs('collections.*')?'active':'' }}" href="{{ route('collections.index') }}"><x-ui-icon name="wallet"/><b>سندات القبض</b></a>@endif
            @if(auth()->user()->hasPermission('discount-vouchers.view'))<a class="nav-link {{ request()->routeIs('discount-vouchers.*')?'active':'' }}" href="{{ route('discount-vouchers.index') }}"><x-ui-icon name="tags"/><b>سندات الخصم</b></a>@endif
            @if(auth()->user()->hasPermission('purchases.view'))<a class="nav-link {{ request()->routeIs('purchases.*')?'active':'' }}" href="{{ route('purchases.index') }}"><x-ui-icon name="shopping-cart"/><b>المشتريات</b></a>@endif
            @if(auth()->user()->hasPermission('expenses.view'))<a class="nav-link {{ request()->routeIs('expenses.*')?'active':'' }}" href="{{ route('expenses.index') }}"><x-ui-icon name="wallet"/><b>المصروفات</b></a>@endif
            @if(auth()->user()->hasPermission('bank-statements.view'))<a class="nav-link {{ request()->routeIs('bank-statements.*')?'active':'' }}" href="{{ route('bank-statements.index') }}"><x-ui-icon name="landmark"/><b>مطابقة كشف البنك</b></a>@endif
            @if(auth()->user()->hasPermission('intermediaries.commissions'))<a class="nav-link {{ request()->routeIs('intermediary-commissions.*')?'active':'' }}" href="{{ route('intermediary-commissions.index') }}"><x-ui-icon name="users-round"/><b>عمولات الوسطاء</b></a>@endif
            @if(auth()->user()->hasPermission('reports.contracts'))<a class="nav-link {{ request()->routeIs('reports.customer-products-due')?'active':'' }}" href="{{ route('reports.customer-products-due') }}"><x-ui-icon name="calendar-days"/><b>استحقاقات العملاء</b></a>@endif
            @if(auth()->user()->hasPermission('reports.profitability'))<a class="nav-link {{ request()->routeIs('reports.profitability')?'active':'' }}" href="{{ route('reports.profitability') }}"><x-ui-icon name="chart-no-axes-combined"/><b>الربحية</b></a>@endif

            <div class="nav-label">الإعداد والتشغيل</div>
            @if(auth()->user()->hasPermission('products.view'))<a class="nav-link {{ request()->routeIs('products.*')?'active':'' }}" href="{{ route('products.index') }}"><x-ui-icon name="package"/><b>المنتجات والموديولات</b></a>@endif
            @if(auth()->user()->hasPermission('intermediaries.view'))<a class="nav-link {{ request()->routeIs('intermediaries.*')?'active':'' }}" href="{{ route('intermediaries.index') }}"><x-ui-icon name="users-round"/><b>الوسطاء</b></a>@endif
            @if(auth()->user()->hasPermission('suppliers.view'))<a class="nav-link {{ request()->routeIs('suppliers.*')?'active':'' }}" href="{{ route('suppliers.index') }}"><x-ui-icon name="shopping-cart"/><b>الموردون</b></a>@endif
            @if(auth()->user()->hasPermission('stations.view'))<a class="nav-link {{ request()->routeIs('stations.*')?'active':'' }}" href="{{ route('stations.index') }}"><x-ui-icon name="map-pin"/><b>المحطات</b></a>@endif
            @if(auth()->user()->hasPermission('installations.view'))<a class="nav-link {{ request()->routeIs('installations.*')?'active':'' }}" href="{{ route('installations.index') }}"><x-ui-icon name="wrench"/><b>التركيبات</b></a>@endif
            @if(auth()->user()->hasPermission('imports.view'))<a class="nav-link {{ request()->routeIs('imports.*')?'active':'' }}" href="{{ route('imports.index') }}"><x-ui-icon name="upload"/><b>استيراد العقود</b></a>@endif

            @if(auth()->user()->hasPermission('users.view')||auth()->user()->hasPermission('reports.audit'))<div class="nav-label">الإدارة والأمان</div>@endif
            @if(auth()->user()->hasPermission('users.view'))<a class="nav-link {{ request()->routeIs('admin.users.*')?'active':'' }}" href="{{ route('admin.users.index') }}"><x-ui-icon name="users-round"/><b>المستخدمون</b></a>@endif
            @if(auth()->user()->hasPermission('users.roles'))<a class="nav-link {{ request()->routeIs('admin.roles.*')?'active':'' }}" href="{{ route('admin.roles.index') }}"><x-ui-icon name="shield-check"/><b>الأدوار والصلاحيات</b></a>@endif
            @if(auth()->user()->hasPermission('reports.audit'))<a class="nav-link {{ request()->routeIs('audit.*')?'active':'' }}" href="{{ route('audit.index') }}"><x-ui-icon name="history"/><b>سجل التدقيق</b></a>@endif
        </nav>

        <div class="sidebar-foot">
            <div class="sidebar-user"><span class="sidebar-user-avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small><span class="status-dot"></span> متصل الآن</small></div></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-light sidebar-logout">تسجيل الخروج</button></form>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <div class="topbar-context">
                <button class="icon-button mobile-only" type="button" data-sidebar-toggle aria-label="القائمة" aria-controls="sidebar" aria-expanded="false"><span class="menu-lines" aria-hidden="true"></span></button>
                <div class="topbar-title"><h1>@yield('page-title','لوحة القيادة')</h1><p>@yield('page-subtitle','متابعة العقود والاستحقاقات والتحصيلات')</p></div>
            </div>
            <div class="top-actions">
                @if(auth()->user()->hasPermission('sales-leads.create') || auth()->user()->hasPermission('quotations.create') || auth()->user()->hasPermission('collections.create') || auth()->user()->hasPermission('contracts.create'))
                <details class="quick-actions-menu">
                    <summary class="btn btn-light"><x-ui-icon name="plus"/> إنشاء جديد</summary>
                    <div class="quick-actions-list">
                        @if(auth()->user()->hasPermission('sales-leads.create'))<a href="{{ route('sales-leads.create') }}"><x-ui-icon name="users-round"/> عميل محتمل</a>@endif
                        @if(auth()->user()->hasPermission('quotations.create'))<a href="{{ route('quotations.create') }}"><x-ui-icon name="file-text"/> عرض مبيعات</a>@endif
                        @if(auth()->user()->hasPermission('collections.create'))<a href="{{ route('collections.create') }}"><x-ui-icon name="wallet"/> سند قبض</a>@endif
                        @if(auth()->user()->hasPermission('contracts.create'))<a href="{{ route('contracts.create') }}"><x-ui-icon name="file-text"/> عقد جديد</a>@endif
                    </div>
                </details>
                @endif
                @if(auth()->user()->hasPermission('contracts.create'))<a class="btn btn-primary topbar-primary" href="{{ route('contracts.create') }}">+ عقد جديد</a>@endif
                <div class="topbar-user" aria-label="المستخدم الحالي"><span>{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>Raito Finance</small></div></div>
            </div>
        </header>

        <section class="content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-error" role="alert"><strong>راجع البيانات التالية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
</div>
<div class="sidebar-overlay" data-sidebar-toggle></div>

<div class="modal" id="confirm-action-modal" data-confirm-modal aria-hidden="true">
    <div class="modal-card confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
        <div class="modal-head"><div><h3 id="confirm-action-title">تأكيد الإجراء</h3><p>راجع الإجراء قبل التنفيذ.</p></div><button class="icon-button" type="button" data-confirm-cancel aria-label="إغلاق"><x-ui-icon name="x" /></button></div>
        <div class="modal-body"><p class="confirm-message" data-confirm-message></p></div>
        <div class="modal-foot"><button class="btn btn-light" type="button" data-confirm-cancel>رجوع</button><button class="btn btn-danger" type="button" data-confirm-accept>تأكيد وتنفيذ</button></div>
    </div>
</div>

@stack('modals')
<script src="{{ asset('assets/vendor/jquery.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/select2.min.js') }}" defer></script>
<script src="{{ asset('assets/app.js') }}?v={{ @filemtime(public_path('assets/app.js')) ?: 1 }}" defer></script>
@stack('scripts')
</body>
</html>
