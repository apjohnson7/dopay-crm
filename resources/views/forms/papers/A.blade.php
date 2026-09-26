<div class="paper fp">@include('forms.papers._top')
<h1 class="ft">EXPENSE Memorandum</h1><div class="rule"></div>
<table style="width:100%"><tbody>
  <tr><td class="fl" style="width:100px">Ref No:</td><td class="mono">{{ $form->reference }}</td></tr>
  <tr><td class="fl">Date:</td><td>{{ fdate($form->datum('date')) }}</td></tr>
  <tr><td class="fl">To:</td><td>{{ config('dopay.forms.A.routes.'.$form->datum('route', 'CFO').'.to') }}</td></tr>
  <tr><td class="fl">Through:</td><td>Financial Controller</td></tr>
  <tr><td class="fl">Preparer:</td><td>{{ $form->preparer->name }}</td></tr>
  <tr><td class="fl">Subject:</td><td class="fl" style="text-transform:uppercase">{{ $form->datum('subject') }}</td></tr>
</tbody></table>
<p><b>Total Amount:</b> <span class="ul">{{ money($form->total, $cur, false) }}</span> &nbsp; <b>Currency:</b> <span class="ul">{{ $cur }}</span></p>
<table class="ft"><thead><tr><th style="width:30px">No</th><th>Description</th><th style="width:22%">Category</th><th class="r" style="width:15%">Amount ({{ $cur }})</th><th style="width:19%">Acct Name / Number / Bank</th></tr></thead><tbody>
  @foreach($form->lines as $n => $l)<tr><td>{{ $n + 1 }}</td><td>{{ $l->description }}</td><td>{{ $l->category?->name }}</td><td class="r">{{ money($l->amount, $cur, false) }}</td><td>{{ $l->account_details }}</td></tr>@endforeach
  @for($i = $form->lines->count(); $i < 3; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>@endfor
  <tr class="tt"><td></td><td></td><td class="fl">TOTAL</td><td class="r fl">{{ money($form->total, $cur, false) }}</td><td></td></tr>
</tbody></table>
@php $pm = $form->datum('pay_method'); @endphp
<p><b>Payment Method:</b> [{{ $pm === 'Cash' ? '✓' : ' ' }}] Cash &nbsp; [{{ $pm === 'Bank transfer' ? '✓' : ' ' }}] Bank transfer &nbsp; [{{ $pm === 'Other' ? '✓' : ' ' }}] Other {{ $form->datum('pay_other') }}</p>
<p><b>Transfer from bank:</b> <span class="ul">{{ $form->datum('from_bank') }}&nbsp;</span> &nbsp; <b>Account number:</b> <span class="ul">{{ $form->datum('account_no') }}&nbsp;</span></p>
<p><b>Payment Date:</b> <span class="ul">{{ $form->datum('pay_date') ? fdate($form->datum('pay_date')) : '' }}&nbsp;</span> &nbsp; <b>Bank Fee:</b> <span class="ul">{{ $form->datum('bank_fee') }}&nbsp;</span></p>
<p class="fl">Detailed Description / Justification:</p><div class="just">{{ $form->datum('justification') }}</div>
<p class="sec">Requester</p>
<table style="width:100%"><tr><td>Name: <span class="ul">{{ $form->datum('requester_name') }}</span><br><small>(Head of Department)</small></td><td>Department: <span class="ul">{{ $form->datum('requester_department') }}&nbsp;</span></td><td>Signature: @include('forms.papers._sig', ['s' => $sigs[-1] ?? null])</td></tr></table>
<p class="sec">Approval Chain: <i>For country that does not have accountant should have Assistant Manager or Country Manager to sign on behalf of Accountant.</i></p>
@include('forms.papers._chain')
<p class="note"><b>Important:</b> No expense shall be paid without complete written approval from all four steps above. Retroactive approval is not permitted except under the genuine emergency procedure in the Financial Policy, Section 15.1. Attach original receipts/invoices for every line item, or a completed Receipt Reimbursement Certification if no receipt is available.</p>
@include('forms.papers._foot')</div>
