<div class="paper fp">@include('forms.papers._top')
@php $holder = \App\Models\User::find($form->datum('holder_id')); @endphp
<h1 class="ft">Petty Cash Voucher <i>(Impress)</i></h1>
<table class="ft"><thead><tr><th style="width:38%">Voucher No.</th><th>Date</th><th>Branch</th></tr></thead><tbody><tr><td class="mono">{{ $form->reference }}</td><td>{{ fdate($form->datum('date')) }}</td><td>{{ $form->branch->name }}, {{ $form->country->name }}</td></tr></tbody></table>
<table class="ft"><thead><tr><th style="width:50%">Pay to</th><th>Petty Cash Holder Name</th></tr></thead><tbody><tr><td>{{ $form->datum('pay_to') }}</td><td>{{ $holder?->name }}</td></tr></tbody></table>
<table class="ft"><thead><tr><th style="width:15%">Date</th><th>Description</th><th style="width:22%">Category</th><th class="r" style="width:16%">Amount ({{ $cur }})</th></tr></thead><tbody>
  @foreach($form->lines as $l)<tr><td>{{ fdate($l->line_date) }}</td><td>{{ $l->description }}</td><td>{{ $l->category?->name }}</td><td class="r">{{ money($l->amount, $cur, false) }}</td></tr>@endforeach
  <tr class="tt"><td></td><td colspan="2" class="r">Total Amount</td><td class="r">{{ money($form->total, $cur, false) }}</td></tr></tbody></table>
<table class="ft"><thead><tr><th>Amount in Words</th></tr></thead><tbody><tr><td>{{ (float) $form->total ? \App\Services\AmountInWords::amount((float) $form->total, $cur) : '' }}</td></tr></tbody></table>
<table class="ft"><thead><tr><th style="width:50%">Expense Memorandum No.</th><th>Date Reimbursed</th></tr></thead><tbody><tr><td class="mono">{{ $form->datum('memo_ref') }}</td><td>{{ $form->datum('reimbursed_on') ? fdate($form->datum('reimbursed_on')) : '' }}</td></tr></tbody></table>
<table class="ft"><thead><tr><th style="width:50%;text-align:center">Paid by (Petty Cash Holder)</th><th style="text-align:center">Approved by (Accountant/Branch manager)</th></tr></thead><tbody>
  <tr><td>Sign: @include('forms.papers._sig', ['s' => $sigs[0] ?? null])</td><td>Sign: @include('forms.papers._sig', ['s' => $sigs[1] ?? null])</td></tr>
  <tr><td>Date: {{ isset($sigs[0]) ? $sigs[0]->signed_at->format('d/m/Y') : '' }}</td><td>Date: {{ isset($sigs[1]) ? $sigs[1]->signed_at->format('d/m/Y') : '' }}</td></tr></tbody></table>
<p class="note"><b>Note:</b> Attach original receipt or Receipt Reimbursement Certification (Appendix C). Petty Cash Holder must never sign “Approved by.”</p>
<div class="cut" style="border-top:1.5px dashed #555;text-align:center;margin-top:14px;font-size:9px;color:#666">✂ cut along this line ✂</div>
@include('forms.papers._foot')</div>
