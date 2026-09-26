@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div class="ph"><div><h1>Customers & members</h1><p class="sub">One record per customer. Invoices, receipts and statements pull their details from here.</p></div>
  <div class="ph-act">@can('customers.manage')<a class="btn pri" href="{{ route('customers.create') }}">New customer</a>@endcan</div></div>
<form class="row" style="margin-bottom:12px"><input class="inp" name="q" value="{{ $q }}" style="max-width:380px" placeholder="Search name, ID, phone, email, company or TIN"><button class="btn">Search</button></form>
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>Customer</th><th>Phone</th><th>Category</th><th>Branch</th><th class="num">Credit limit</th></tr></thead><tbody>
@forelse($customers as $c)
  <tr class="click" data-href="{{ route('customers.show', $c) }}"><td class="lead" data-label="Customer"><b>{{ $c->displayName() }}</b><span class="sub2">@if($c->company){{ $c->name }} · @endif<span class="mono">{{ $c->code }}</span></span></td><td data-label="Phone">{{ $c->phone }}</td><td data-label="Category"><span class="tag">{{ $c->category }}</span></td><td data-label="Branch">{{ $c->branch->name }}</td><td class="num" data-label="Credit limit">{{ money($c->credit_limit, $c->currency_code) }}</td></tr>
@empty <tr><td colspan="5" class="empty">No customers match.</td></tr> @endforelse
</tbody></table></div>{{ $customers->links() }}</section>
@endsection
