@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('content')
<h2 style="margin-bottom:14px">Choose a new password</h2>
<form method="post" action="{{ route('password.update') }}" class="stack" style="gap:12px">@csrf
  <input type="hidden" name="token" value="{{ $request->route('token') }}">
  <div class="f"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required></div>
  <div class="f"><label for="password">New password</label><input id="password" name="password" type="password" required autocomplete="new-password"><span class="hint">At least 12 characters with upper and lower case letters and a number.</span></div>
  <div class="f"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required></div>
  <button class="btn pri" style="width:100%">Save password</button>
</form>
@endsection
