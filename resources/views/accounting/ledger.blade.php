@extends('layouts.app')
@section('title', 'General ledger')
@section('content')
@include('accounting._head', ['title' => 'General ledger', 'sub' => 'Every posting to one account, with a running balance.'])
<form class="row" style="margin-bottom:12px"><input type="hidden" name="country" value="{{ $country->id }}">
  <select class="sel" name="account" data-autosubmit aria-label="Account">@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->label() }}</option>@endforeach</select>
  <input class="inp" type="date" name="from" value="{{ $from }}" style="max-width:170px" aria-label="From"><input class="inp" type="date" name="to" value="{{ $to }}" style="max-width:170px" aria-label="To"><button class="btn">Show</button></form>
@if($account)
@php $cur = $country->currency_code; $run = $opening; @endphp
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>Date</th><th>Entry</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>
<tr class="tt"><td colspan="4">Opening balance {{ fdate($from) }}</td><td class="num">{{ money($account->natural($opening), $cur, false) }}</td></tr>
@foreach($lines as $l)@php $run += (float) $l->debit - (float) $l->credit; @endphp
<tr class="click" data-href="{{ route('accounting.journal', $l->journal_entry_id) }}"><td data-label="Date">{{ fdate($l->entry->entry_date) }}</td><td class="lead" data-label="Entry"><span class="mono">{{ $l->entry->number }}</span><span class="sub2">{{ $l->entry->memo }}</span></td>
  <td class="num" data-label="Debit">{{ (float) $l->debit ? money($l->debit, $cur, false) : '' }}</td><td class="num" data-label="Credit">{{ (float) $l->credit ? money($l->credit, $cur, false) : '' }}</td><td class="num" data-label="Balance">{{ money($account->natural($run), $cur, false) }}</td></tr>
@endforeach
</tbody></table></div></section>
@endif
@endsection
