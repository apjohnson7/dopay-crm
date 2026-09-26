@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<div class="ph"><div><h1>Invoices</h1><p class="sub">Create → preview → approve → generate → send. Numbers follow each country’s sequence.</p></div>
  <div class="ph-act">@can('invoices.create')<a class="btn pri" href="{{ route('invoices.create') }}">New invoice</a>@endcan</div></div>
<div class="row" style="margin-bottom:12px">@foreach(['All', 'Draft', 'Pending approval', 'Approved', 'Sent', 'Partially paid', 'Paid', 'Overdue', 'Cancelled'] as $s)<a class="chip {{ $filter === $s ? 'on' : '' }}" href="?status={{ urlencode($s) }}" style="text-decoration:none">{{ $s }}</a>@endforeach</div>
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="num">Total</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
@forelse($invoices as $i)
  <tr class="click" data-href="{{ route('invoices.show', $i) }}"><td class="lead" data-label="Invoice"><span class="mono strong">{{ $i->number }}</span><span class="sub2">{{ $i->customer->displayName() }}</span></td><td data-label="Date">{{ fdate($i->issue_date) }}</td><td data-label="Due">{{ fdate($i->due_date) }}</td><td class="num" data-label="Total">{{ money($i->total, $i->currency_code) }}</td><td class="num" data-label="Balance">{{ money($i->balance(), $i->currency_code) }}</td><td data-label="Status">@include('partials.pill', ['label' => $i->displayStatus()])</td></tr>
@empty <tr><td colspan="6" class="empty">No invoices with this status.</td></tr> @endforelse
</tbody></table></div></section>
@endsection
