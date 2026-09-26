@extends('layouts.app')
@section('title', 'Finance forms')
@section('content')
<div class="ph"><div><h1>Finance forms</h1><p class="sub">Your appendices, filled in online, signed electronically in order, then printed or shared. Each country uses the same forms with its own entity, currency, numbering and approvers.</p></div></div>
<div class="fcards">
  @foreach(config('dopay.forms') as $k => $d)
    <section class="card fcard"><span class="fapp">{{ $d['appendix'] }}</span><h3>{{ $d['name'] }}</h3>
      <p>{{ ['A' => 'Request approval to pay an expense. Four signatures before any payment.', 'B' => 'Cash paid out of the petty cash float. Prints two per A4 sheet.', 'C' => 'Certify small payments where the vendor gave no receipt.', 'F' => 'Monthly, quarterly or annual budget across the 21 categories.', 'G' => 'Monthly bank ledger reconciled to the statement. Due by the 15th.', 'J' => 'One entity funds another’s expenses as an intercompany payable.', 'K' => 'Request a cash advance; receipts due 5 working days after use.'][$k] }}</p>
      @can('forms.submit')<a class="btn pri sm" href="{{ route('forms.create', $k) }}">Fill online</a>@endcan</section>
  @endforeach
  @can('reports.view')<section class="card fcard" style="background:var(--surface-2)"><span class="fapp">Appendix F</span><h3>Budget monitoring</h3><p>Budget vs actual by category. Actuals post automatically from paid expenses.</p><a class="btn sm" href="{{ route('budget.index') }}">Open monitoring</a></section>@endcan
</div>
<div class="grid g-main" style="margin-top:20px">
  <section class="card"><div class="card-h"><h3>Submitted forms</h3>
    <form class="row"><input type="hidden" name="status" value="{{ $status }}"><select class="sel" name="type" onchange="this.form.submit()"><option value="">All form types</option>@foreach(config('dopay.forms') as $k => $d)<option value="{{ $k }}" @selected($type === $k)>{{ $d['appendix'] }} · {{ $d['name'] }}</option>@endforeach</select></form></div>
    <div class="row" style="padding:12px 18px 0">@foreach(['all' => 'All', 'mine' => 'Awaiting my signature', 'drafts' => 'Drafts & returned', 'in_approval' => 'In approval', 'open' => 'Approved & open', 'closed' => 'Closed'] as $k => $l)<a class="chip {{ $status === $k ? 'on' : '' }}" href="?status={{ $k }}&type={{ $type }}" style="text-decoration:none">{{ $l }}@if($k === 'mine')<b>{{ $awaiting->count() }}</b>@endif</a>@endforeach</div>
    <div class="tw"><table class="tbl cards"><thead><tr><th>Form</th><th>Entity</th><th>Prepared by</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
    @forelse($forms as $f)
      @php $step = $approvals->currentStep($f); @endphp
      <tr class="click" onclick="location='{{ route('forms.show', $f) }}'"><td class="lead" data-label="Form"><span class="mono strong">{{ $f->reference }}</span><span class="sub2">{{ $f->definition()['appendix'] }} · {{ $f->name() }}</span></td>
        <td data-label="Entity">{{ $f->country->name }}<span class="sub2">{{ $f->branch->name }}</span></td><td data-label="Prepared by">{{ $f->preparer->name }}</td>
        <td class="num" data-label="Amount">{{ (float) $f->total ? money($f->total, $f->currency_code) : '—' }}</td>
        <td data-label="Status">@include('partials.pill', ['label' => $f->statusLabel()])@if($step !== null)<span class="sub2">Step {{ $step + 1 }} of {{ count($approvals->steps($f)) }}</span>@endif</td></tr>
    @empty <tr><td colspan="5" class="empty">{{ $status === 'mine' ? 'Nothing is waiting for your signature.' : 'No forms here yet.' }}</td></tr> @endforelse
    </tbody></table></div>
    @if(method_exists($forms, 'links')){{ $forms->links() }}@endif
  </section>
  <div class="stack">
    <section class="card"><div class="card-h"><h3>Intercompany payables</h3><span class="small muted">Appendix J</span></div><div class="feed">
      @forelse($intercompany as $f)<a class="feed-i" href="{{ route('forms.show', $f) }}" style="text-decoration:none;color:inherit"><div class="t"><b>{{ $f->country->name }} owes {{ optional(\App\Models\Country::find($f->datum('paying_country_id')))->name }}</b><span>Terms due {{ fdate($f->approved_at?->addDays(config('dopay.forms.J.repayment_days'))) }}</span></div><div class="a">{{ money($f->total, $f->currency_code) }}</div></a>
      @empty <p class="pad small muted">No open intercompany balances.</p> @endforelse</div></section>
    <section class="card"><div class="card-h"><h3>Cash advances to liquidate</h3><span class="small muted">Appendix K</span></div><div class="feed">
      @forelse($advances as $f)<a class="feed-i" href="{{ route('forms.show', $f) }}" style="text-decoration:none;color:inherit"><div class="t"><b>{{ $f->preparer->name }}</b><span style="color:{{ $f->statusLabel() === 'Overdue' ? 'var(--bad)' : 'inherit' }}">{{ $f->status === 'approved' ? 'Approved, not yet disbursed' : 'Receipts due '.fdate($f->datum('liquidation_date')) }}</span></div><div class="a">{{ money($f->total, $f->currency_code) }}</div></a>
      @empty <p class="pad small muted">No advances outstanding.</p> @endforelse</div></section>
  </div>
</div>
@endsection
