<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ABPMS' }} · Agricultural Budget & Procurement Management System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">✦</span><span><strong>ABPMS</strong><span>Bongabong MAO</span></span></a>
        @auth
            <nav>
                <div class="nav-label">Workspace</div>
                <a class="nav-link {{ ($section ?? '') === 'overview' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="icon">⌂</span>Overview</a>
                @if(auth()->user()->canAccess('budget.manage') || auth()->user()->canAccess('budget.adjust'))
                    <div class="nav-label">Budget control</div>
                    <a class="nav-link {{ in_array($section ?? '', ['budget','periods','allocations','adjustments','ledger']) ? 'active' : '' }}" href="{{ route('budget.index') }}"><span class="icon">₱</span>Budget overview</a>
                    <a class="nav-link" href="{{ route('periods.index') }}"><span class="icon">▣</span>Fiscal periods</a>
                    <a class="nav-link" href="{{ route('ledger.index') }}"><span class="icon">≡</span>Budget ledger</a>
                @endif
                @if(auth()->user()->canAccess('procurement.manage'))
                    <div class="nav-label">Procurement</div>
                    <a class="nav-link {{ in_array($section ?? '', ['procurement','approvals']) ? 'active' : '' }}" href="{{ route('procurement.index') }}"><span class="icon">⌁</span>Purchase requests</a>
                    <a class="nav-link" href="{{ route('suppliers.index') }}"><span class="icon">◉</span>Suppliers</a>
                    <a class="nav-link" href="{{ route('orders.index') }}"><span class="icon">▤</span>Purchase orders</a>
                @endif
                @if(auth()->user()->canAccess('receiving.manage'))
                    <a class="nav-link" href="{{ route('receiving.index') }}"><span class="icon">↥</span>Receiving</a>
                @endif
                @if(auth()->user()->canAccess('reports.view'))
                    <div class="nav-label">Insights</div>
                    <a class="nav-link" href="{{ route('reports.index') }}"><span class="icon">◒</span>Reports & analytics</a>
                @endif
                @if(auth()->user()->canAccess('audit.view'))
                    <a class="nav-link" href="{{ route('audit.index') }}"><span class="icon">⌕</span>Audit trail</a>
                @endif
                @if(auth()->user()->hasRole('system_administrator'))
                    <div class="nav-label">Administration</div>
                    <a class="nav-link" href="{{ route('users.index') }}"><span class="icon">♙</span>User management</a>
                    <a class="nav-link" href="{{ route('roles.index') }}"><span class="icon">◆</span>Roles & permissions</a>
                @endif
            </nav>
            <div class="sidebar-bottom">
                <div class="user-chip"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span><strong>{{ auth()->user()->name }}</strong><span>{{ config('abpms.role_labels.'.auth()->user()->role) }}</span></span></div>
                <a class="nav-link" href="{{ route('profile.edit') }}"><span class="icon">⚙</span>My profile</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link" style="background:none;border:0;width:100%;text-align:left;cursor:pointer"><span class="icon">↪</span>Sign out</button></form>
            </div>
        @endauth
    </aside>
    <main class="main">
        <header class="topbar"><div><div class="eyebrow">Agricultural Budget & Procurement Management System</div><h1>{{ $heading ?? 'Command center' }}</h1></div><div class="top-actions"><span class="badge green">FY {{ now()->year }} · Bongabong MAO</span><a class="btn secondary" href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a></div></header>
        <div class="content">
            @if(session('success')) <div class="flash">{{ session('success') }}</div> @endif
            @if($errors->any()) <div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
