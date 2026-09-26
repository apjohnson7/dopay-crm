@extends('layouts.app')
@section('title', $receipt->number)
@section('content')
@php $p = $receipt->payment; $c = $p->customer; $msg = "Hello {$c->displayName()}, thank you for your payment of ".money($p->amount, $p->currency_code).". Receipt {$receipt->number}."; @endphp
<div class="ph"><div><h1 class="mono" style="font-size:22px">{{ $receipt->number }}</h1><p class="sub">{{ $c->displayName() }} · {{ money($p->amount, $p->currency_code) }} · {{ $p->method }}</p></div>@include('partials.pill', ['label' => ucfirst($receipt->status)])</div>
<div class="builder"><div>@include('receipts.paper')</div>
  <div class="stack sticky-col"><section class="card pad"><h3 style="margin-bottom:10px">Send receipt</h3><div class="share">
    <a class="btn" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', $c->phone) }}?text={{ urlencode($msg) }}">WhatsApp</a>
    <a class="btn" href="mailto:{{ $c->email }}?subject={{ rawurlencode('Receipt '.$receipt->number) }}&body={{ rawurlencode($msg) }}">Email</a>
    <a class="btn" href="{{ route('receipts.pdf', $receipt) }}">Download PDF</a>
    <a class="btn" href="{{ route('receipts.pdf', $receipt) }}" target="_blank">Print</a></div></section></div></div>
@endsection
