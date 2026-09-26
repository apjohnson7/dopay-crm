@extends('layouts.app')
@section('title', $supplier->exists ? 'Edit supplier' : 'New supplier')
@section('content')
<div class="ph"><div><h1>{{ $supplier->exists ? 'Edit '.$supplier->company : 'New supplier' }}</h1><p class="sub">The country sets the supplier's currency. Names and TINs must be unique.</p></div></div>
<form method="post" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card pad">@csrf @if($supplier->exists) @method('put') @endif
<div class="fg">
  <div class="f full"><label for="company">Company name</label><input id="company" name="company" value="{{ old('company', $supplier->company) }}" required maxlength="190"></div>
  <div class="f"><label for="contact_person">Contact person</label><input id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}"></div>
  <div class="f"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}"></div>
  <div class="f"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $supplier->email) }}"></div>
  <div class="f"><label for="country_id">Country</label><select id="country_id" name="country_id" required>@foreach($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id', $supplier->country_id) == $c->id)>{{ $c->name }} · {{ $c->currency_code }}</option>@endforeach</select></div>
  <div class="f full"><label for="address">Address</label><input id="address" name="address" value="{{ old('address', $supplier->address) }}"></div>
  <div class="f"><label for="tax_id">Tax ID (TIN)</label><input id="tax_id" name="tax_id" value="{{ old('tax_id', $supplier->tax_id) }}"></div>
  <div class="f"><label for="payment_terms_days">Payment terms</label><select id="payment_terms_days" name="payment_terms_days">@foreach([0 => 'Due on receipt', 7 => 'Net 7', 15 => 'Net 15', 30 => 'Net 30', 45 => 'Net 45', 60 => 'Net 60'] as $d => $l)<option value="{{ $d }}" @selected(old('payment_terms_days', $supplier->payment_terms_days) == $d)>{{ $l }}</option>@endforeach</select></div>
  <div class="f full"><label for="supplies">What they supply</label><input id="supplies" name="supplies" value="{{ old('supplies', $supplier->supplies) }}" placeholder="e.g. Printing, stationery"></div>
  <div class="f full"><label for="bank_details">Bank details</label><input id="bank_details" name="bank_details" value="{{ old('bank_details', $supplier->bank_details) }}" placeholder="Bank · account name · account number"><span class="hint">Stored encrypted. Changes are logged and the Finance Manager is notified.</span></div>
</div>
<div class="row" style="margin-top:16px"><button class="btn pri">Save supplier</button><a class="btn ghost" href="{{ route('suppliers.index') }}">Cancel</a></div>
</form>
@endsection
