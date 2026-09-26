@php $p = $receipt->payment; $c = $p->customer; $co = $p->branch->country; $cur = $p->currency_code; $bal = $p->allocations->sum(fn($a) => $a->invoice->balance()); @endphp
<div class="paper">
  @if($receipt->status === 'void')<div class="wm">VOID</div>@endif
  <table style="width:100%"><tr>
    <td><table><tr><td style="width:56px"><img src="{{ isset($pdf) ? public_path('img/dopay-logo.png') : asset('img/dopay-logo.png') }}" style="width:48px" alt="Logo"></td><td><b style="font-size:13px">{{ $co->legal_entity }}</b><br>{{ $co->address ?: $p->branch->office.', '.$p->branch->name }}, {{ $co->name }}@if($co->phone || $co->email)<br>{{ collect([$co->phone, $co->email])->filter()->implode(' · ') }}@endif</td></tr></table></td>
    <td class="r"><div class="p-doc">Receipt</div><div class="mono">{{ $receipt->number }}</div></td></tr></table>
  <div class="line"></div>
  <table style="width:100%;margin-bottom:12px"><tr>
    <td style="width:58%;vertical-align:top"><div class="muted" style="font-size:9px;text-transform:uppercase">Received from</div><b>{{ $c->displayName() }}</b><br>{{ $c->address }}, {{ $c->city }}<br>{{ $c->phone }}</td>
    <td><table><tr><td class="muted">Payment date</td><td class="r fl">{{ fdate($p->paid_on) }}</td></tr><tr><td class="muted">Method</td><td class="r fl">{{ $p->method }}</td></tr><tr><td class="muted">Reference</td><td class="r mono">{{ $p->reference }}</td></tr><tr><td class="muted">Received by</td><td class="r fl">{{ $p->receiver->name }}</td></tr></table></td></tr></table>
  <table class="items" style="width:100%"><thead><tr><th>Invoice</th><th class="r">Invoice total</th><th class="r">Applied</th><th class="r">Balance after</th></tr></thead><tbody>
    @foreach($p->allocations as $a)<tr><td class="mono">{{ $a->invoice->number }}</td><td class="r">{{ money($a->invoice->total, $cur, false) }}</td><td class="r">{{ money($a->amount, $cur, false) }}</td><td class="r">{{ money($a->invoice->balance(), $cur, false) }}</td></tr>@endforeach
  </tbody></table>
  <table style="width:100%;margin-top:10px"><tr><td style="width:55%"></td><td><table style="width:100%"><tr><td class="fl">Amount received</td><td class="r fl">{{ money($p->amount, $cur) }}</td></tr><tr><td class="due" style="padding:4px 6px">Outstanding balance</td><td class="r due" style="padding:4px 6px">{{ money($bal, $cur) }}</td></tr></table></td></tr></table>
  <p style="margin-top:12px;font-size:10.5px">Received with thanks: {{ \App\Services\AmountInWords::amount((float) $p->amount, $cur) }}.</p>
  <table style="width:100%;margin-top:28px;font-size:10px"><tr><td style="width:50%;border-top:1px solid #999;padding-top:4px">Received by: {{ $p->receiver->name }}</td><td style="width:8%"></td><td style="border-top:1px solid #999;padding-top:4px">Customer signature</td></tr></table>
</div>
