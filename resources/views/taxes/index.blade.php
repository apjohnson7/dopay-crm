@extends('layouts.app')
@section('title', 'Taxes')
@section('content')
@php
  $cur = $country->currency_code; $tn = $country->tax_name;
  $sum = fn ($k) => $yearRows->sum($k);
  $oweTotal = $owed->sum('balance');
  $next = $owed->first() ?? $open;
  $today = now($country->timezone)->startOfDay();
  $dueTxt = function ($d) use ($today) { $n = (int) $today->diffInDays($d, false); return $n < 0 ? abs($n).' day'.(abs($n) === 1 ? '' : 's').' late' : ($n === 0 ? 'due today' : 'in '.$n.' day'.($n === 1 ? '' : 's')); };
@endphp
<div class="ph"><div><h1>Taxes</h1><p class="sub">{{ $tn }} charged on invoices, tax paid through Expenses, and what is left to remit. Rates and filing days are set below.</p></div>
  <div class="ph-act">
    <a class="btn" href="{{ route('taxes.index', ['country' => $country->id, 'export' => 'csv']) }}">Export CSV</a>
    @can('expenses.create')<button class="btn pri" data-open-modal="taxPay">+ Record tax payment</button>@endcan
  </div></div>

@if($countries->count() > 1)
<div class="tabs">@foreach($countries as $c)<a class="{{ $c->id === $country->id ? 'on' : '' }}" style="text-decoration:none" href="{{ route('taxes.index', ['country' => $c->id]) }}">{{ $c->name }} · {{ $c->tax_name }} {{ rtrim(rtrim(number_format($c->tax_rate, 3), '0'), '.') }}%</a>@endforeach</div>
@endif

<div class="grid g-kpi" style="margin-bottom:16px">
  <div class="card kpi acc"><div class="kpi-stripe"></div><span class="lbl">{{ $tn }} charged · {{ $year }}</span><div class="v"><small>{{ $cur }}</small>{{ money($sum('vat'), $cur, false) }}</div><span class="d">on {{ money($sum('net'), $cur, false) }} taxable sales</span></div>
  <div class="card kpi ok"><div class="kpi-stripe"></div><span class="lbl">{{ $tn }} remitted · {{ $year }}</span><div class="v"><small>{{ $cur }}</small>{{ money($sum('paid'), $cur, false) }}</div><span class="d">paid Tax expenses</span></div>
  <div class="card kpi {{ $owed->contains('status', 'Overdue') ? 'bad' : ($oweTotal > 0 ? 'warn' : 'ok') }}"><div class="kpi-stripe"></div><span class="lbl">Balance to remit</span><div class="v"><small>{{ $cur }}</small>{{ money($oweTotal, $cur, false) }}</div>
    <span class="d">{{ $owed->count() ? $owed->count().' return'.($owed->count() > 1 ? 's' : '').' not fully paid' : 'All closed returns paid' }}@if($open && $open['vat']) · {{ money($open['vat'], $cur, false) }} accruing this month @endif</span></div>
  <div class="card kpi {{ $next && $next['due']->lt($today) ? 'bad' : 'info' }}"><div class="kpi-stripe"></div><span class="lbl">Next filing due</span><div class="v" style="font-size:22px">{{ $next ? fdate($next['due']) : '—' }}</div><span class="d">{{ $next ? $next['label'].' return · '.$dueTxt($next['due']) : '' }}</span></div>
</div>

<section class="card" style="margin-bottom:16px"><div class="card-h"><div><h3>Monthly {{ $tn }} returns</h3><p class="small muted">{{ $country->legal_entity }} · {{ $cur }} · due on day {{ $country->vat_filing_day }} of the following month</p></div></div>
<div class="tw"><table class="tbl cards"><thead><tr><th>Period</th><th class="num">Invoices</th><th class="num">Taxable sales</th><th class="num">{{ $tn }} charged</th><th class="num">Remitted</th><th class="num">Balance</th><th>Due by</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($rows as $r)
  <tr @if($selected && $r['period'] === $selected['period']) style="background:var(--accent-soft)" @endif>
    <td class="lead" data-label="Period"><a class="strong" href="{{ route('taxes.index', ['country' => $country->id, 'period' => $r['period']]) }}#register">{{ $r['label'] }}</a></td>
    <td class="num" data-label="Invoices">{{ $r['invoices']->count() }}</td>
    <td class="num" data-label="Taxable sales">{{ money($r['net'], $cur, false) }}</td>
    <td class="num" data-label="{{ $tn }} charged">{{ money($r['vat'], $cur, false) }}</td>
    <td class="num" data-label="Remitted">{{ money($r['paid'], $cur, false) }}@if($r['pending'])<span class="sub2">{{ money($r['pending'], $cur, false) }} in approval</span>@endif</td>
    <td class="num strong" data-label="Balance" @if($r['status'] === 'Overdue') style="color:var(--bad)" @endif>{{ money($r['balance'], $cur, false) }}</td>
    <td data-label="Due by">{{ fdate($r['due']) }}</td>
    <td data-label="Status">@include('partials.pill', ['label' => $r['status']])</td>
    <td>@if($r['balance'] > 0 && $r['status'] !== 'Open period')@can('expenses.create')<button class="btn sm" data-open-modal="taxPay" data-period="{{ $r['period'] }}" data-amount="{{ $r['balance'] }}" data-label="{{ $r['label'] }}">Record payment</button>@endcan @endif</td>
  </tr>
@endforeach
</tbody></table></div></section>

<section class="card" id="register" style="margin-bottom:16px"><div class="card-h"><div><h3>{{ $tn }} invoice register · {{ $selected['label'] ?? '' }}</h3><p class="small muted">Official invoices issued in the period, the list your return is built from</p></div></div>
@if($selected && $selected['invoices']->count())
<div class="tw"><table class="tbl cards"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Customer TIN</th><th class="num">Net</th><th class="num">{{ $tn }}</th><th class="num">Total</th></tr></thead><tbody>
@foreach($selected['invoices'] as $i)
  <tr><td class="lead" data-label="Invoice"><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a></td><td data-label="Date">{{ fdate($i->issue_date) }}</td><td data-label="Customer">{{ $i->customer->company ?: $i->customer->name }}</td>
    <td class="mono" data-label="TIN">{{ $i->customer->tax_id ?: '—' }}</td><td class="num" data-label="Net">{{ money($i->subtotal - $i->discount_total, $cur, false) }}</td><td class="num" data-label="{{ $tn }}">{{ money($i->tax_total, $cur, false) }}</td><td class="num" data-label="Total">{{ money($i->total, $cur, false) }}</td></tr>
@endforeach
<tr class="tt"><td colspan="4" class="strong">Period total</td><td class="num strong">{{ money($selected['net'], $cur, false) }}</td><td class="num strong">{{ money($selected['vat'], $cur, false) }}</td><td></td></tr>
</tbody></table></div>
@else<div class="empty">No official invoices in this period.</div>@endif
</section>

<div class="grid g-2">
  <section class="card"><div class="card-h"><div><h3>Tax payments</h3><p class="small muted">Every Tax-category expense: VAT/TVA returns, PAYE, withholding and other taxes</p></div></div>
  @if($pending->count() || $other->count() || $rows->sum('paid'))
  <div class="tw"><table class="tbl cards"><thead><tr><th>Payment</th><th>Paid on</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead><tbody>
  @foreach($pending->merge($other)->unique('id')->sortByDesc('spent_on') as $e)
    <tr><td class="lead" data-label="Payment"><span class="strong">{{ $e->description }}</span><span class="sub2 mono">{{ $e->number }}@if($e->tax_period) · period {{ $e->tax_period }}@endif</span></td><td data-label="Paid on">{{ fdate($e->spent_on) }}</td><td class="num" data-label="Amount">{{ money($e->amount, $cur, false) }}</td>
      <td data-label="Status">@include('partials.pill', ['label' => ucfirst($e->status)])</td>
      <td>@if($e->status !== 'paid')@can('expenses.approve')@if($e->submitted_by !== auth()->id())<form class="inline" method="post" action="{{ route('taxes.approve', $e) }}">@csrf<button class="btn sm pri">Approve &amp; mark paid</button></form>@else<span class="small muted">Needs another approver</span>@endif @endcan @endif</td></tr>
  @endforeach
  </tbody></table></div>
  @else<div class="empty">No PAYE, withholding or pending tax payments. VAT/TVA returns you record appear in the monthly table once paid.</div>@endif
  </section>

  <section class="card pad stack" style="gap:12px"><h3>Tax rates &amp; filing days</h3>
    <form method="post" action="{{ route('taxes.settings') }}" class="stack" style="gap:8px">@csrf
    @foreach($countries as $c)
      <div class="row" style="flex-wrap:nowrap;gap:8px"><span style="flex:1;font-weight:500">{{ $c->name }} · {{ $c->tax_name }}</span>
        <input class="inp" style="max-width:88px;text-align:right" type="number" step="any" min="0" max="100" name="countries[{{ $c->id }}][tax_rate]" value="{{ (float) $c->tax_rate }}" aria-label="{{ $c->name }} tax rate" @cannot('settings.manage') disabled @endcannot><span class="small muted">%</span>
        <input class="inp" style="max-width:66px;text-align:right" type="number" min="1" max="28" name="countries[{{ $c->id }}][vat_filing_day]" value="{{ $c->vat_filing_day }}" aria-label="{{ $c->name }} filing day" @cannot('settings.manage') disabled @endcannot><span class="small muted">th</span></div>
    @endforeach
    @can('settings.manage')<div><button class="btn sm pri">Save tax settings</button></div>@endcan
    </form>
    <p class="small muted">The rate applies to new invoice lines; issued invoices keep theirs. A payment counts as remitted once it is marked paid, and the same payments feed the Tax line in <a href="{{ route('budget.index', ['country' => $country->id]) }}">budget monitoring</a>.</p>
  </section>
</div>

@can('expenses.create')
<dialog class="modal-d" id="taxPay"><form method="post" action="{{ route('taxes.store') }}">@csrf
  <input type="hidden" name="country_id" value="{{ $country->id }}">
  <div class="modal-h"><h2>Record tax payment · {{ $country->name }}</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><div class="fg">
    <div class="f"><label for="tp-period">Tax period</label><input id="tp-period" name="tax_period" type="month" value="{{ ($owed->first() ?? $open)['period'] ?? now()->format('Y-m') }}"><span class="hint">The month this return covers. Leave blank for PAYE or other taxes.</span></div>
    <div class="f"><label for="tp-date">Paid on</label><input id="tp-date" name="spent_on" type="date" value="{{ now($country->timezone)->toDateString() }}" required></div>
    <div class="f full"><label for="tp-desc">Description</label><input id="tp-desc" name="description" required maxlength="190" value="{{ $tn }} return, {{ ($owed->first() ?? $open)['label'] ?? '' }}"><span class="hint">Include “{{ $tn }}” for a {{ $tn }} return so it is matched to the period.</span></div>
    <div class="f"><label for="tp-amt">Amount ({{ $cur }})</label><input id="tp-amt" name="amount" type="number" step="any" min="0" required value="{{ $owed->first()['balance'] ?? '' }}"></div>
    <div class="f"><label for="tp-method">Payment method</label><select id="tp-method" name="payment_method">@foreach(config('dopay.payment_methods') as $m)<option @selected($m === 'Bank transfer')>{{ $m }}</option>@endforeach</select></div>
    <div class="f"><label for="tp-br">Branch</label><select id="tp-br" name="branch_id">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($b->id === auth()->user()->branch_id)>{{ $b->name }}</option>@endforeach</select></div>
  </div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Record payment</button></div></form></dialog>
<script>
document.addEventListener('click', e => {
  const b = e.target.closest('[data-open-modal="taxPay"][data-period]'); if (!b) return;
  document.getElementById('tp-period').value = b.dataset.period;
  document.getElementById('tp-amt').value = b.dataset.amount;
  document.getElementById('tp-desc').value = @json($tn) + ' return, ' + b.dataset.label;
});
</script>
@endcan
@endsection
