@extends('layouts.app')
@section('title', 'Account')
@section('content')
<div class="ph"><div><h1>DoPay {{ $country->name }} account</h1><p class="sub">{{ $country->legal_entity }} · {{ $country->currency_code }} · {{ $country->tax_name }} {{ rtrim(rtrim(number_format($country->tax_rate, 3), '0'), '.') }}%. Each country's records, users and settings are kept separate; only the Super Administrator can open other accounts.</p></div>
  @if($countries->count() > 1)<form><select class="sel" name="country" onchange="this.form.submit()" aria-label="Account">@foreach($countries as $c)<option value="{{ $c->id }}" @selected($c->id === $country->id)>DoPay {{ $c->name }}</option>@endforeach</select></form>@endif</div>
<div class="grid g-2">
  <form method="post" action="{{ route('account.update', $country) }}" class="card pad">@csrf @method('put')
    <h3 style="margin-bottom:12px">Details on invoices, receipts and statements</h3>
    <div class="fg">
      <div class="f full"><label>Legal entity</label><input value="{{ $country->legal_entity }}" readonly></div>
      <div class="f full"><label for="address">Office address</label><input id="address" name="address" value="{{ old('address', $country->address) }}" @cannot('settings.manage') disabled @endcannot></div>
      <div class="f"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $country->phone) }}" @cannot('settings.manage') disabled @endcannot></div>
      <div class="f"><label for="email">Accounts email</label><input id="email" name="email" type="email" value="{{ old('email', $country->email) }}" @cannot('settings.manage') disabled @endcannot></div>
      <div class="f"><label for="tax_id">Tax ID (TIN)</label><input id="tax_id" name="tax_id" value="{{ old('tax_id', $country->tax_id) }}" @cannot('settings.manage') disabled @endcannot></div>
      <div class="f"><label for="mobile_money">Mobile money</label><input id="mobile_money" name="mobile_money" value="{{ old('mobile_money', $country->mobile_money) }}" @cannot('settings.manage') disabled @endcannot></div>
      <div class="f full"><label for="bank_details">Bank details</label><input id="bank_details" name="bank_details" value="{{ old('bank_details', $country->bank_details) }}" @cannot('settings.manage') disabled @endcannot></div>
    </div>
    @can('settings.manage')<div class="row" style="margin-top:14px"><button class="btn pri">Save account details</button></div>@endcan
  </form>
  <section class="card"><div class="card-h"><h3>People in this account</h3><span class="small muted">{{ $users->count() }}</span></div>
    <div class="tw"><table class="tbl cards"><thead><tr><th>User</th><th>Role</th><th>Branch</th><th>2FA</th></tr></thead><tbody>
    @foreach($users as $u)<tr><td class="lead" data-label="User"><b>{{ $u->name }}</b><span class="sub2">{{ $u->email }}</span></td><td data-label="Role">{{ $u->roleName() }}</td><td data-label="Branch">{{ $u->branch?->name }}</td><td data-label="2FA">@include('partials.pill', ['label' => $u->two_factor_confirmed_at ? 'Active' : 'Off'])</td></tr>@endforeach
    </tbody></table></div></section>
</div>
@endsection
