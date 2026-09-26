@extends('layouts.app')
@section('title', $entry->number)
@section('content')
<div class="ph"><div><h1 class="mono" style="font-size:22px">{{ $entry->number }}</h1><p class="sub">{{ $entry->memo }} · {{ fdate($entry->entry_date) }} · {{ ucfirst($entry->kind) }} · posted by {{ $entry->poster?->name ?? 'System' }}@if($entry->authorized_by) · authorized@endif</p></div>
  <div class="ph-act"><a class="btn" href="{{ route('accounting.journals', ['country' => $entry->country_id]) }}">All journals</a></div></div>
<section class="card"><div class="tw"><table class="tbl"><thead><tr><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead><tbody>
@foreach($entry->lines as $l)<tr><td><a href="{{ route('accounting.ledger', ['country' => $entry->country_id, 'account' => $l->ledger_account_id]) }}">{{ $l->account->label() }}</a></td><td>{{ $l->description }}</td><td class="num">{{ (float) $l->debit ? money($l->debit, $entry->country->currency_code, false) : '' }}</td><td class="num">{{ (float) $l->credit ? money($l->credit, $entry->country->currency_code, false) : '' }}</td></tr>@endforeach
<tr class="tt"><td colspan="2" class="strong">Total</td><td class="num strong">{{ money($entry->lines->sum('debit'), $entry->country->currency_code, false) }}</td><td class="num strong">{{ money($entry->lines->sum('credit'), $entry->country->currency_code, false) }}</td></tr>
</tbody></table></div></section>
@if($entry->source_type)<p class="small muted" style="margin-top:10px">Posted automatically from {{ class_basename($entry->source_type) }} #{{ $entry->source_id }}.</p>@endif
@endsection
