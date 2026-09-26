@extends('layouts.app')
@section('title', $form->reference)
@section('content')
@php $sigs = $form->currentSignatures(); $u = auth()->user(); $def = $form->definition(); $wide = $form->type === 'G' || ($form->type === 'F' && $form->datum('period') !== 'Monthly'); @endphp
<p style="margin-bottom:10px"><a class="btn ghost sm" href="{{ route('forms.index') }}">← Finance forms</a></p>
<div class="ph"><div><h1 class="mono" style="font-size:22px">{{ $form->reference }}</h1><p class="sub">{{ $def['appendix'] }} · {{ $def['name'] }} · {{ $form->country->legal_entity }}@if((float) $form->total) · {{ money($form->total, $form->currency_code) }}@endif</p></div>@include('partials.pill', ['label' => $form->statusLabel()])</div>
@if($form->return_note && $form->status === 'returned')<div class="alert-err">Returned: {{ $form->return_note }}</div>@endif
<div class="{{ $wide ? 'stack' : 'builder' }}">
  <div>@include('forms.papers.'.$form->type)</div>
  <div class="{{ $wide ? 'grid g-2' : 'stack sticky-col' }}">
    <section class="card pad"><h3 style="margin-bottom:6px">Approval chain</h3><div class="steps">
      @if(isset($sigs[-1]))<div class="step done"><span class="d">✓</span><div><b>Requester</b><div class="small muted">{{ $sigs[-1]->user->name }} · {{ $sigs[-1]->signed_at->format('j M Y') }}, {{ $sigs[-1]->local_time }}</div></div></div>@endif
      @foreach($steps as $i => $st)
        @php $s = $sigs[$i] ?? null; $isCur = $current === $i; @endphp
        <div class="step {{ $s ? 'done' : ($isCur ? 'cur' : '') }}"><span class="d">{{ $s ? '✓' : '' }}</span><div><b>{{ $st['label'] }}</b>
          @if($s)<div><span class="sg">{{ $s->user->name }}</span></div><div class="small muted">{{ $s->signed_at->format('j M Y') }}, {{ $s->local_time }} · signature {{ $s->code }}@if($s->comment) · “{{ $s->comment }}”@endif</div>
          @elseif($isCur)<div class="small muted">Waiting for {{ $waitingFor->pluck('name')->implode(', ') ?: 'an eligible approver in this country' }}</div>
            @if($canSign)<div class="row" style="margin-top:8px"><button class="btn pri sm" data-open-modal="signForm">Sign as {{ strtok($u->name, ' ') }}</button><button class="btn sm" data-open-modal="returnForm">Return</button></div>
            @elseif($waitingFor->count())<div class="row" style="margin-top:6px"><a class="btn sm" href="{{ route('messages.index', ['to' => $waitingFor->first()->id, 'attach' => 'form:'.$form->id]) }}">Message {{ strtok($waitingFor->first()->name, ' ') }}</a></div>@endif
          @endif
        </div></div>
      @endforeach
    </div></section>
    <section class="card pad"><h3 style="margin-bottom:10px">Actions</h3><div class="row">
      @if(in_array($form->status, ['draft', 'returned']))
        <a class="btn" href="{{ route('forms.edit', $form) }}">Edit</a>
        <form method="post" action="{{ route('forms.submit', $form) }}">@csrf<button class="btn pri">Submit for approval</button></form>
      @endif
      @if($form->type === 'A' && $form->status === 'approved')@can('payments.record')<button class="btn pri" data-open-modal="paidForm">Mark as paid</button>@endcan @endif
      @if($form->type === 'B' && $form->status === 'approved')<form method="post" action="{{ route('forms.action', [$form, 'reimburse']) }}">@csrf<button class="btn pri">Mark reimbursed</button></form>@endif
      @if($form->type === 'K' && $form->status === 'approved')<form method="post" action="{{ route('forms.action', [$form, 'disburse']) }}">@csrf<button class="btn pri">Disburse advance</button></form>@endif
      @if($form->type === 'K' && $form->status === 'awaiting_liquidation')<button class="btn pri" data-open-modal="liqForm">Record liquidation</button>@endif
      @if($form->type === 'J' && $form->status === 'approved')<form method="post" action="{{ route('forms.action', [$form, 'settle']) }}">@csrf<button class="btn pri">Record repayment</button></form>@endif
      @if(!in_array($form->status, ['draft', 'returned', 'void']))<button class="btn" data-open-modal="reviseForm">Revise (authorized)</button>@endif
      @if(in_array($form->status, ['draft', 'returned', 'in_approval']))<button class="btn danger" data-open-modal="voidForm">Void</button>@endif
    </div></section>
    <section class="card pad"><h3 style="margin-bottom:10px">Print & share</h3><div class="share">
      <a class="btn" href="{{ route('forms.pdf', $form) }}" target="_blank">Print</a>
      <a class="btn" href="{{ route('forms.pdf', $form) }}">PDF</a>
      <a class="btn" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($def['name'].' '.$form->reference.': '.route('forms.show', $form)) }}">WhatsApp</a>
      <a class="btn" href="mailto:?subject={{ rawurlencode($def['name'].' '.$form->reference) }}&body={{ rawurlencode(route('forms.show', $form)) }}">Email</a>
      <a class="btn" href="{{ route('messages.index', ['attach' => 'form:'.$form->id]) }}">Team message</a>
    </div><p class="small muted" style="margin-top:8px">Links open inside Dopay and require sign-in.</p></section>
    @if($form->documents->count())<section class="card pad"><h3 style="margin-bottom:8px">Attachments</h3>@foreach($form->documents as $d)<div class="small">📎 {{ $d->name }} · {{ $d->category }}</div>@endforeach</section>@endif
  </div>
</div>

<dialog class="modal-d" id="signForm"><form method="post" action="{{ route('forms.sign', $form) }}">@csrf
  <div class="modal-h"><h2>Sign {{ $form->reference }}</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><p>Signing as <b>{{ $u->name }}</b> ({{ $u->roleName() }}). Your name, date, local time ({{ $u->country()?->localTime() }}) and a signature ID are stamped on the form.</p>
    <div class="f" style="margin-top:12px"><label for="comment">Comment (optional)</label><input id="comment" name="comment"></div>
    <div class="f" style="margin-top:10px"><label for="pin">Your signing PIN</label><input id="pin" name="pin" type="password" inputmode="numeric" required></div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Apply e-signature</button></div></form></dialog>
<dialog class="modal-d" id="returnForm"><form method="post" action="{{ route('forms.return', $form) }}">@csrf
  <div class="modal-h"><h2>Return for correction</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><div class="f"><label for="note">What needs to change?</label><textarea id="note" name="note" required minlength="4"></textarea></div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Return to preparer</button></div></form></dialog>
<dialog class="modal-d" id="paidForm"><form method="post" action="{{ route('forms.action', [$form, 'paid']) }}">@csrf
  <div class="modal-h"><h2>Mark as paid</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><p class="small" style="margin-bottom:10px">Posts each line to Expenses under its category, which updates budget monitoring.</p><div class="fg"><div class="f"><label for="paid_on">Payment date</label><input id="paid_on" name="paid_on" type="date" value="{{ today()->toDateString() }}" required></div><div class="f"><label for="reference">Payment reference</label><input id="reference" name="reference"></div></div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Mark paid & post</button></div></form></dialog>
<dialog class="modal-d" id="liqForm"><form method="post" enctype="multipart/form-data" action="{{ route('forms.action', [$form, 'liquidate']) }}">@csrf
  <div class="modal-h"><h2>Record liquidation</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b"><div class="fg"><div class="f"><label for="spent">Amount spent ({{ $form->currency_code }})</label><input id="spent" name="spent" type="number" step="any" min="0" value="{{ (float) $form->total }}" required></div>
    <div class="f full"><label for="receipts">Original receipts & reconciliation report</label><input id="receipts" name="receipts[]" type="file" multiple required></div></div></div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn pri">Record liquidation</button></div></form></dialog>
@foreach(['reviseForm' => ['forms.revise', 'Revise a signed form', 'Revise'], 'voidForm' => ['forms.void', 'Void this form', 'Void']] as $id => [$route, $title, $btn])
<dialog class="modal-d" id="{{ $id }}"><form method="post" action="{{ route($route, $form) }}">@csrf
  <div class="modal-h"><h2>{{ $title }} — authorization required</h2><button class="icon-btn" data-close-modal>✕</button></div>
  <div class="modal-b">@include('partials.authorize', ['id' => $id])</div>
  <div class="modal-f"><button class="btn" data-close-modal>Cancel</button><button class="btn {{ $id === 'voidForm' ? 'danger' : 'pri' }}">{{ $btn }}</button></div></form></dialog>
@endforeach
@endsection
