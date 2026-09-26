@php $sigs = $form->currentSignatures(); $cur = $form->currency_code; @endphp
@if(in_array($form->status, ['draft', 'returned', 'void']))<div class="wm">{{ $form->status === 'void' ? 'VOID' : 'DRAFT' }}</div>@endif
<table style="width:100%;font-size:9px;color:#8A8A8A;margin-bottom:8px"><tr><td>{{ $form->country->legal_entity }} · {{ $form->branch->name }}</td><td style="text-align:right" class="mono">{{ $form->reference }}</td></tr></table>
