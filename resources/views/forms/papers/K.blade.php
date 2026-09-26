<div class="paper fp">@include('forms.papers._top')
@php $cm = \App\Models\User::role('Country Manager')->whereHas('branch', fn ($q) => $q->where('country_id', $form->country_id))->first();
  $P = ['1' => 'Clearing Customs', '2' => 'Pocket money for international trips', '3' => 'Company Events', '4' => 'Other approved projects (specify)']; $pm = $form->datum('pay_method'); $liq = $form->datum('liquidation'); @endphp
<h1 class="ft" style="text-align:center">CASH ADVANCE REQUEST FORM</h1><div class="rule"></div>
<table class="ft"><tbody>
  <tr><td><b>Requester Name:</b> {{ $form->preparer->name }}</td><td><b>Date Filed:</b> {{ fdate($form->datum('date')) }}</td></tr>
  <tr><td><b>Department / Branch:</b> {{ $form->datum('department') }} / {{ $form->branch->name }}</td><td><b>Ref No:</b> <span class="mono">{{ $form->reference }}</span></td></tr>
  <tr><td><b>To (Country Manager):</b> {{ $cm?->name ?? 'Country Manager' }}</td><td><b>Through:</b> Financial Controller</td></tr>
  <tr><td colspan="2"><b>Subject:</b> {{ $form->datum('subject') }}<br><b>Purpose of Cash Advance (select one):</b><br>@foreach($P as $k => $l)[{{ (string) $form->datum('purpose') === (string) $k ? '✓' : ' ' }}] {{ $k }}. {{ $l }}{{ $k === '4' && $form->datum('purpose') == 4 ? ': '.$form->datum('purpose_other') : '' }} &nbsp; @endforeach</td></tr></tbody></table>
<table class="ft"><thead><tr><th style="width:30px">No</th><th>Description</th><th style="width:22%">Category</th><th class="r" style="width:18%">Amount ({{ $cur }})</th></tr></thead><tbody>
  @foreach($form->lines as $n => $l)<tr><td>{{ $n + 1 }}</td><td>{{ $l->description }}</td><td>{{ $l->category?->name }}</td><td class="r">{{ money($l->amount, $cur, false) }}</td></tr>@endforeach
  <tr class="tt"><td colspan="3">TOTAL</td><td class="r">{{ money($form->total, $cur, false) }}</td></tr></tbody></table>
<table class="ft"><tbody><tr><td><b>Justification / Business Need:</b> {{ $form->datum('justification') }}</td></tr></tbody></table>
<table class="ft"><tbody>
  <tr><td><b>Total Amount Requested:</b> {{ money($form->total, $cur, false) }}</td><td><b>Currency:</b> {{ $cur }}</td></tr>
  <tr><td><b>Expected Date of Use:</b> {{ fdate($form->datum('use_date')) }}</td><td><b>Expected Date of Liquidation:</b> {{ fdate($form->datum('liquidation_date')) }}</td></tr>
  <tr><td><b>Payment Method:</b> @foreach(['Cash', 'Bank Transfer', 'Mobile Money'] as $m)[{{ $pm === $m ? '✓' : ' ' }}] {{ $m }} &nbsp; @endforeach</td><td><b>Payable To / Account No.:</b> {{ $form->datum('payable_to') }}</td></tr></tbody></table>
<p class="sec">Approval Chain: <i>Use the same rule as unusual expense (Appendix A)</i></p>
@include('forms.papers._chain', ['nameLabel' => 'Printed Name'])
@if($liq)<table class="ft"><thead><tr><th colspan="3">Liquidation recorded {{ fdate($liq['date']) }}</th></tr></thead><tbody><tr><td>Spent: {{ money($liq['spent'], $cur) }}</td><td>Returned: {{ money($liq['returned'], $cur) }}</td><td>Receipts attached</td></tr></tbody></table>@endif
<p class="note"><b>Important:</b> No Cash Advance shall be paid without complete written approval from all steps above. Original receipts and a reconciliation report are due within 5 working days of the expense/trip end (Policy Section 14.4). Unused funds must be returned immediately.</p>
@include('forms.papers._foot')</div>
