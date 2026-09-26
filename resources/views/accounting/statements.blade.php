@extends('layouts.app')
@section('title', 'Financial statements')
@section('content')
@include('accounting._head', ['title' => 'Financial statements', 'sub' => 'Built live from the ledger. Click any account to see its postings.'])
@php $cur = $country->currency_code; $m = fn ($v) => money($v, $cur, false);
  $acctLink = fn ($a) => route('accounting.ledger', ['country' => $country->id, 'account' => $a->id, 'from' => $from, 'to' => $to]); @endphp
<form class="row" style="margin-bottom:12px"><input type="hidden" name="country" value="{{ $country->id }}"><input type="hidden" name="report" value="{{ $report }}">
  @foreach(['pl' => 'Profit & loss', 'bs' => 'Balance sheet', 'tb' => 'Trial balance'] as $k => $l)<a class="chip {{ $report === $k ? 'on' : '' }}" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['report' => $k]) }}">{{ $l }}</a>@endforeach
  <span style="margin-left:auto"></span>@if($report === 'pl')<input class="inp" type="date" name="from" value="{{ $from }}" style="max-width:170px" aria-label="From">@endif
  <input class="inp" type="date" name="to" value="{{ $to }}" style="max-width:170px" aria-label="{{ $report === 'pl' ? 'To' : 'As at' }}"><button class="btn">Update</button></form>

@if($pl)
<section class="card"><div class="card-h"><div><h3>Profit &amp; loss</h3><p class="small muted">{{ $country->legal_entity }} · {{ fdate($from) }} – {{ fdate($to) }} · {{ $cur }}</p></div></div>
<div class="tw"><table class="tbl"><tbody>
  @foreach(['income' => 'Income', 'expense' => 'Expenses'] as $side => $label)
    <tr class="tt"><td class="strong">{{ strtoupper($label) }}</td><td></td></tr>
    @foreach($pl[$side] as $line => $rows)
      <tr><td class="strong">{{ $line }}</td><td class="num strong">{{ $m(collect($rows)->sum('amount')) }}</td></tr>
      @foreach($rows as $r)<tr><td style="padding-left:28px"><a href="{{ $acctLink($r['account']) }}">{{ $r['account']->label() }}</a></td><td class="num">{{ $m($r['amount']) }}</td></tr>@endforeach
    @endforeach
    <tr><td class="strong">Total {{ strtolower($label) }}</td><td class="num strong">{{ $m($side === 'income' ? $pl['total_income'] : $pl['total_expense']) }}</td></tr>
  @endforeach
  <tr class="tt"><td class="strong">{{ $pl['net'] >= 0 ? 'Net profit' : 'Net loss' }}</td><td class="num strong" style="color:{{ $pl['net'] < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $m($pl['net']) }}</td></tr>
</tbody></table></div></section>
@endif

@if($bs)
<section class="card"><div class="card-h"><div><h3>Balance sheet</h3><p class="small muted">{{ $country->legal_entity }} · as at {{ fdate($to) }} · {{ $cur }}</p></div>
  @php $diff = round($bs['total_assets'] - $bs['total_liabilities'] - $bs['total_equity'], 2); @endphp
  @include('partials.pill', ['label' => abs($diff) < 0.01 ? 'Balanced' : 'Out of balance'])</div>
<div class="tw"><table class="tbl"><tbody>
  @foreach(['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $side => $label)
    <tr class="tt"><td class="strong">{{ strtoupper($label) }}</td><td></td></tr>
    @foreach($bs[$side] as $line => $rows)
      <tr><td class="strong">{{ $line }}</td><td class="num strong">{{ $m(collect($rows)->sum('amount')) }}</td></tr>
      @foreach($rows as $r)<tr><td style="padding-left:28px"><a href="{{ $acctLink($r['account']) }}">{{ $r['account']->label() }}</a></td><td class="num">{{ $m($r['amount']) }}</td></tr>@endforeach
    @endforeach
    @if($side === 'equity')
      <tr><td style="padding-left:28px">Results of earlier years not yet transferred</td><td class="num">{{ $m($bs['prior_results']) }}</td></tr>
      <tr><td style="padding-left:28px">Result for {{ \Carbon\Carbon::parse($to)->format('Y') }} to date</td><td class="num">{{ $m($bs['current_result']) }}</td></tr>
    @endif
    <tr><td class="strong">Total {{ strtolower($label) }}</td><td class="num strong">{{ $m($bs['total_'.$side]) }}</td></tr>
  @endforeach
  <tr class="tt"><td class="strong">Liabilities + equity</td><td class="num strong">{{ $m($bs['total_liabilities'] + $bs['total_equity']) }}</td></tr>
</tbody></table></div></section>
@endif

@if($tb)
<section class="card"><div class="card-h"><div><h3>Trial balance</h3><p class="small muted">{{ $country->legal_entity }} · as at {{ fdate($to) }} · {{ $cur }}</p></div>
  @include('partials.pill', ['label' => abs($tb->sum('debit') - $tb->sum('credit')) < 0.01 ? 'Balanced' : 'Out of balance'])</div>
<div class="tw"><table class="tbl cards"><thead><tr><th>Code</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
  @foreach($tb as $r)<tr class="click" data-href="{{ $acctLink($r['account']) }}"><td class="mono" data-label="Code">{{ $r['account']->code }}</td><td class="lead" data-label="Account">{{ $r['account']->name }}</td><td class="num" data-label="Debit">{{ $r['debit'] ? $m($r['debit']) : '' }}</td><td class="num" data-label="Credit">{{ $r['credit'] ? $m($r['credit']) : '' }}</td></tr>@endforeach
  <tr class="tt"><td></td><td class="strong">Total</td><td class="num strong">{{ $m($tb->sum('debit')) }}</td><td class="num strong">{{ $m($tb->sum('credit')) }}</td></tr>
</tbody></table></div></section>
@endif
@endsection
