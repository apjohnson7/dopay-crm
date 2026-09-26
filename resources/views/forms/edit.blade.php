@extends('layouts.app')
@section('title', ($form->reference ?: 'New').' · '.$def['name'])
@section('content')
@php
  $lines = old('lines', $form->exists ? $form->lines->map(fn ($l) => ['date' => optional($l->line_date)->toDateString(), 'description' => $l->description, 'category' => $l->is_inflow ? 'inflow' : $l->expense_category_id, 'amount' => (float) $l->amount, 'inflow' => (float) $l->inflow, 'outflow' => (float) $l->outflow, 'account_details' => $l->account_details, 'memo_ref' => $l->memo_ref, 'party' => $l->party, 'notes' => $l->notes])->all() : [[]]);
  if (! $lines) { $lines = [[]]; }
  $budget = $form->exists ? $form->budgetLines->groupBy('expense_category_id')->map(fn ($g) => $g->pluck('amount', 'month')) : collect();
  $period = old('data.period', $form->datum('period', 'Monthly'));
  $months = $period === 'Annual' ? range(1, 12) : ($period === 'Quarterly' ? range(((int) $form->datum('quarter', 1) - 1) * 3 + 1, ((int) $form->datum('quarter', 1)) * 3) : [(int) $form->datum('month', now()->month)]);
@endphp
<div class="ph"><div><h1>{{ $form->reference ?: 'New '.$def['name'] }}</h1><p class="sub">{{ $def['appendix'] }} · the entity, currency and reference fill in from the branch you choose.</p></div></div>
<form method="post" enctype="multipart/form-data" action="{{ $form->exists ? route('forms.update', $form) : route('forms.store', $form->type) }}" class="stack">@csrf @if($form->exists) @method('put') @endif
  <section class="card pad"><div class="bstep"><i>1</i><h3>Entity & branch</h3></div><div class="fg">
    <div class="f"><label for="branch_id">Branch</label><select id="branch_id" name="branch_id" @if($form->reference) disabled @endif>@foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $form->branch_id) == $b->id)>{{ $b->name }} · {{ $b->country->legal_entity }}</option>@endforeach</select>@if($form->reference)<input type="hidden" name="branch_id" value="{{ $form->branch_id }}"><span class="hint">Locked once a reference is issued</span>@endif</div>
    <div class="f"><label for="currency_code">Currency</label><select id="currency_code" name="currency_code" @if(in_array($form->type, ['F', 'G'])) disabled @endif>@foreach($currencies as $c)<option @selected(old('currency_code', $form->currency_code) === $c)>{{ $c }}</option>@endforeach</select></div>
  </div></section>

  <section class="card pad"><div class="bstep"><i>2</i><h3>Details</h3></div><div class="fg">
    @foreach($def['fields'] as $f)
      @php $name = 'data['.$f['key'].']'; $val = old('data.'.$f['key'], $form->datum($f['key'])); $id = 'd-'.$f['key']; @endphp
      <div class="f {{ ($f['full'] ?? false) || $f['type'] === 'textarea' ? 'full' : '' }}"><label for="{{ $id }}">{{ $f['label'] }}@if(!empty($f['required'])) <span class="muted">*</span>@endif</label>
        @switch($f['type'])
          @case('select')<select id="{{ $id }}" name="{{ $name }}">@foreach($f['options'] as $v => $l)<option value="{{ $v }}" @selected((string) $val === (string) $v)>{{ $l }}</option>@endforeach</select>@break
          @case('textarea')<textarea id="{{ $id }}" name="{{ $name }}">{{ $val }}</textarea>@break
          @case('user')<select id="{{ $id }}" name="{{ $name }}">@foreach($colleagues as $c)<option value="{{ $c->id }}" @selected((int) $val === $c->id)>{{ $c->name }} · {{ $c->roleName() }}</option>@endforeach</select>@break
          @case('country')<select id="{{ $id }}" name="{{ $name }}">@foreach($countries as $c)<option value="{{ $c->id }}" @selected((int) $val === $c->id)>{{ $c->legal_entity }}</option>@endforeach</select>@break
          @case('month_number')<select id="{{ $id }}" name="{{ $name }}">@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected((int) $val === $m)>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>@endforeach</select>@break
          @case('money')<input id="{{ $id }}" name="{{ $name }}" type="number" step="any" value="{{ $val }}">@break
          @default<input id="{{ $id }}" name="{{ $name }}" type="{{ in_array($f['type'], ['date', 'month', 'number']) ? $f['type'] : 'text' }}" value="{{ $val }}" @if($f['key'] === 'memo_ref') list="memos" @endif>
        @endswitch
        @if(!empty($f['hint']))<span class="hint">{{ $f['hint'] }}</span>@endif
      </div>
    @endforeach
    @if($form->type === 'A')<div class="f"><label for="cert">Or link a Receipt Reimbursement Certification</label><select id="cert" name="data[certification_ref]"><option value="">None</option>@foreach($certifications as $c)<option @selected($form->datum('certification_ref') === $c->reference)>{{ $c->reference }}</option>@endforeach</select></div>@endif
  </div></section>

  <section class="card pad"><div class="bstep"><i>3</i><h3>{{ $def['lines'] === 'budget' ? 'Budget by category' : 'Lines' }}</h3></div>
  @if($def['lines'] === 'budget')
    <p class="small muted" style="margin-bottom:8px">Save after changing the template or period to refresh the month columns.</p>
    <div class="tw"><table class="ftab bud"><thead><tr><th>#</th><th>Expense category</th>@foreach($months as $m)<th class="r">{{ date('M', mktime(0, 0, 0, $m, 1)) }}</th>@endforeach<th>Notes</th></tr></thead><tbody>
      @foreach($categories as $c)
        @if($c->position === 1)<tr><td></td><td colspan="{{ count($months) + 2 }}" class="lbl" style="padding-top:8px">Operating expenses</td></tr>@endif
        @if($c->position === 15)<tr><td></td><td colspan="{{ count($months) + 2 }}" class="lbl" style="padding-top:14px">Fixed asset acquisitions</td></tr>@endif
        <tr><td class="n">{{ $c->position }}</td><td class="bc" title="{{ $c->note }}">{{ $c->name }}</td>
          @foreach($months as $m)<td><input class="inp tab" type="number" min="0" step="any" style="text-align:right;min-width:96px" name="budget[{{ $c->id }}][{{ $m }}]" value="{{ old("budget.$c->id.$m", $budget[$c->id][$m] ?? '') }}" data-amount aria-label="{{ $c->name }} {{ $m }}"></td>@endforeach
          <td><input class="inp" name="budget_notes[{{ $c->id }}]" placeholder="{{ $c->note }}" style="min-width:180px"></td></tr>
      @endforeach
    </tbody></table></div>
  @else
    <div class="tw"><table class="ftab" id="lines"><thead><tr><th>#</th>@foreach($def['lines'] as $col)<th>{{ $col['label'] }}</th>@endforeach<th></th></tr></thead><tbody>
      @foreach($lines as $n => $l)@include('forms._line', ['n' => $n, 'l' => $l])@endforeach
    </tbody></table></div>
    <template>@include('forms._line', ['n' => '__', 'l' => []])</template>
    <div class="row" style="justify-content:space-between;margin-top:10px"><button type="button" class="btn sm" data-add-line="lines">+ Add line</button>@if($form->type !== 'G')<span class="small">Total: <b id="form-total" data-cur="{{ $form->currency_code }}">0</b></span>@endif</div>
  @endif
  <datalist id="memos">@foreach($memos as $m)<option value="{{ $m->reference }}">{{ $m->datum('subject') }} · {{ money($m->total, $m->currency_code) }}</option>@endforeach</datalist>
  </section>

  <section class="card pad"><div class="bstep"><i>4</i><h3>Supporting documents</h3></div>
    @if($form->exists)@foreach($form->documents as $d)<div class="small">📎 {{ $d->name }} · {{ $d->category }}</div>@endforeach @endif
    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" style="margin-top:8px">
    @if(!empty($def['requires_attachment']))<p class="hint" style="margin-top:6px">Required: original receipts/invoices for every line, or a linked Receipt Reimbursement Certification.</p>@endif
  </section>

  <section class="card"><div class="bbar"><span class="small muted">Submitting adds your e-signature and sends the form to the first approver.</span>
    <div class="row"><button class="btn" name="submit" value="0">Save draft</button><button class="btn pri" name="submit" value="1">Submit for approval</button></div></div></section>
</form>
@endsection
