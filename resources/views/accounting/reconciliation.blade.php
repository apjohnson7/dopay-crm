@extends('layouts.app')
@section('title', 'Reconciliation')
@section('content')
@include('accounting._head', ['title' => 'Reconciliation', 'sub' => 'Match the bank or mobile money statement to the books line by line. Whatever stays unmatched is a missing entry, a timing difference or an error.'])
@php $cur = $country->currency_code; $m = fn ($v) => money($v, $cur, false); $canWork = auth()->user()->can('journals.create') && ! $confirmed;
  $hidden = '<input type="hidden" name="account_id" value="'.$account?->id.'"><input type="hidden" name="period" value="'.$period.'">'; @endphp
@if(! $account)<section class="card empty">This country has no bank, mobile money or cash account yet.</section>@else
<form class="row" style="margin-bottom:12px"><input type="hidden" name="country" value="{{ $country->id }}">
  <select class="sel" name="account" data-autosubmit aria-label="Account">@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($a->id === $account->id)>{{ $a->label() }}</option>@endforeach</select>
  <input class="inp" type="month" name="period" value="{{ $period }}" style="max-width:170px" data-autosubmit aria-label="Month">
</form>
@php $matched = $statement->whereNotNull('journal_line_id'); $open = $statement->whereNull('journal_line_id'); @endphp
<div class="mstrip">
  <div><span>Statement lines</span><b>{{ $statement->count() }}</b></div>
  <div class="ok"><span>Matched</span><b>{{ $matched->count() }}</b></div>
  <div class="{{ $open->count() ? 'warn' : '' }}"><span>Statement only</span><b>{{ $open->count() }} · {{ $m($open->sum('amount')) }}</b></div>
  <div class="{{ $book->count() ? 'warn' : '' }}"><span>Books only</span><b>{{ $book->count() }} · {{ $m($book->sum(fn ($l) => (float) $l->debit - (float) $l->credit)) }}</b></div>
  <div class="{{ $confirmed ? 'ok' : '' }}"><span>Status</span><b>{{ $confirmed ? 'Confirmed by '.$confirmed->confirmer->name : ($statement->isEmpty() ? 'No statement yet' : 'In progress') }}</b></div>
</div>
@if($canWork)
<div class="row" style="margin-bottom:14px">
  <form method="post" action="{{ route('accounting.reconcile', 'import') }}" enctype="multipart/form-data" class="row">@csrf{!! $hidden !!}<input type="file" name="file" accept=".csv,text/csv" required aria-label="Statement CSV"><button class="btn">Import statement (CSV)</button></form>
  <form method="post" action="{{ route('accounting.reconcile', 'auto') }}" class="inline">@csrf{!! $hidden !!}<button class="btn pri">Auto-match</button></form>
  @if($statement->isNotEmpty() && $open->isEmpty())<form method="post" action="{{ route('accounting.reconcile', 'confirm') }}" class="inline">@csrf{!! $hidden !!}<button class="btn pri">Confirm {{ \Carbon\Carbon::createFromFormat('!Y-m', $period)->format('F') }}</button></form>@endif
  <span class="small muted">CSV columns: date, description, amount (money out negative), reference.</span>
</div>
@endif
<div class="grid g-2" style="margin-bottom:16px">
  <section class="card"><div class="card-h"><h3>On the statement, not in the books</h3></div>
    @forelse($open as $s)
      <div class="pad" style="border-bottom:1px solid var(--grid)"><div class="row" style="justify-content:space-between"><div><b>{{ $s->description }}</b><div class="small muted">{{ fdate($s->line_date) }} · {{ $s->reference }}</div></div><b style="color:{{ $s->amount < 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $m($s->amount) }}</b></div>
      @if($canWork)
        @php $cands = $book->filter(fn ($l) => abs(((float) $l->debit - (float) $l->credit) - (float) $s->amount) < 0.005); @endphp
        @if($cands->count())<form method="post" action="{{ route('accounting.reconcile', 'match') }}" class="row" style="margin-top:8px">@csrf{!! $hidden !!}<input type="hidden" name="line_id" value="{{ $s->id }}">
          <select class="sel" name="journal_line_id" aria-label="Book entry">@foreach($cands as $l)<option value="{{ $l->id }}">{{ fdate($l->entry->entry_date) }} · {{ \Illuminate\Support\Str::limit($l->entry->memo, 50) }}</option>@endforeach</select><button class="btn sm">Match</button></form>@endif
        <form method="post" action="{{ route('accounting.reconcile', 'book') }}" class="row" style="margin-top:8px">@csrf{!! $hidden !!}<input type="hidden" name="line_id" value="{{ $s->id }}">
          <select class="sel" name="counter_account_id" aria-label="Post to">@foreach($counterAccounts as $a)<option value="{{ $a->id }}" @selected($a->role === ($s->amount < 0 ? 'exp_bank' : 'suspense'))>{{ $a->label() }}</option>@endforeach</select>
          <input class="inp" name="memo" required maxlength="190" value="{{ $s->amount < 0 ? 'Bank charges' : 'Deposit to identify' }}" style="max-width:180px"><button class="btn sm">Post &amp; match</button></form>
      @endif
      </div>
    @empty<div class="empty">Nothing unmatched on the statement.</div>@endforelse
  </section>
  <section class="card"><div class="card-h"><h3>In the books, not on the statement</h3></div>
    @forelse($book as $l)
      <div class="pad row" style="justify-content:space-between;border-bottom:1px solid var(--grid)"><div><b>{{ \Illuminate\Support\Str::limit($l->entry->memo, 60) }}</b><div class="small muted">{{ fdate($l->entry->entry_date) }} · <a href="{{ route('accounting.journal', $l->journal_entry_id) }}">{{ $l->entry->number }}</a></div></div><b>{{ $m((float) $l->debit - (float) $l->credit) }}</b></div>
    @empty<div class="empty">Every book entry is on the statement.</div>@endforelse
  </section>
</div>
@if($matched->count())
<section class="card"><div class="card-h"><h3>Matched</h3></div><div class="tw"><table class="tbl cards"><thead><tr><th>Date</th><th>Statement line</th><th class="num">Amount</th><th>Book entry</th><th></th></tr></thead><tbody>
  @foreach($matched as $s)<tr><td data-label="Date">{{ fdate($s->line_date) }}</td><td class="lead" data-label="Line">{{ $s->description }}<span class="sub2">{{ $s->reference }}</span></td><td class="num" data-label="Amount">{{ $m($s->amount) }}</td>
    <td data-label="Entry"><a class="mono" href="{{ route('accounting.journal', $s->journalLine->journal_entry_id) }}">{{ $s->journalLine->entry->number }}</a></td>
    <td>@if($canWork)<form method="post" action="{{ route('accounting.reconcile', 'unmatch') }}" class="inline">@csrf{!! $hidden !!}<input type="hidden" name="line_id" value="{{ $s->id }}"><button class="btn sm ghost">Unmatch</button></form>@endif</td></tr>@endforeach
</tbody></table></div></section>
@endif
@endif
@endsection
