@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php $s = $invoice->displayStatus(); $u = auth()->user(); $c = $invoice->customer;
  $msg = "Hello {$c->displayName()}, here is invoice {$invoice->number} for ".money($invoice->total, $invoice->currency_code).", due ".fdate($invoice->due_date).": ".$invoice->shareUrl(); @endphp
<p style="margin-bottom:10px"><a class="btn ghost sm" href="{{ route('invoices.index') }}">← Invoices</a></p>
<div class="ph"><div><h1 class="mono" style="font-size:22px">{{ $invoice->number }}</h1><p class="sub">{{ $c->displayName() }} · {{ money($invoice->total, $invoice->currency_code) }} · due {{ fdate($invoice->due_date) }}</p></div>@include('partials.pill', ['label' => $s])</div>
<div class="builder">
  <div>@include('invoices.paper')</div>
  <div class="stack sticky-col">
    <section class="card pad"><h3 style="margin-bottom:10px">Workflow</h3>
      <div class="row">
        @if($invoice->status === 'draft')
          @can('invoices.create')<a class="btn" href="{{ route('invoices.edit', $invoice) }}">Edit draft</a>
          <form method="post" action="{{ route('invoices.submit', $invoice) }}">@csrf<button class="btn pri">Submit for approval</button></form>@endcan
        @elseif($invoice->status === 'pending_approval')
          @if($u->can('invoices.approve') && $u->canActForCountry($invoice->country_id))
            <form method="post" action="{{ route('invoices.approve', $invoice) }}">@csrf<button class="btn pri">Approve</button></form>
            <button class="btn" data-open-modal="returnInv">Return to draft</button>
          @else <p class="small muted">Waiting for a Finance Manager, Branch Manager or Country Manager to approve.</p> @endif
        @elseif($invoice->status === 'approved' && !$invoice->generated_at)
          <form method="post" action="{{ route('invoices.generate', $invoice) }}">@csrf<button class="btn pri">Generate official invoice</button></form>
        @elseif($invoice->isOfficial())
          @if($invoice->balance() > 0)@can('payments.record')<a class="btn pri" href="{{ route('payments.create', ['customer' => $c->id, 'invoice' => $invoice->id]) }}">Record payment</a>@endcan @endif
          <button class="btn danger" data-open-modal="cancelInv">Cancel invoice</button>
        @endif
      </div>
    </section>
    <section class="card pad"><h3 style="margin-bottom:10px">Send & share</h3>
      @if($invoice->generated_at && $invoice->status !== 'cancelled')
        <div class="share">
          <a class="btn" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', $c->phone) }}?text={{ urlencode($msg) }}" data-log-share="{{ route('invoices.share', $invoice) }}" data-channel="WhatsApp">WhatsApp</a>
          <a class="btn" href="mailto:{{ $c->email }}?subject={{ rawurlencode('Invoice '.$invoice->number) }}&body={{ rawurlencode($msg) }}" data-log-share="{{ route('invoices.share', $invoice) }}" data-channel="Email">Email</a>
          <a class="btn" target="_blank" rel="noopener" href="https://t.me/share/url?url={{ urlencode($invoice->shareUrl()) }}&text={{ urlencode('Invoice '.$invoice->number) }}" data-log-share="{{ route('invoices.share', $invoice) }}" data-channel="Telegram">Telegram</a>
          <a class="btn" href="#" data-copy="{{ $msg }}" data-log-share="{{ route('invoices.share', $invoice) }}" data-channel="IMO"><span>IMO (copy)</span></a>
          <a class="btn" href="#" data-copy="{{ $invoice->shareUrl() }}" data-log-share="{{ route('invoices.share', $invoice) }}" data-channel="Link"><span>Copy link</span></a>
          <a class="btn" href="{{ route('invoices.pdf', $invoice) }}">Download PDF</a>
          <a class="btn" href="{{ route('invoices.pdf', $invoice) }}" target="_blank">Print</a>
        </div>
        <div class="linkbox" style="margin-top:10px"><span class="mono">{{ $invoice->shareUrl() }}</span></div>
      @else <p class="note">Sharing unlocks once the invoice is approved and the official copy is generated.</p> @endif
    </section>
    <section class="card"><div class="card-h"><h3>Payments</h3><span class="small muted">{{ money($invoice->amount_paid, $invoice->currency_code) }} of {{ money($invoice->total, $invoice->currency_code) }}</span></div>
      <div class="feed">@forelse($invoice->allocations as $a)<div class="feed-i"><div class="t"><b class="mono">{{ $a->payment->number }}</b><span>{{ fdate($a->payment->paid_on) }} · {{ $a->payment->method }} · {{ $a->payment->status }}</span></div><div class="a">{{ money($a->amount, $invoice->currency_code) }}</div>@if($a->payment->receipt)<a class="btn sm" href="{{ route('receipts.show', $a->payment->receipt) }}">Receipt</a>@endif</div>@empty<div class="empty">No payments yet.</div>@endforelse</div>
    </section>
  </div>
</div>

<dialog class="modal-d" id="returnInv"><form method="post" action="{{ route('invoices.return', $invoice) }}">@csrf
  <div class="modal-h"><h2>Return to draft</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><div class="f"><label for="comment">What needs to change?</label><textarea id="comment" name="comment" required minlength="4"></textarea></div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Return to preparer</button></div></form></dialog>
<dialog class="modal-d" id="cancelInv"><form method="post" action="{{ route('invoices.cancel', $invoice) }}">@csrf
  <div class="modal-h"><h2>Cancel invoice — authorization required</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><p class="small" style="margin-bottom:12px">Cancelling needs a reason and a second person’s PIN. The invoice stays in the records and the audit trail.</p>@include('partials.authorize', ['id' => 'ci'])</div>
  <div class="modal-f"><button class="btn" data-close-modal>Keep invoice</button><button class="btn danger">Cancel invoice</button></div></form></dialog>
@endsection
