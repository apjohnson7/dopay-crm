@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
<h2 style="margin-bottom:6px">Reset your password</h2>
<p class="small muted" style="margin-bottom:14px">We’ll email you a link to choose a new password.</p>
<form method="post" action="{{ route('password.email') }}" class="stack" style="gap:12px">@csrf
  <div class="f"><label for="email">Work email</label><input id="email" name="email" type="email" required autofocus></div>
  <button class="btn pri" style="width:100%">Email me a reset link</button>
  <a class="small" href="{{ route('login') }}">Back to sign in</a>
</form>
@endsection
