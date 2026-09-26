@extends('layouts.guest')
@section('content')
<h2 style="margin-bottom:14px">Sign in</h2>
<form method="post" action="{{ route('login') }}" class="stack" style="gap:12px">@csrf
  <div class="f"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
  <div class="f"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <label class="small"><input type="checkbox" name="remember"> Keep me signed in on this device</label>
  <button class="btn pri" style="width:100%">Continue</button>
  <a class="small" href="{{ route('password.request') }}">Forgot your password?</a>
</form>
@endsection
