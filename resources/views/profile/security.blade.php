@extends('layouts.app')
@section('title', 'Security')
@section('content')
<div class="ph"><div><h1>Security</h1><p class="sub">Two-factor sign-in and your signing PIN. Both are required before you can use Dopay.</p></div></div>
<div class="grid g-2">
  <section class="card pad stack" style="gap:12px">
    <h3>Two-factor authentication</h3>
    @if(! $user->two_factor_secret)
      <p class="small">Use an authenticator app (Google Authenticator, Microsoft Authenticator, Authy) to add a 6-digit code at sign-in.</p>
      <form method="post" action="{{ url('/user/two-factor-authentication') }}">@csrf<button class="btn pri">Set up two-factor</button></form>
    @elseif(! $user->two_factor_confirmed_at)
      <p class="small">Scan this code with your authenticator app, then enter the 6-digit code it shows.</p>
      <div class="qr">{!! $user->twoFactorQrCodeSvg() !!}</div>
      <form method="post" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="row">@csrf
        <input class="inp" name="code" inputmode="numeric" placeholder="123456" style="max-width:160px" required><button class="btn pri">Confirm</button>
      </form>
    @else
      <p class="small">{!! view('partials.pill', ['label' => 'Active'])->render() !!} Two-factor has been on since {{ fdate($user->two_factor_confirmed_at) }}.</p>
      <details><summary class="small">Show recovery codes</summary><pre class="mono small">{{ implode("\n", $user->recoveryCodes()) }}</pre></details>
    @endif
  </section>
  <section class="card pad stack" style="gap:12px">
    <h3>Signing PIN</h3>
    <p class="small">You enter this PIN to sign finance forms and to authorize another person’s sensitive action. Keep it private.</p>
    <form method="post" action="{{ route('profile.pin') }}" class="fg">@csrf
      <div class="f full"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required></div>
      <div class="f"><label for="pin">New PIN (4–6 digits)</label><input id="pin" name="pin" type="password" inputmode="numeric" required></div>
      <div class="f"><label for="pin_confirmation">Repeat PIN</label><input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" required></div>
      <div class="full"><button class="btn pri">{{ $user->signing_pin ? 'Change PIN' : 'Save PIN' }}</button></div>
    </form>
  </section>
</div>
@endsection
