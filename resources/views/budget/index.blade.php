@extends('layouts.app')
@section('title', 'Budget monitoring')
@section('content')
<div class="ph"><div><h1>Budget monitoring</h1><p class="sub">{{ $country->legal_entity }} · {{ $year }} · {{ $country->currency_code }}. Budget from the approved Annual Budget (Appendix F); actuals post automatically from paid expenses and memos.</p></div>
  @if($countries->count() > 1)<form><select class="sel" name="country" onchange="this.form.submit()">@foreach($countries as $c)<option value="{{ $c->id }}" @selected($c->id === $country->id)>{{ $c->legal_entity }}</option>@endforeach</select></form>@endif</div>
<div class="row" style="margin-bottom:12px">@foreach(range(1, 12) as $m)<a class="chip {{ !$ytd && $month === $m ? 'on' : '' }}" style="text-decoration:none" href="?country={{ $country->id }}&month={{ $m }}">{{ date('M', mktime(0, 0, 0, $m, 1)) }}</a>@endforeach
  <a class="chip {{ $ytd ? 'on' : '' }}" style="text-decoration:none" href="?country={{ $country->id }}&month={{ $month }}&mode=ytd">Year to date</a></div>
@if(!$report)
  <section class="card empty">No approved {{ $year }} annual budget for {{ $country->legal_entity }} yet. @can('forms.submit')<a href="{{ route('forms.create', 'F') }}">Prepare one</a>.@endcan</section>
@else
  @php $rows = $report['rows']; $tb = $rows->sum('budget'); $ta = $rows->sum('actual'); $cur = $country->currency_code; @endphp
  <div class="grid g-kpi" style="margin-bottom:16px">
    <div class="card kpi acc"><div class="kpi-stripe"></div><span class="lbl">Budget</span><div class="v"><small>{{ $cur }}</small>{{ money($tb, $cur, false) }}</div></div>
    <div class="card kpi ok"><div class="kpi-stripe"></div><span class="lbl">Actual</span><div class="v"><small>{{ $cur }}</small>{{ money($ta, $cur, false) }}</div></div>
    <div class="card kpi {{ $tb - $ta < 0 ? 'bad' : 'ok' }}"><div class="kpi-stripe"></div><span class="lbl">Variance</span><div class="v"><small>{{ $cur }}</small>{{ money($tb - $ta, $cur, false) }}</div></div>
    <div class="card kpi {{ $rows->filter(fn ($r) => $r['budget'] && $r['actual'] > $r['budget'])->count() ? 'bad' : 'ok' }}"><div class="kpi-stripe"></div><span class="lbl">Categories over budget</span><div class="v">{{ $rows->filter(fn ($r) => $r['budget'] && $r['actual'] > $r['budget'])->count() }}</div></div>
  </div>
  <section class="card"><div class="card-h"><h3>{{ $ytd ? 'Year to date' : date('F', mktime(0, 0, 0, $month, 1)).' '.$year }}</h3><a class="btn sm" href="{{ route('forms.show', $report['form']) }}">Open budget {{ $report['form']->reference }}</a></div>
  <div class="tw"><table class="tbl"><thead><tr><th>S/N</th><th>Expense category</th><th class="num">Budget</th><th class="num">Actual</th><th class="num">Variance</th><th class="num">% used</th></tr></thead><tbody>
  @foreach($rows as $r)@php $p = $r['budget'] ? $r['actual'] / $r['budget'] : ($r['actual'] ? 2 : 0); @endphp
    <tr><td>{{ $r['category']->position }}</td><td>{{ $r['category']->name }}</td><td class="num">{{ money($r['budget'], $cur, false) }}</td><td class="num">{{ money($r['actual'], $cur, false) }}</td><td class="num" style="color:{{ $r['budget'] - $r['actual'] < 0 ? 'var(--bad)' : 'inherit' }}">{{ money($r['budget'] - $r['actual'], $cur, false) }}</td>
      <td class="num" style="min-width:140px"><div class="bmbar"><span style="width:{{ min(100, $p * 100) }}%;background:{{ $p > 1 ? 'var(--bad)' : ($p > .9 ? 'var(--warn)' : 'var(--accent)') }}"></span></div><span class="small">{{ $r['budget'] ? round($p * 100).'%' : ($r['actual'] ? 'no budget' : '–') }}</span></td></tr>@endforeach
  <tr class="tt"><td></td><td class="strong">Total expenditure</td><td class="num strong">{{ money($tb, $cur, false) }}</td><td class="num strong">{{ money($ta, $cur, false) }}</td><td class="num strong">{{ money($tb - $ta, $cur, false) }}</td><td class="num strong">{{ $tb ? round($ta / $tb * 100).'%' : '–' }}</td></tr>
  </tbody></table></div></section>
@endif
@endsection
