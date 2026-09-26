<div class="paper fp">@include('forms.papers._top')
@php $pay = \App\Models\Country::find($form->datum('paying_country_id')); $steps = app(\App\Services\ApprovalService::class)->steps($form); @endphp
<h1 class="ft">Interbranch Transfer Memorandum</h1><div class="rule"></div>
<table class="ft"><tbody><tr><td class="fl" style="width:110px">Ref No.:</td><td class="mono">{{ $form->reference }}</td></tr><tr><td class="fl">Date:</td><td>{{ fdate($form->datum('date')) }}</td></tr><tr><td class="fl">Subject:</td><td class="fl">REQUEST FOR INTERCOMPANY FUND TRANSFER / EXPENSE SUPPORT</td></tr></tbody></table>
<p>This memo serves to formally document that <b>{{ $pay?->legal_entity }}</b> will provide financial support to cover the expenses for <b>{{ $form->country->legal_entity }}</b>. The specific funding requirements are outlined below.</p>
<p>Please note that this total advanced amount will be treated as an interest-free intercompany payable due to <b>{{ $pay?->legal_entity }}</b>, with repayment terms to be agreed in writing within 30 days.</p>
<table class="ft"><thead><tr><th style="width:40px">S/N</th><th>Description</th><th class="r" style="width:16%">Amount to Pay</th><th style="width:12%">Currency</th><th style="width:20%">Memo Ref</th></tr></thead><tbody>
  @foreach($form->lines as $n => $l)<tr><td>{{ $n + 1 }}</td><td>{{ $l->description }}</td><td class="r">{{ money($l->amount, $cur, false) }}</td><td>{{ $cur }}</td><td class="mono">{{ $l->memo_ref }}</td></tr>@endforeach
  @for($i = $form->lines->count(); $i < 5; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>@endfor
  <tr class="tt"><td></td><td>Total</td><td class="r">{{ money($form->total, $cur, false) }}</td><td>{{ $cur }}</td><td></td></tr></tbody></table>
<p><b>Total Amount (in words):</b> <span class="ul">{{ (float) $form->total ? \App\Services\AmountInWords::amount((float) $form->total, $cur) : '' }}</span></p>
<table class="ft"><thead><tr>@foreach($steps as $st)<th style="text-align:center;width:33%">{{ $st['label'] }}</th>@endforeach</tr></thead><tbody>
  <tr>@foreach($steps as $i => $st)<td style="height:40px">@include('forms.papers._sig', ['s' => $sigs[$i] ?? null])</td>@endforeach</tr>
  <tr>@foreach($steps as $i => $st)<td>Name: {{ isset($sigs[$i]) ? $sigs[$i]->user->name : '' }}</td>@endforeach</tr>
  <tr>@foreach($steps as $i => $st)<td>Date: {{ isset($sigs[$i]) ? $sigs[$i]->signed_at->format('d/m/Y') : '' }}</td>@endforeach</tr></tbody></table>
@include('forms.papers._foot')</div>
