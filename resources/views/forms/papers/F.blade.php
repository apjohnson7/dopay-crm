<div class="paper fp">@include('forms.papers._top')
@php
  $period = $form->datum('period');
  $months = $period === 'Annual' ? range(1, 12) : ($period === 'Quarterly' ? range(((int) $form->datum('quarter') - 1) * 3 + 1, (int) $form->datum('quarter') * 3) : [(int) $form->datum('month')]);
  $b = $form->budgetLines->groupBy('expense_category_id')->map(fn ($g) => $g->pluck('amount', 'month'));
  $notes = $form->budgetLines->pluck('note', 'expense_category_id');
  $cats = \App\Models\ExpenseCategory::orderBy('position')->get();
  $v = fn ($c, $m) => (float) ($b[$c][$m] ?? 0);
  $sum = fn ($cs, $m = null) => $cs->sum(fn ($c) => $m ? $v($c->id, $m) : collect($months)->sum(fn ($mm) => $v($c->id, $mm)));
  $rec = $cats->where('group', 'recurrent'); $fix = $cats->where('group', 'fixed');
@endphp
<h1 class="ft">{{ $form->country->legal_entity }} – {{ $period === 'Annual' ? 'Annual Budget' : $period.' Expense Budget' }}</h1>
<p class="meta">{{ $period === 'Monthly' ? 'Month: '.date('F', mktime(0, 0, 0, $months[0], 1)).' '.$form->datum('year') : 'Year: '.$form->datum('year').($period === 'Quarterly' ? ' | Quarter: '.$form->datum('quarter') : '') }} | Currency: {{ $cur }} | Prepared by: {{ $form->preparer->name }}</p>
<div class="p-tw"><table class="ft" style="font-size:{{ count($months) > 3 ? '8.5' : '10.5' }}px"><thead><tr><th>S/N</th><th>Expense Category</th>@foreach($months as $m)<th class="r">{{ date('M', mktime(0, 0, 0, $m, 1)) }}</th>@endforeach @if(count($months) > 1)<th class="r">Total</th>@endif @if($period !== 'Annual')<th>{{ $period === 'Quarterly' ? 'Remark' : 'Notes' }}</th>@endif</tr></thead><tbody>
@foreach([['OPERATING EXPENSES', $rec, 'TOTAL RECURRENT EXPENDITURE'], ['FIXED ASSET ACQUISITIONS', $fix, 'TOTAL FIXED ASSET EXPENDITURE']] as [$head, $group, $totalLabel])
  <tr class="sh"><td colspan="{{ count($months) + 4 }}">{{ $head }}</td></tr>
  @foreach($group as $c)<tr><td>{{ $c->position }}</td><td>{{ $c->name }}</td>@foreach($months as $m)<td class="r">{{ money($v($c->id, $m), $cur, false) }}</td>@endforeach @if(count($months) > 1)<td class="r fl">{{ money($sum(collect([$c])), $cur, false) }}</td>@endif @if($period !== 'Annual')<td style="font-size:9px;color:#555">{{ $notes[$c->id] ?? $c->note }}</td>@endif</tr>@endforeach
  <tr class="tt"><td colspan="2">{{ $totalLabel }}</td>@foreach($months as $m)<td class="r">{{ money($sum($group, $m), $cur, false) }}</td>@endforeach @if(count($months) > 1)<td class="r">{{ money($sum($group), $cur, false) }}</td>@endif @if($period !== 'Annual')<td></td>@endif</tr>
@endforeach
<tr class="tt"><td colspan="2">TOTAL EXPENDITURE</td>@foreach($months as $m)<td class="r">{{ money($sum($cats, $m), $cur, false) }}</td>@endforeach @if(count($months) > 1)<td class="r">{{ money($sum($cats), $cur, false) }}</td>@endif @if($period !== 'Annual')<td></td>@endif</tr>
</tbody></table></div>
<table style="width:100%;margin-top:14px"><tr><td><b>Prepared by (Accountant):</b><br>@include('forms.papers._sig', ['s' => $sigs[0] ?? null])</td><td><b>Reviewed by (Financial Controller):</b><br>@include('forms.papers._sig', ['s' => $sigs[1] ?? null])</td></tr></table>
@include('forms.papers._foot')</div>
