@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php $u = auth()->user(); $h = now($u->country()?->timezone ?? 'UTC')->hour; @endphp
<div class="ph"><div>
  <h1>{{ $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening') }}, {{ strtok($u->name, ' ') }}</h1>
  <p class="sub">{{ $u->branch?->name ?? 'All countries' }} · {{ now($u->country()?->timezone ?? 'UTC')->format('l, j F Y') }} · {{ $u->roleName() }}@if(!$scopeCountryId) · figures consolidated in {{ $currency }}@endif</p>
</div></div>

<div class="qa" style="margin-bottom:16px">
  @can('invoices.create')<a class="btn" href="{{ route('invoices.create') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">New invoice</a>@endcan
  @can('customers.manage')<a class="btn" href="{{ route('customers.create') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">New customer</a>@endcan
  @can('payments.record')<a class="btn" href="{{ route('payments.create') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Record payment</a>@endcan
  @can('forms.submit')
    <a class="btn" href="{{ route('forms.create', 'A') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Expense memo</a>
    <a class="btn" href="{{ route('forms.create', 'B') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Petty cash voucher</a>
    <a class="btn" href="{{ route('forms.create', 'K') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Cash advance</a>
  @endcan
  <a class="btn" href="{{ route('messages.index') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Team messages</a>
  @can('reports.view')<a class="btn" href="{{ route('budget.index') }}" style="height:auto;padding:14px;flex-direction:column;align-items:flex-start">Budget monitoring</a>@endcan
</div>

<div class="grid g-kpi" style="margin-bottom:16px">
  <div class="card kpi hero"><div class="kpi-stripe"></div><span class="lbl">Total sales · {{ now()->year }}</span><div class="v"><small>{{ $currency }}</small>{{ money($kpi['sales'], $currency, false) }}</div><span class="d">{{ $kpi['invoice_count'] }} official invoices</span></div>
  <div class="card kpi warn"><div class="kpi-stripe"></div><span class="lbl">Outstanding</span><div class="v"><small>{{ $currency }}</small>{{ money($kpi['outstanding'], $currency, false) }}</div><span class="d">{{ $kpi['outstanding_count'] }} invoices with a balance</span></div>
  <div class="card kpi bad"><div class="kpi-stripe"></div><span class="lbl">Overdue</span><div class="v"><small>{{ $currency }}</small>{{ money($kpi['overdue'], $currency, false) }}</div><span class="d">{{ $kpi['overdue_count'] }} past due date</span></div>
  <div class="card kpi ok"><div class="kpi-stripe"></div><span class="lbl">Payments today</span><div class="v"><small>{{ $currency }}</small>{{ money($kpi['payments_today'], $currency, false) }}</div><span class="d">{{ $kpi['payments_today_count'] }} received</span></div>
</div>

<div class="grid g-main">
  <section class="card"><div class="card-h"><h3>Invoices requiring attention</h3><a class="lnk" href="{{ route('invoices.index', ['status' => 'Overdue']) }}">All overdue</a></div>
    <div class="tw"><table class="tbl cards"><thead><tr><th>Invoice</th><th>Due</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
    @forelse($attention as $i)
      <tr class="click" data-href="{{ route('invoices.show', $i) }}"><td class="lead" data-label="Invoice"><span class="mono strong">{{ $i->number }}</span><span class="sub2">{{ $i->customer->displayName() }}</span></td><td data-label="Due">{{ fdate($i->due_date) }}</td><td class="num" data-label="Balance">{{ money($i->balance(), $i->currency_code) }}</td><td data-label="Status">@include('partials.pill', ['label' => $i->displayStatus()])</td></tr>
    @empty <tr><td colspan="4" class="empty">All clear. Nothing is overdue.</td></tr> @endforelse
    </tbody></table></div>
  </section>
  <section class="card"><div class="card-h"><h3>What needs attention today</h3></div><div class="alerts">
    @if($formsAwaiting->count())<a class="alert" href="{{ route('forms.index', ['status' => 'mine']) }}"><span class="sv warn"></span><div><b>Forms awaiting your signature</b><span>{{ $formsAwaiting->take(2)->pluck('reference')->implode(', ') }}</span></div><span class="n">{{ $formsAwaiting->count() }}</span></a>@endif
    @if($taxAlerts->count())<a class="alert" href="{{ route('taxes.index', ['country' => $taxAlerts->first()['country']->id]) }}"><span class="sv {{ $taxAlerts->contains(fn ($a) => $a['row']['status'] === 'Overdue') ? 'bad' : 'warn' }}"></span><div><b>{{ $taxAlerts->contains(fn ($a) => $a['row']['status'] === 'Overdue') ? 'Tax returns overdue' : 'Tax returns due soon' }}</b><span>{{ $taxAlerts->map(fn ($a) => $a['country']->name.' '.$a['row']['due']->copy()->subMonthNoOverflow()->format('M').' · due '.fdate($a['row']['due']))->implode(', ') }}</span></div><span class="n">{{ $taxAlerts->count() }}</span></a>@endif
    @if($pendingInvoices->count())<a class="alert" href="{{ route('invoices.index', ['status' => 'Pending approval']) }}"><span class="sv warn"></span><div><b>Invoices to approve</b><span>{{ $pendingInvoices->take(2)->pluck('number')->implode(', ') }}</span></div><span class="n">{{ $pendingInvoices->count() }}</span></a>@endif
    @if($advancesOverdue->count())<a class="alert" href="{{ route('forms.index', ['status' => 'open', 'type' => 'K']) }}"><span class="sv bad"></span><div><b>Cash advances past liquidation date</b><span>Receipts are overdue</span></div><span class="n">{{ $advancesOverdue->count() }}</span></a>@endif
    @if($unread)<a class="alert" href="{{ route('messages.index') }}"><span class="sv info"></span><div><b>Unread team messages</b><span>From colleagues in your branches</span></div><span class="n">{{ $unread }}</span></a>@endif
    @if($expensesPending)<div class="alert"><span class="sv warn"></span><div><b>Expenses awaiting review</b><span>Submitted or reviewed</span></div><span class="n">{{ $expensesPending }}</span></div>@endif
    @if($expiringDocs)<div class="alert"><span class="sv warn"></span><div><b>Documents expiring in 30 days</b><span>Renew contracts and licences</span></div><span class="n">{{ $expiringDocs }}</span></div>@endif
  </div></section>
</div>

<section class="card" style="margin-top:16px"><div class="card-h"><h3>Recent payments</h3><a class="lnk" href="{{ route('payments.index') }}">All payments</a></div>
  <div class="feed">@forelse($recentPayments as $p)
    <div class="feed-i"><div class="av">₵</div><div class="t"><b>{{ $p->customer->displayName() }}</b><span>{{ fdate($p->paid_on) }} · {{ $p->method }} · {{ $p->status }}</span></div><div class="a">{{ money($p->amount, $p->currency_code) }}</div></div>
  @empty <div class="empty">No payments yet.</div> @endforelse</div>
</section>
@endsection
