@extends('layouts.app')
@section('title', $customer->displayName())
@section('content')
<p style="margin-bottom:10px"><a class="btn ghost sm" href="{{ route('customers.index') }}">← Customers</a></p>
<section class="card pad" style="margin-bottom:16px"><div class="cust-head">
  <div class="avatar">{{ collect(explode(' ', $customer->displayName()))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
  <div style="flex:1;min-width:200px"><h1 style="font-size:22px">{{ $customer->displayName() }}</h1><p class="muted small"><span class="mono">{{ $customer->code }}</span> · @if($customer->company){{ $customer->name }} · @endif{{ $customer->phone }} · {{ $customer->email }} · <span class="tag">{{ $customer->category }}</span></p></div>
  <div class="ph-act">
    @can('invoices.create')<a class="btn pri" href="{{ route('invoices.create', ['customer' => $customer->id]) }}">New invoice</a>@endcan
    @can('payments.record')<a class="btn" href="{{ route('payments.create', ['customer' => $customer->id]) }}">Record payment</a>@endcan
    @can('customers.manage')<a class="btn" href="{{ route('customers.edit', $customer) }}">Edit</a>@endcan
  </div></div></section>
<section class="card" style="margin-bottom:16px"><div class="fin">
  <div><span class="lbl">Credit limit</span><b>{{ money($customer->credit_limit, $customer->currency_code) }}</b></div>
  <div><span class="lbl">Outstanding</span><b>{{ money($fin['outstanding'], $customer->currency_code) }}</b></div>
  <div><span class="lbl">Overdue</span><b style="color:{{ $fin['overdue'] ? 'var(--bad)' : 'inherit' }}">{{ money($fin['overdue'], $customer->currency_code) }}</b></div>
  <div><span class="lbl">Total purchases</span><b>{{ money($fin['purchases'], $customer->currency_code) }}</b></div>
  <div><span class="lbl">Total payments</span><b>{{ money($fin['payments'], $customer->currency_code) }}</b></div>
</div></section>
<div class="tabs">@foreach(['timeline' => 'Activity', 'statement' => 'Statement', 'profile' => 'Profile'] as $k => $l)<a href="?tab={{ $k }}" class="{{ $tab === $k ? 'on' : '' }}" style="padding:10px 14px;font-weight:600;text-decoration:none;color:inherit;border-bottom:2px solid {{ $tab === $k ? 'var(--accent)' : 'transparent' }}">{{ $l }}</a>@endforeach</div>

@if($tab === 'statement')
  <section class="card"><div class="card-h"><div><h3>Statement of account</h3><p class="small muted">{{ fdate($statement['from']) }} – {{ fdate($statement['to']) }} · {{ $customer->currency_code }}</p></div>
    <form><input type="hidden" name="tab" value="statement"><select class="sel" name="period" data-autosubmit>@foreach(['month' => 'This month', 'last' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year', 'all' => 'All time'] as $k => $l)<option value="{{ $k }}" @selected($period === $k)>{{ $l }}</option>@endforeach</select></form></div>
  <div class="tw"><table class="tbl"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>
    <tr><td>{{ fdate($statement['from']) }}</td><td></td><td class="muted">Opening balance</td><td></td><td></td><td class="num strong">{{ money($statement['opening'], $customer->currency_code, false) }}</td></tr>
    @foreach($statement['rows'] as $r)<tr><td>{{ fdate($r['date']) }}</td><td class="mono">{{ $r['ref'] }}</td><td>{{ $r['desc'] }}</td><td class="num">{{ $r['debit'] ? money($r['debit'], $customer->currency_code, false) : '' }}</td><td class="num">{{ $r['credit'] ? money($r['credit'], $customer->currency_code, false) : '' }}</td><td class="num">{{ money($r['balance'], $customer->currency_code, false) }}</td></tr>@endforeach
    <tr><td colspan="5" class="strong">Closing balance</td><td class="num strong">{{ money($statement['closing'], $customer->currency_code) }}</td></tr>
  </tbody></table></div></section>
@elseif($tab === 'profile')
  <section class="card pad"><dl class="bk">
    <dt>Type</dt><dd>{{ ucfirst($customer->type) }}</dd><dt>Address</dt><dd>{{ $customer->address }}, {{ $customer->city }}, {{ $customer->country->name }}</dd>
    <dt>Branch</dt><dd>{{ $customer->branch->name }}</dd><dt>Tax ID</dt><dd>{{ $customer->tax_id ?: '—' }}</dd><dt>Registration</dt><dd>{{ $customer->registration_no ?: '—' }}</dd>
    <dt>Industry</dt><dd>{{ $customer->industry ?: '—' }}</dd><dt>Account manager</dt><dd>{{ $customer->accountManager?->name ?? '—' }}</dd>
    <dt>Terms</dt><dd>{{ config('dopay.payment_terms')[$customer->payment_terms_days] ?? 'Net '.$customer->payment_terms_days }}</dd><dt>Currency</dt><dd>{{ $customer->currency_code }}</dd>
  </dl></section>
@else
  <div class="grid g-2">
    <section class="card"><div class="card-h"><h3>Invoices</h3></div><div class="tw"><table class="tbl cards"><tbody>
      @forelse($customer->invoices as $i)<tr class="click" data-href="{{ route('invoices.show', $i) }}"><td class="lead" data-label="Invoice"><span class="mono strong">{{ $i->number }}</span><span class="sub2">{{ fdate($i->issue_date) }}</span></td><td class="num" data-label="Total">{{ money($i->total, $i->currency_code) }}</td><td data-label="Status">@include('partials.pill', ['label' => $i->displayStatus()])</td></tr>
      @empty<tr><td class="empty">No invoices yet.</td></tr>@endforelse</tbody></table></div></section>
    <section class="card"><div class="card-h"><h3>Payments & messages</h3></div><div class="pad timeline" style="padding-top:4px">
      @foreach($customer->payments as $p)<div class="tl"><div class="av">₵</div><div>Payment {{ money($p->amount, $p->currency_code) }} · {{ $p->method }}<div class="small muted">{{ fdate($p->paid_on) }} · <span class="mono">{{ $p->reference }}</span></div></div><div>@if($p->receipt)<a class="btn sm" href="{{ route('receipts.show', $p->receipt) }}">Receipt</a>@endif</div></div>@endforeach
      @foreach($customer->communications as $m)<div class="tl"><div class="av">✉</div><div>{{ $m->kind }} <span class="mono">{{ $m->reference }}</span> via {{ $m->channel }}<div class="small muted">{{ $m->created_at->format('j M Y, H:i') }}</div></div><div>@include('partials.pill', ['label' => ucfirst($m->status)])</div></div>@endforeach
    </div></section>
  </div>
@endif
@endsection
