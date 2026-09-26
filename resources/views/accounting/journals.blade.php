@extends('layouts.app')
@section('title', 'Journals')
@section('content')
@include('accounting._head', ['title' => 'Journals', 'sub' => 'Automatic postings from invoices, payments and expenses, plus manual journals. Entries are never edited: a mistake is corrected with a new entry.',
  'actions' => auth()->user()->can('journals.create') ? '<button class="btn pri" data-open-modal="newJournal">+ New journal</button>' : null])
<form class="row" style="margin-bottom:12px"><input type="hidden" name="country" value="{{ $country->id }}">
  @foreach(['' => 'All', 'auto' => 'Automatic', 'manual' => 'Manual', 'opening' => 'Opening', 'revaluation' => 'Revaluation'] as $k => $l)<a class="chip {{ ($kind ?? '') === $k ? 'on' : '' }}" style="text-decoration:none" href="{{ route('accounting.journals', ['country' => $country->id, 'kind' => $k ?: null]) }}">{{ $l }}</a>@endforeach
  <input class="inp" style="max-width:260px;margin-left:auto" type="search" name="q" value="{{ request('q') }}" placeholder="Search number or memo"></form>
<section class="card">
@if($entries->count())
<div class="tw"><table class="tbl cards"><thead><tr><th>Entry</th><th>Date</th><th>Kind</th><th>Posted by</th><th class="num">Amount ({{ $country->currency_code }})</th></tr></thead><tbody>
@foreach($entries as $e)
<tr class="click" data-href="{{ route('accounting.journal', $e) }}"><td class="lead" data-label="Entry"><span class="mono strong">{{ $e->number }}</span><span class="sub2">{{ $e->memo }}</span></td><td data-label="Date">{{ fdate($e->entry_date) }}</td><td data-label="Kind">{{ ucfirst($e->kind) }}</td><td data-label="By">{{ $e->poster?->name ?? 'System' }}</td><td class="num" data-label="Amount">{{ money($e->total(), $country->currency_code, false) }}</td></tr>
@endforeach
</tbody></table></div>{{ $entries->links() }}
@else<div class="empty">No journal entries yet.</div>@endif
</section>

@can('journals.create')
<dialog class="modal-d" id="newJournal" style="max-width:860px" @if($errors->any()) open @endif><form method="post" action="{{ route('accounting.journals.store') }}">@csrf<input type="hidden" name="country_id" value="{{ $country->id }}">
  <div class="modal-h"><h2>New journal · {{ $country->name }}</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b">
    <div class="fg"><div class="f"><label for="jd">Date</label><input id="jd" name="entry_date" type="date" required value="{{ old('entry_date', now($country->timezone)->toDateString()) }}"></div>
      <div class="f"><label for="jm">Memo</label><input id="jm" name="memo" required maxlength="250" value="{{ old('memo') }}" placeholder="e.g. September depreciation"></div></div>
    <div class="tw" style="margin-top:12px"><table class="tbl" id="jlines" data-lines><thead><tr><th>Account</th><th class="num">Debit</th><th class="num">Credit</th><th>Description</th></tr></thead><tbody>
      @for($i = 0; $i < 4; $i++)<tr><td><select name="lines[{{ $i }}][account_id]"><option value="">Choose…</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old("lines.$i.account_id") == $a->id)>{{ $a->label() }}</option>@endforeach</select></td>
        <td><input class="inp" name="lines[{{ $i }}][debit]" type="number" step="any" min="0" value="{{ old("lines.$i.debit") }}"></td><td><input class="inp" name="lines[{{ $i }}][credit]" type="number" step="any" min="0" value="{{ old("lines.$i.credit") }}"></td>
        <td><input class="inp" name="lines[{{ $i }}][description]" maxlength="190" value="{{ old("lines.$i.description") }}"></td></tr>@endfor
    </tbody></table>
    <template><tr><td><select name="lines[__][account_id]"><option value="">Choose…</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></td><td><input class="inp" name="lines[__][debit]" type="number" step="any" min="0"></td><td><input class="inp" name="lines[__][credit]" type="number" step="any" min="0"></td><td><input class="inp" name="lines[__][description]" maxlength="190"></td></tr></template></div>
    <button type="button" class="btn sm" data-add-line="jlines" style="margin-top:8px">+ Add line</button>
    <p class="small muted" style="margin:12px 0 6px">Debits must equal credits. Journals above USD {{ number_format($threshold) }} need a second person’s authorization:</p>
    @include('partials.authorize', ['id' => 'jnl', 'required' => false, 'authorizers' => $authorizers])
  </div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Post journal</button></div></form></dialog>
@endcan
@endsection
