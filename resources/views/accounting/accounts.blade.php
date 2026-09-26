@extends('layouts.app')
@section('title', 'Chart of accounts')
@section('content')
@include('accounting._head', ['title' => 'Chart of accounts', 'sub' => 'Every invoice, payment, expense and approved form posts to these accounts automatically. System accounts can’t be removed.'])
<div class="grid g-main">
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>Code</th><th>Account</th><th>Type</th><th>Statement line</th><th class="num">Balance ({{ $country->currency_code }})</th></tr></thead><tbody>
@foreach($accounts as $r)@php $a = $r['account']; @endphp
<tr data-href="{{ route('accounting.ledger', ['country' => $country->id, 'account' => $a->id]) }}" class="click"><td class="mono" data-label="Code">{{ $a->code }}</td><td class="lead" data-label="Account"><b>{{ $a->name }}</b>@unless($a->is_system)<span class="sub2">Added by your team</span>@endunless</td><td data-label="Type">{{ ucfirst($a->type) }}</td><td data-label="Line">{{ $a->statement_line }}</td><td class="num" data-label="Balance">{{ money($r['balance'], $country->currency_code, false) }}</td></tr>
@endforeach
</tbody></table></div></section>
@can('journals.approve')
<form method="post" action="{{ route('accounting.accounts.store') }}" class="card pad stack" style="gap:10px;align-self:start">@csrf<input type="hidden" name="country_id" value="{{ $country->id }}">
  <h3>Add an account</h3>
  <div class="fg">
    <div class="f"><label for="code">Code</label><input id="code" name="code" required maxlength="10" value="{{ old('code') }}"></div>
    <div class="f"><label for="type">Type</label><select id="type" name="type">@foreach(['asset', 'liability', 'equity', 'income', 'expense'] as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach</select></div>
    <div class="f full"><label for="name">Name</label><input id="name" name="name" required maxlength="120" value="{{ old('name') }}"></div>
    <div class="f full"><label for="statement_line">Statement line</label><input id="statement_line" name="statement_line" required maxlength="60" placeholder="e.g. Operating expenses" value="{{ old('statement_line') }}"></div>
  </div>
  <button class="btn pri">Add account</button>
  <p class="small muted">Ask your external accountant before changing the chart; codes can’t be reused.</p>
</form>
@endcan
</div>
@endsection
