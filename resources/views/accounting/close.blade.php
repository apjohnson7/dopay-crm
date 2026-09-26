@extends('layouts.app')
@section('title', 'Month-end close')
@section('content')
@include('accounting._head', ['title' => 'Month-end close', 'sub' => 'Close each month once the books are right. A closed month is locked: nothing dated in it can be added or changed until it is reopened with a second person’s approval.'])
@php $label = \Carbon\Carbon::createFromFormat('!Y-m', $next)->format('F Y'); $ready = collect($checks)->every(fn ($c) => ! $c['required'] || $c['ok']);
  $ended = \Carbon\Carbon::createFromFormat('!Y-m', $next)->endOfMonth()->isPast(); @endphp
<div class="grid g-main">
  <section class="card"><div class="card-h"><div><h3>Close {{ $label }}</h3><p class="small muted">{{ $country->legal_entity }}</p></div>@include('partials.pill', ['label' => $ready && $ended ? 'Ready' : 'Open period'])</div>
    @foreach($checks as $c)
      <div class="pad row" style="gap:12px;align-items:flex-start;border-bottom:1px solid var(--grid)"><span class="avt {{ $c['ok'] ? '' : 'y' }}" style="width:28px;height:28px">{{ $c['ok'] ? '✓' : '!' }}</span><div><b>{{ $c['label'] }}</b><div class="small muted">{{ $c['detail'] }}</div></div></div>
    @endforeach
    <div class="pad row" style="justify-content:space-between">
      <span class="small muted">{{ ! $ended ? $label.' has not ended yet.' : ($ready ? 'All required steps are done.' : 'Finish the steps above first.') }}</span>
      @can('books.close')<form method="post" action="{{ route('accounting.close.store') }}" class="inline">@csrf<input type="hidden" name="country_id" value="{{ $country->id }}"><input type="hidden" name="period" value="{{ $next }}"><button class="btn pri" @disabled(! $ready || ! $ended)>Close {{ $label }}</button></form>@endcan
    </div>
  </section>
  <section class="card pad stack" style="gap:12px;align-self:start"><h3>Reopen a month</h3>
    @if($country->books_closed_through)
      <p class="small">Books are closed through <b>{{ \Carbon\Carbon::createFromFormat('!Y-m', $country->books_closed_through)->format('F Y') }}</b>. Reopening needs a reason and a second person’s PIN, and is recorded in the audit trail.</p>
      @can('books.close')<form method="post" action="{{ route('accounting.close.reopen') }}">@csrf<input type="hidden" name="country_id" value="{{ $country->id }}">@include('partials.authorize', ['id' => 'reopen', 'authorizers' => $authorizers])<button class="btn danger" style="margin-top:10px">Reopen {{ \Carbon\Carbon::createFromFormat('!Y-m', $country->books_closed_through)->format('F Y') }}</button></form>@endcan
    @else<p class="small muted">No month has been closed yet.</p>@endif
  </section>
</div>
@endsection
