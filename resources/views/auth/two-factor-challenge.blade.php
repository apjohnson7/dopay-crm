@extends('layouts.guest')
@section('title', 'Two-factor check')
@section('content')
<h2 style="margin-bottom:6px">Two-factor check</h2>
<p class="small muted" style="margin-bottom:14px">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
<form method="post" action="{{ url('/two-factor-challenge') }}" class="stack" style="gap:12px">@csrf
  <div class="f"><label for="code">Code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus></div>
  <div class="f"><label for="recovery_code">Or a recovery code</label><input id="recovery_code" name="recovery_code" autocomplete="off"></div>
  <button class="btn pri" style="width:100%">Verify and sign in</button>
</form>
@endsection
