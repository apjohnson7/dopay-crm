@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="ph"><div><h1>Payments</h1><p class="sub">Every payment is allocated to invoices and produces a receipt automatically.</p></div>
  <div class="ph-act">@can('payments.record')<a class="btn pri" href="{{ route('payments.create') }}">Record payment</a>@endcan</div></div>
<section class="card"><div class="tw"><table class="tbl cards"><thead><tr><th>Payment</th><th>Invoice(s)</th><th class="num">Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Received by</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($payments as $p)
  <tr><td class="lead" data-label="Payment"><span class="mono strong">{{ $p->number }}</span><span class="sub2">{{ $p->customer->displayName() }}</span></td>
    <td data-label="Invoices"><span class="small mono">{{ $p->allocations->map(fn($a) => $a->invoice->number)->implode(', ') }}</span></td>
    <td class="num" data-label="Amount">{{ money($p->amount, $p->currency_code) }}</td><td data-label="Method">{{ $p->method }}</td><td data-label="Reference"><span class="mono small">{{ $p->reference }}</span></td>
    <td data-label="Date">{{ fdate($p->paid_on) }}</td><td data-label="Received by">{{ $p->receiver->name }}</td><td data-label="Status">@include('partials.pill', ['label' => ucfirst($p->status)])</td>
    <td>@if($p->receipt)<a class="btn sm" href="{{ route('receipts.show', $p->receipt) }}">Receipt</a>@endif
      @if($p->status === 'completed')@can('payments.reverse')<button class="btn sm danger" data-open-modal="rev-{{ $p->id }}">Reverse</button>
      <dialog class="modal-d" id="rev-{{ $p->id }}"><form method="post" action="{{ route('payments.reverse', $p) }}">@csrf<div class="modal-h"><h2>Reverse {{ $p->number }}</h2><button class="icon-btn" data-close-modal>✕</button></div><div class="modal-b">@include('partials.authorize', ['id' => 'r'.$p->id])</div><div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn danger">Reverse payment</button></div></form></dialog>@endcan @endif</td></tr>
@empty <tr><td colspan="9" class="empty">No payments yet.</td></tr> @endforelse
</tbody></table></div>{{ $payments->links() }}</section>
@endsection
