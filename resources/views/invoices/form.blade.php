@extends('layouts.app')
@section('title', $invoice->exists ? 'Edit '.$invoice->number : 'New invoice')
@section('content')
@php $items = old('items', $invoice->exists ? $invoice->items->toArray() : [['quantity' => 1]]); @endphp
<div class="ph"><div><h1>{{ $invoice->exists ? 'Edit '.$invoice->number : 'New invoice' }}</h1><p class="sub">You only type the transaction particulars. Customer details, currency, tax and numbering come from the system.</p></div></div>
<form method="post" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" id="builder" data-cur="{{ $invoice->currency_code }}" data-tax="{{ $invoice->customer?->country?->tax_rate }}" class="stack">@csrf @if($invoice->exists) @method('put') @endif
  <section class="card pad"><div class="bstep"><i>1</i><h3>Customer</h3></div>
    <div class="f"><label for="customer_id">Search or select customer</label>
      <select id="customer_id" name="customer_id" required><option value="">Choose a customer…</option>
        @foreach($customers as $c)
          <option value="{{ $c->id }}" @selected(old('customer_id', $invoice->customer_id) == $c->id) data-name="{{ $c->displayName() }}{{ $c->company ? ' · Attn '.$c->name : '' }}" data-address="{{ $c->address }}, {{ $c->city }}" data-contact="{{ $c->phone }} · {{ $c->email }}" data-tin="{{ $c->tax_id }}" data-terms="{{ $c->currency_code }} · {{ config('dopay.payment_terms')[$c->payment_terms_days] ?? 'Net '.$c->payment_terms_days }}" data-days="{{ $c->payment_terms_days }}" data-tax="{{ $c->country->tax_rate }}" data-cur="{{ $c->currency_code }}" data-country="{{ $c->country_id }}">{{ $c->displayName() }} · {{ $c->code }} · {{ $c->phone }}</option>
        @endforeach
      </select></div>
    <dl class="autofill" id="autofill" @if(!$invoice->customer_id) hidden @endif>
      <div class="af-h">✓ Loaded from customer record</div>
      <div><dt>Bill to</dt><dd data-af="name">{{ $invoice->customer?->displayName() }}</dd></div><div><dt>Address</dt><dd data-af="address">{{ $invoice->customer?->address }}</dd></div>
      <div><dt>Contact</dt><dd data-af="contact">{{ $invoice->customer?->phone }}</dd></div><div><dt>Tax ID</dt><dd data-af="tin">{{ $invoice->customer?->tax_id }}</dd></div>
      <div><dt>Currency · terms</dt><dd data-af="terms"></dd></div>
    </dl>
  </section>
  <section class="card pad"><div class="bstep"><i>2</i><h3>Dates</h3></div><div class="fg">
    <div class="f"><label for="issue_date">Invoice date</label><input id="issue_date" type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->toDateString() ?? today()->toDateString()) }}" required></div>
    <div class="f"><label for="due_date">Due date</label><input id="due_date" type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->toDateString()) }}"><span class="hint">Set from payment terms</span></div>
  </div></section>
  <section class="card pad"><div class="bstep"><i>3</i><h3>Products & services</h3></div>
    <div class="tw"><table class="ftab" id="items" data-calc><thead><tr><th>#</th><th>Product</th><th>Description</th><th>Qty</th><th>Unit price</th><th>Disc %</th><th>Tax %</th><th class="r">Amount</th><th></th></tr></thead><tbody>
      @foreach($items as $n => $it)@include('invoices._item', ['n' => $n, 'it' => $it])@endforeach
    </tbody></table></div>
    <template>@include('invoices._item', ['n' => '__', 'it' => ['quantity' => 1]])</template>
    <button type="button" class="btn sm" style="margin-top:10px" data-add-line="items">+ Add line</button>
  </section>
  <section class="card pad"><div class="bstep"><i>4</i><h3>Notes & terms</h3></div><div class="fg">
    <div class="f full"><label for="notes">Payment note</label><textarea id="notes" name="notes" placeholder="Bank and mobile money details, payment reference">{{ old('notes', $invoice->notes) }}</textarea></div>
    <div class="f full"><label for="terms">Terms & conditions</label><textarea id="terms" name="terms">{{ old('terms', $invoice->terms) }}</textarea></div>
  </div></section>
  <section class="card"><div class="bbar">
    <dl class="totals" style="min-width:260px"><dt>Subtotal</dt><dd id="t-sub">0</dd><dt>Discount</dt><dd id="t-disc">0</dd><dt>Tax</dt><dd id="t-tax">0</dd><dt class="big">Total</dt><dd class="big" id="t-total">0</dd></dl>
    <div class="row"><button class="btn" name="submit" value="0">Save draft</button><button class="btn pri" name="submit" value="1">Submit for approval</button></div>
  </div></section>
</form>
@endsection
