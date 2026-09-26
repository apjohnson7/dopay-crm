@extends('layouts.app')
@section('title', $customer->exists ? 'Edit customer' : 'New customer')
@section('content')
<div class="ph"><div><h1>{{ $customer->exists ? 'Edit '.$customer->displayName() : 'New customer' }}</h1><p class="sub">The branch decides the country, currency and tax that every document for this customer will use.</p></div></div>
<form method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card pad">@csrf @if($customer->exists) @method('put') @endif
<div class="fg">
  <div class="f"><label for="type">Type</label><select id="type" name="type"><option value="business" @selected(old('type', $customer->type) === 'business')>Business</option><option value="member" @selected(old('type', $customer->type) === 'member')>Individual member</option></select></div>
  <div class="f"><label for="category">Category</label><select id="category" name="category">@foreach(config('dopay.customer_categories') as $c)<option @selected(old('category', $customer->category) === $c)>{{ $c }}</option>@endforeach</select></div>
  <div class="f"><label for="name">Full name (contact)</label><input id="name" name="name" value="{{ old('name', $customer->name) }}" required></div>
  <div class="f"><label for="company">Company name</label><input id="company" name="company" value="{{ old('company', $customer->company) }}" placeholder="Leave blank for individuals"></div>
  <div class="f"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"></div>
  <div class="f"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}"></div>
  <div class="f"><label for="address">Address</label><input id="address" name="address" value="{{ old('address', $customer->address) }}"></div>
  <div class="f"><label for="city">City</label><input id="city" name="city" value="{{ old('city', $customer->city) }}"></div>
  <div class="f"><label for="branch_id">Branch</label><select id="branch_id" name="branch_id" required>@foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $customer->branch_id ?? auth()->user()->branch_id) == $b->id)>{{ $b->name }} · {{ $b->country->name }}</option>@endforeach</select></div>
  <div class="f"><label for="tax_id">Tax ID (TIN)</label><input id="tax_id" name="tax_id" value="{{ old('tax_id', $customer->tax_id) }}"></div>
  <div class="f"><label for="registration_no">Registration number</label><input id="registration_no" name="registration_no" value="{{ old('registration_no', $customer->registration_no) }}"></div>
  <div class="f"><label for="industry">Industry</label><input id="industry" name="industry" value="{{ old('industry', $customer->industry) }}"></div>
  <div class="f"><label for="payment_terms_days">Payment terms</label><select id="payment_terms_days" name="payment_terms_days">@foreach(config('dopay.payment_terms') as $d => $l)<option value="{{ $d }}" @selected(old('payment_terms_days', $customer->payment_terms_days) == $d)>{{ $l }}</option>@endforeach</select></div>
  <div class="f"><label for="credit_limit">Credit limit</label><input id="credit_limit" name="credit_limit" type="number" min="0" step="any" value="{{ old('credit_limit', $customer->credit_limit) }}"></div>
  <div class="f"><label for="id_type">ID type (members)</label><input id="id_type" name="id_type" value="{{ old('id_type', $customer->id_type) }}" placeholder="National ID, passport…"></div>
  <div class="f"><label for="id_number">ID number</label><input id="id_number" name="id_number" value="{{ old('id_number', $customer->id_number) }}"><span class="hint">Stored encrypted</span></div>
</div>
<div class="row" style="margin-top:16px"><button class="btn pri">Save customer</button><a class="btn ghost" href="{{ url()->previous() }}">Cancel</a></div>
</form>
@endsection
