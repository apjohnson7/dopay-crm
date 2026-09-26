@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
<div class="ph"><div><h1>Suppliers</h1><p class="sub">Supplier records feed expenses, expense memos and payment vouchers.</p></div>
  <div class="ph-act">@can('suppliers.manage')<a class="btn pri" href="{{ route('suppliers.create') }}">+ New supplier</a>@endcan</div></div>
<form class="row" style="margin-bottom:12px" method="get"><input class="inp" style="max-width:360px" type="search" name="q" value="{{ $q }}" placeholder="Search company, contact, code or TIN"><button class="btn">Search</button>
  @cannot('suppliers.manage')<span class="small muted">Only administrators and Finance Managers can add or edit suppliers.</span>@endcannot</form>
<section class="card">
@if($suppliers->count())
<div class="tw"><table class="tbl cards"><thead><tr><th>Supplier</th><th>Contact</th><th>Supplies</th><th>Country</th><th>Tax ID</th><th>Bank</th><th>Terms</th><th></th></tr></thead><tbody>
@foreach($suppliers as $s)
  <tr><td class="lead" data-label="Supplier"><b>{{ $s->company }}</b><span class="sub2 mono">{{ $s->code }}</span></td>
    <td data-label="Contact">{{ $s->contact_person ?: '—' }}<span class="sub2">{{ $s->phone ?: $s->email }}</span></td>
    <td data-label="Supplies">{{ $s->supplies ?: '—' }}</td>
    <td data-label="Country">{{ $s->country->name }}</td>
    <td class="mono" data-label="Tax ID">{{ $s->tax_id ?: '—' }}</td>
    <td class="small" data-label="Bank">{{ $s->bank_details ? preg_replace('/\d(?=[\d ]{4})/', '•', $s->bank_details) : '—' }}</td>
    <td data-label="Terms">{{ $s->payment_terms_days ? 'Net '.$s->payment_terms_days : 'Due on receipt' }}</td>
    <td>@can('suppliers.manage')<a class="btn sm" href="{{ route('suppliers.edit', $s) }}">Edit</a>@endcan</td></tr>
@endforeach
</tbody></table></div>
{{ $suppliers->links() }}
@else<div class="empty">No suppliers yet.@can('suppliers.manage') <a href="{{ route('suppliers.create') }}">Add the first one</a>.@endcan</div>@endif
</section>
<p class="note" style="margin-top:12px">Bank details are stored encrypted and masked in this list. Every change to them is logged and the Finance Manager is notified.</p>
@endsection
