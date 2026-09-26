<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Sign in') · Dopay CRM</title>
<link rel="icon" href="{{ asset('img/favicon.png') }}">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/dopay.css') }}?v=2">
</head>
<body>
<div class="login" style="position:static;min-height:100vh">
  <div class="card">
    <div class="brand" style="padding:0 0 18px"><img src="{{ asset('img/dopay-logo.png') }}" alt="Dopay logo" style="width:58px"><div><b style="color:var(--ink);font-size:22px">Dopay CRM</b><small style="color:var(--ink-3)">Finance & administration</small></div></div>
    @include('partials.flash')
    @yield('content')
  </div>
</div>
</body>
</html>
