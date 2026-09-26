@extends('layouts.guest')
@section('title', 'Confirm password')
@section('content')
<h2 style="margin-bottom:6px">Confirm your password</h2>
<p class="small muted" style="margin-bottom:14px">This is a secure area. Please confirm your password to continue.</p>
<form method="post" action="{{ url('/user/confirm-password') }}" class="stack" style="gap:12px">@csrf
  <div class="f"><label for="password">Password</label><input id="password" name="password" type="password" required autofocus></div>
  <button class="btn pri" style="width:100%">Confirm</button>
</form>
@endsection
