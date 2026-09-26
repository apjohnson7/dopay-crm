{{-- Shared header for the accounting pages: title, country account tabs (Super Administrator) and section tabs. --}}
<div class="ph"><div><h1>{{ $title }}</h1><p class="sub">{{ $sub }}</p></div>@isset($actions)<div class="ph-act">{!! $actions !!}</div>@endisset</div>
@if($countries->count() > 1)
<div class="tabs">@foreach($countries as $c)<a class="{{ $c->id === $country->id ? 'on' : '' }}" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['country' => $c->id, 'account' => null]) }}">{{ $c->name }} · {{ config('accounting.chart_by_country.'.$c->iso2) === 'syscohada' ? 'SYSCOHADA' : 'IFRS' }}</a>@endforeach</div>
@endif
<div class="row" style="margin-bottom:14px">
  @foreach(['statements' => 'Statements', 'journals' => 'Journals', 'ledger' => 'General ledger', 'accounts' => 'Chart of accounts', 'reconciliation' => 'Reconciliation', 'close' => 'Month-end close'] as $k => $l)
    <a class="chip {{ $tab === $k ? 'on' : '' }}" style="text-decoration:none" href="{{ route('accounting.'.$k, ['country' => $country->id]) }}">{{ $l }}</a>
  @endforeach
  <span class="small muted" style="margin-left:auto">{{ $country->legal_entity }} · {{ $country->currency_code }} · books closed through {{ $country->books_closed_through ? \Carbon\Carbon::createFromFormat('!Y-m', $country->books_closed_through)->format('F Y') : '—' }}</span>
</div>
