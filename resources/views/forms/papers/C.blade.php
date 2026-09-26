<div class="paper fp">@include('forms.papers._top')
@php $p = \App\Models\User::find($form->datum('purchaser_id')); @endphp
<p class="intro" style="font-size:10px">To be used when the company makes payments for miscellaneous goods or services but cannot obtain a receipt from the vendor.<br>In such cases, a company employee must certify the payment.</p>
<h1 class="ft" style="text-align:center">Receipt Reimbursement Certification</h1><p style="text-align:center;margin:0 0 8px">For substitution of receipt</p>
<p style="text-align:right"><b>{{ $form->country->legal_entity }}</b> (Company)</p>
<table class="ft"><thead><tr><th style="width:16%">Date</th><th>Transaction Detail</th><th class="r" style="width:18%">Amount ({{ $cur }})</th><th style="width:20%">Note</th></tr></thead><tbody>
  @foreach($form->lines as $l)<tr><td>{{ fdate($l->line_date) }}</td><td>{{ $l->description }}</td><td class="r">{{ money($l->amount, $cur, false) }}</td><td>{{ $l->notes }}</td></tr>@endforeach
  @for($i = $form->lines->count(); $i < 6; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td></tr>@endfor
  <tr class="tt"><td colspan="2">Number in Word: {{ (float) $form->total ? \App\Services\AmountInWords::amount((float) $form->total, $cur) : '' }} <span style="float:right">Total</span></td><td class="r">{{ money($form->total, $cur, false) }}</td><td></td></tr></tbody></table>
<p style="line-height:2">I <span class="ul">{{ $p?->name }}</span> (purchaser) position <span class="ul">{{ $form->datum('position') }}</span><br>certify that a receipt could not be obtained for the above expenses, and that this payment was made solely for <b>{{ $form->country->legal_entity }}</b> official company business. From <span class="ul">{{ fdate($form->datum('period_from')) }}</span> to <span class="ul">{{ fdate($form->datum('period_to')) }}</span></p>
<table style="width:100%;margin-top:18px"><tr><td style="width:40%"></td><td>Sign @include('forms.papers._sig', ['s' => $sigs[0] ?? null]) (Purchaser)<br>( {{ $p?->name }} )<br><br>Sign @include('forms.papers._sig', ['s' => $sigs[1] ?? null]) (Approver)<br>( {{ isset($sigs[1]) ? $sigs[1]->user->name : '' }} )<br><small>Accountant or Assistant Manager</small></td></tr></table>
@include('forms.papers._foot')</div>
