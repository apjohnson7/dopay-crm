<tr>
  <td class="n">{{ is_numeric($n) ? $n + 1 : '' }}</td>
  <td data-l="Product"><select class="inp" name="items[{{ $n }}][product_id]" data-product aria-label="Product"><option value="">Custom line…</option>
    @foreach($products as $p)<option value="{{ $p->id }}" @selected(($it['product_id'] ?? null) == $p->id) data-name="{{ $p->name }}" data-price="{{ $p->price }}" data-prices="{{ json_encode($currencyCodes->mapWithKeys(fn ($cc) => [$cc => $p->priceIn($cc)])) }}" data-taxable="{{ $p->taxable ? 1 : 0 }}" data-countries="{{ $p->countries->pluck('id')->implode(',') }}">{{ $p->name }}</option>@endforeach
  </select></td>
  <td data-l="Description" class="wide"><input class="inp" name="items[{{ $n }}][description]" data-f="description" value="{{ $it['description'] ?? '' }}" aria-label="Description"></td>
  <td data-l="Qty"><input class="inp tab" type="number" step="any" min="0" name="items[{{ $n }}][quantity]" data-f="quantity" value="{{ $it['quantity'] ?? 1 }}" style="width:72px" aria-label="Quantity"></td>
  <td data-l="Unit price"><input class="inp tab" type="number" step="any" min="0" name="items[{{ $n }}][unit_price]" data-f="unit_price" value="{{ $it['unit_price'] ?? '' }}" style="width:120px" aria-label="Unit price"></td>
  <td data-l="Disc %"><input class="inp tab" type="number" step="any" min="0" max="100" name="items[{{ $n }}][discount_pct]" data-f="discount_pct" value="{{ $it['discount_pct'] ?? 0 }}" style="width:64px" aria-label="Discount"></td>
  <td data-l="Tax %"><input class="inp tab" type="number" step="any" min="0" max="100" name="items[{{ $n }}][tax_pct]" data-f="tax_pct" value="{{ $it['tax_pct'] ?? '' }}" style="width:64px" aria-label="Tax"></td>
  <td class="lt r tab" style="padding-top:12px">0</td>
  <td><button type="button" class="btn ghost sm" data-del-line aria-label="Remove line">✕</button></td>
</tr>
