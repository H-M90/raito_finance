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
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">R</div>
            <div class="brand-copy"><strong>Raito Finance</strong><small>ERP & Finance Workspace</small></div>
        </div>

        <nav class="nav-list" aria-label="القائمة الرئيسية">
            @if(auth()->user()->hasPermission('dashboard.view'))
                <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>لوحة القيادة</b></a>
            @endif

            @if(auth()->user()->hasPermission('sales-leads.view')||auth()->user()->hasPermission('quotations.view')||auth()->user()->hasPermission('contracts.view')||auth()->user()->hasPermission('customers.view'))<div class="nav-label">المبيعات والعقود</div>@endif
            @if(auth()->user()->hasPermission('sales-leads.view'))<a class="nav-link {{ request()->routeIs('sales-leads.*')?'active':'' }}" href="{{ route('sales-leads.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>العملاء المحتملون</b></a>@endif
            @if(auth()->user()->hasPermission('tasks.view'))<a class="nav-link {{ request()->routeIs('tasks.*')?'active':'' }}" href="{{ route('tasks.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المهام</b></a>@endif
            @if(auth()->user()->hasPermission('quotations.view'))<a class="nav-link {{ request()->routeIs('quotations.*')?'active':'' }}" href="{{ route('quotations.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>عروض المبيعات</b></a>@endif
            @if(auth()->user()->hasPermission('pricing-offers.view'))<a class="nav-link {{ request()->routeIs('pricing-offers.*')?'active':'' }}" href="{{ route('pricing-offers.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>العروض والخصومات</b></a>@endif
            @if(auth()->user()->hasPermission('contracts.view'))<a class="nav-link {{ request()->routeIs('contracts.*')||request()->routeIs('addendums.*')?'active':'' }}" href="{{ route('contracts.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>العقود</b></a>@endif
            @if(auth()->user()->hasPermission('customers.view'))<a class="nav-link {{ request()->routeIs('customers.*')||request()->routeIs('customer-success.*')?'active':'' }}" href="{{ route('customers.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>العملاء</b></a>@endif

            @if(auth()->user()->hasPermission('receivables.view')||auth()->user()->hasPermission('collections.view')||auth()->user()->hasPermission('discount-vouchers.view')||auth()->user()->hasPermission('bank-statements.view'))<div class="nav-label">المالية</div>@endif
            @if(auth()->user()->hasPermission('receivables.view'))<a class="nav-link {{ request()->routeIs('receivables.*')?'active':'' }}" href="{{ route('receivables.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>الاستحقاقات</b></a>@endif
            @if(auth()->user()->hasPermission('collections.view'))<a class="nav-link {{ request()->routeIs('collections.*')?'active':'' }}" href="{{ route('collections.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>سندات القبض</b></a>@endif
            @if(auth()->user()->hasPermission('discount-vouchers.view'))<a class="nav-link {{ request()->routeIs('discount-vouchers.*')?'active':'' }}" href="{{ route('discount-vouchers.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>سندات الخصم</b></a>@endif
            @if(auth()->user()->hasPermission('purchases.view'))<a class="nav-link {{ request()->routeIs('purchases.*')?'active':'' }}" href="{{ route('purchases.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المشتريات</b></a>@endif
            @if(auth()->user()->hasPermission('expenses.view'))<a class="nav-link {{ request()->routeIs('expenses.*')?'active':'' }}" href="{{ route('expenses.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المصروفات</b></a>@endif
            @if(auth()->user()->hasPermission('bank-statements.view'))<a class="nav-link {{ request()->routeIs('bank-statements.*')?'active':'' }}" href="{{ route('bank-statements.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>مطابقة كشف البنك</b></a>@endif
            @if(auth()->user()->hasPermission('intermediaries.commissions'))<a class="nav-link {{ request()->routeIs('intermediary-commissions.*')?'active':'' }}" href="{{ route('intermediary-commissions.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>عمولات الوسطاء</b></a>@endif
            @if(auth()->user()->hasPermission('reports.contracts'))<a class="nav-link {{ request()->routeIs('reports.customer-products-due')?'active':'' }}" href="{{ route('reports.customer-products-due') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>استحقاقات العملاء</b></a>@endif
            @if(auth()->user()->hasPermission('reports.profitability'))<a class="nav-link {{ request()->routeIs('reports.profitability')?'active':'' }}" href="{{ route('reports.profitability') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>الربحية</b></a>@endif

            <div class="nav-label">الإعداد والتشغيل</div>
            @if(auth()->user()->hasPermission('products.view'))<a class="nav-link {{ request()->routeIs('products.*')?'active':'' }}" href="{{ route('products.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المنتجات والموديولات</b></a>@endif
            @if(auth()->user()->hasPermission('intermediaries.view'))<a class="nav-link {{ request()->routeIs('intermediaries.*')?'active':'' }}" href="{{ route('intermediaries.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>الوسطاء</b></a>@endif
            @if(auth()->user()->hasPermission('suppliers.view'))<a class="nav-link {{ request()->routeIs('suppliers.*')?'active':'' }}" href="{{ route('suppliers.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>الموردون</b></a>@endif
            @if(auth()->user()->hasPermission('stations.view'))<a class="nav-link {{ request()->routeIs('stations.*')?'active':'' }}" href="{{ route('stations.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المحطات</b></a>@endif
            @if(auth()->user()->hasPermission('installations.view'))<a class="nav-link {{ request()->routeIs('installations.*')?'active':'' }}" href="{{ route('installations.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>التركيبات</b></a>@endif
            @if(auth()->user()->hasPermission('imports.view'))<a class="nav-link {{ request()->routeIs('imports.*')?'active':'' }}" href="{{ route('imports.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>استيراد العقود</b></a>@endif

            @if(auth()->user()->hasPermission('users.view')||auth()->user()->hasPermission('reports.audit'))<div class="nav-label">الإدارة والأمان</div>@endif
            @if(auth()->user()->hasPermission('users.view'))<a class="nav-link {{ request()->routeIs('admin.users.*')?'active':'' }}" href="{{ route('admin.users.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>المستخدمون</b></a>@endif
            @if(auth()->user()->hasPermission('users.roles'))<a class="nav-link {{ request()->routeIs('admin.roles.*')?'active':'' }}" href="{{ route('admin.roles.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>الأدوار والصلاحيات</b></a>@endif
            @if(auth()->user()->hasPermission('reports.audit'))<a class="nav-link {{ request()->routeIs('audit.*')?'active':'' }}" href="{{ route('audit.index') }}"><span class="nav-safe-mark" aria-hidden="true"></span><b>سجل التدقيق</b></a>@endif
        </nav>

        <div class="sidebar-foot">
            <div class="sidebar-user"><span class="sidebar-user-avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small><span class="status-dot"></span> متصل الآن</small></div></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-light sidebar-logout">تسجيل الخروج</button></form>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <div class="topbar-context">
                <button class="icon-button mobile-only" type="button" data-sidebar-toggle aria-label="القائمة"><span class="menu-lines" aria-hidden="true"></span></button>
                <div class="topbar-title"><h1>@yield('page-title','لوحة القيادة')</h1><p>@yield('page-subtitle','متابعة العقود والاستحقاقات والتحصيلات')</p></div>
            </div>
            <div class="top-actions">
                @if(auth()->user()->hasPermission('sales-leads.create'))<a class="btn btn-light topbar-quick" href="{{ route('sales-leads.create') }}">عميل محتمل</a>@endif
                @if(auth()->user()->hasPermission('quotations.create'))<a class="btn btn-light topbar-quick" href="{{ route('quotations.create') }}">عرض مبيعات</a>@endif
                @if(auth()->user()->hasPermission('collections.create'))<a class="btn btn-light topbar-quick" href="{{ route('collections.create') }}">سند قبض</a>@endif
                @if(auth()->user()->hasPermission('contracts.create'))<a class="btn btn-primary topbar-primary" href="{{ route('contracts.create') }}">+ عقد جديد</a>@endif
                <div class="topbar-user" aria-label="المستخدم الحالي"><span>{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>Raito Finance</small></div></div>
            </div>
        </header>

        <section class="content">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-error"><strong>راجع البيانات التالية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
</div>
<div class="sidebar-overlay" data-sidebar-toggle></div>

<div class="modal" id="confirm-action-modal" data-confirm-modal aria-hidden="true">
    <div class="modal-card confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
        <div class="modal-head"><div><h3 id="confirm-action-title">تأكيد الإجراء</h3><p>راجع الإجراء قبل التنفيذ.</p></div><button class="icon-button" type="button" data-confirm-cancel aria-label="إغلاق">×</button></div>
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
