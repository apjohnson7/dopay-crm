<tr><td class="n">{{ is_numeric($n) ? $n + 1 : '' }}</td>
@foreach($def['lines'] as $col)
  @php $k = $col['key']; $v = $l[$k] ?? ''; $name = "lines[$n][$k]"; @endphp
  <td data-l="{{ $col['label'] }}" class="{{ !empty($col['wide']) ? 'wide' : '' }}">
    @switch($col['type'])
      @case('category')<select class="inp" name="{{ $name }}" aria-label="{{ $col['label'] }}">@foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) $v === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>@break
      @case('category_or_inflow')<select class="inp" name="{{ $name }}" aria-label="{{ $col['label'] }}"><option value="inflow" @selected($v === 'inflow')>Inflow</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) $v === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>@break
      @case('money')<input class="inp tab" type="number" step="any" min="0" name="{{ $name }}" value="{{ $v }}" style="text-align:right;min-width:110px" @if($k === 'amount') data-amount @endif aria-label="{{ $col['label'] }}">@break
      @case('date')<input class="inp" type="date" name="{{ $name }}" value="{{ $v }}" aria-label="{{ $col['label'] }}">@break
      @default<input class="inp" name="{{ $name }}" value="{{ $v }}" @if($k === 'memo_ref') list="memos" @endif aria-label="{{ $col['label'] }}">
    @endswitch
  </td>
@endforeach
<td><button type="button" class="btn ghost sm" data-del-line aria-label="Remove line">✕</button></td></tr>
