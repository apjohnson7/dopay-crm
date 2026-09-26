@extends('layouts.app')
@section('title', 'Record payment')
@section('content')
<div class="ph"><div><h1>Record payment</h1><p class="sub">Allocate the money to invoices. A receipt is issued as soon as you save.</p></div></div>
<form method="get" class="card pad" style="margin-bottom:16px"><div class="f"><label for="customer">Customer</label>
  <select id="customer" name="customer" data-autosubmit><option value="">Choose a customer…</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->displayName() }} · {{ $c->code }}</option>@endforeach</select></div></form>
@if($customer)
<form method="post" action="{{ route('payments.store') }}" class="card pad">@csrf<input type="hidden" name="customer_id" value="{{ $customer->id }}">
  <div class="fg">
    <div class="f"><label for="amount">Amount received ({{ $customer->currency_code }})</label><input id="amount" name="amount" type="number" step="any" min="0" value="{{ old('amount', $preselect ? optional($open->firstWhere('id', (int) $preselect))->balance() : '') }}" required></div>
    <div class="f"><label for="paid_on">Payment date</label><input id="paid_on" name="paid_on" type="date" value="{{ old('paid_on', today()->toDateString()) }}" required></div>
    <div class="f"><label for="method">Method</label><select id="method" name="method">@foreach(config('dopay.payment_methods') as $m)<option @selected(old('method', 'Mobile money') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="f"><label for="reference">Transaction reference</label><input id="reference" name="reference" value="{{ old('reference') }}" placeholder="e.g. MTN-MP260926.1044.X12345"></div>
  </div>
  <div class="row" style="justify-content:space-between;margin:16px 0 6px"><h3>Allocate to invoices</h3><button type="button" class="btn sm" data-auto-allocate>Auto-allocate oldest first</button></div>
  @if($open->count())
  <div class="tw"><table class="tbl"><thead><tr><th>Invoice</th><th>Due</th><th class="num">Balance</th><th class="num">Apply</th></tr></thead><tbody>
    @foreach($open as $i)<tr><td class="mono">{{ $i->number }}</td><td>{{ fdate($i->due_date) }} @if($i->displayStatus() === 'Overdue')@include('partials.pill', ['label' => 'Overdue'])@endif</td><td class="num">{{ money($i->balance(), $i->currency_code, false) }}</td>
      <td class="num"><input class="inp tab" style="max-width:140px;text-align:right" type="number" step="any" min="0" name="allocations[{{ $i->id }}]" data-alloc data-max="{{ $i->balance() }}" value="{{ old('allocations.'.$i->id, (int) $preselect === $i->id ? $i->balance() : '') }}"></td></tr>@endforeach
  </tbody></table></div>
  @else <p class="note">No open invoices. The payment will sit as credit on the account.</p> @endif
  <div class="row" style="margin-top:16px"><button class="btn pri">Save & issue receipt</button></div>
</form>
@endif
@endsection
