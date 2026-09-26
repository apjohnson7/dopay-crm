<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · Dopay CRM</title>
<link rel="icon" href="{{ asset('img/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Mono:wght@400;500&family=Caveat:wght@500;600&display=swap">
<link rel="stylesheet" href="{{ asset('css/dopay.css') }}?v=3">
</head>
<body>
@php
  $u = auth()->user();
  $nav = [
    'Overview' => [['dashboard', 'Dashboard', 'dashboard', null]],
    'Billing' => array_values(array_filter([['customers.index', 'Customers', 'customers*', null], ['invoices.index', 'Invoices', 'invoices*', null], ['payments.index', 'Payments', 'payments*|receipts*', null], $u->can('reports.view') ? ['taxes.index', 'Taxes', 'taxes*', null] : null])),
    'Spending & forms' => [['forms.index', 'Finance forms', 'forms*|budget*', $navFormsAwaiting ?? 0]],
    'Records' => [['messages.index', 'Team messages', 'messages*', $navUnreadMessages ?? 0], ['help', 'Help & guide', 'help', null]],
    'Administration' => array_values(array_filter([$u->can('audit.view') ? ['audit.index', 'Audit trail', 'audit*', null] : null, ['profile.security', 'Security', 'profile*', null]])),
  ];
@endphp
<div class="app">
  <aside class="side" id="side" aria-label="Main navigation">
    <a class="brand" href="{{ route('dashboard') }}" style="text-decoration:none"><img class="brand-logo" src="{{ asset('img/dopay-logo.png') }}" alt="Dopay logo" width="42"><div><b>Dopay</b><small>CRM</small></div></a>
    <div class="brand-stripe" aria-hidden="true"><i style="background:#0A47A6"></i><i style="background:#D7191F"></i><i style="background:#1B9A3E"></i></div>
    <nav class="nav">
      @foreach($nav as $group => $items)
        <div class="nav-g"><div class="nav-h">{{ $group }}</div>
          @foreach($items as [$route, $label, $pattern, $count])
            @php $on = collect(explode('|', $pattern))->contains(fn($p) => request()->routeIs($p)); @endphp
            <a href="{{ route($route) }}" class="{{ $on ? 'on' : '' }}" @if($on) aria-current="page" @endif>{{ $label }}@if($count)<span class="cnt">{{ $count }}</span>@endif</a>
          @endforeach
        </div>
      @endforeach
    </nav>
    <div class="side-foot">{{ $u->name }}<br>{{ $u->roleName() }} · {{ $u->branch?->name ?? 'All countries' }}</div>
  </aside>
  <div class="scrim" id="sideScrim" hidden data-close-side></div>
  <div class="main">
    <header class="top">
      <button class="icon-btn menu-btn" type="button" data-open-side aria-label="Open menu">☰</button>
      <form class="search" action="{{ route('search') }}" method="get" role="search">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search customers, invoices, receipts, forms… try “John 250000”" aria-label="Search">
      </form>
      @if($scopeCountries->count() > 1)
        <form method="post" action="{{ route('scope') }}" style="margin-left:auto">@csrf
          <select class="sel" name="country_id" onchange="this.form.submit()" aria-label="Country">
            <option value="">All countries · {{ config('dopay.base_currency') }}</option>
            @foreach($scopeCountries as $c)<option value="{{ $c->id }}" @selected($scopeCountryId === $c->id)>{{ $c->name }} · {{ $c->currency_code }}</option>@endforeach
          </select>
        </form>
      @else
        <span class="proto" style="margin-left:auto;background:var(--accent-soft);color:var(--accent)">{{ $u->country()?->name }} · {{ $u->country()?->localTime() }}</span>
      @endif
      <a class="ai-btn" href="{{ route('help') }}">Help</a>
      <details class="user-menu" style="position:relative">
        <summary class="icon-btn" aria-label="Notifications">🔔@if($navNotifications->count())<span class="dot-count">{{ $navNotifications->count() }}</span>@endif</summary>
        <div class="pop notif">
          <div class="card-h" style="padding:10px 14px"><b>Notifications</b><form method="post" action="{{ route('notifications.read') }}">@csrf<button class="btn ghost sm">Mark all read</button></form></div>
          @forelse($navNotifications as $n)
            <a class="n unread" href="{{ $n->data['url'] ?? '#' }}" style="text-decoration:none;color:inherit;display:block"><b style="font-weight:600;font-size:13px">{{ $n->data['text'] ?? '' }}</b><span>{{ $n->created_at->diffForHumans() }}</span></a>
          @empty <div class="empty">You're all caught up.</div> @endforelse
        </div>
      </details>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn ghost sm">Sign out</button></form>
    </header>
    <main class="content" id="view">
      @include('partials.flash')
      @yield('content')
    </main>
  </div>
</div>
<script src="{{ asset('js/dopay.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
