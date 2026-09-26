/* Dopay CRM — small, dependency-free helpers for the Blade screens */
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const ZERO = ['UGX', 'XAF', 'XOF'];
  const fmt = (n, cur) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: ZERO.includes(cur) ? 0 : 2, maximumFractionDigits: ZERO.includes(cur) ? 0 : 2 });

  // Mobile sidebar
  document.addEventListener('click', e => {
    if (e.target.closest('[data-open-side]')) { $('#side').classList.add('open'); $('#sideScrim').hidden = false; }
    if (e.target.closest('[data-close-side]')) { $('#side').classList.remove('open'); $('#sideScrim').hidden = true; }
    const open = e.target.closest('[data-open-modal]');
    if (open) { e.preventDefault(); const d = document.getElementById(open.dataset.openModal); if (d) d.showModal(); }
    if (e.target.closest('[data-close-modal]')) { e.preventDefault(); e.target.closest('dialog').close(); }
  });

  // Repeating line tables: <table data-lines> with a <template> row and [data-add-line] button
  function renumber(table) {
    $$('tbody tr', table).forEach((tr, i) => {
      $$('[name]', tr).forEach(inp => { inp.name = inp.name.replace(/\[\d+\]|\[__\]/, '[' + i + ']'); });
      const n = $('.n', tr); if (n) n.textContent = i + 1;
    });
  }
  document.addEventListener('click', e => {
    const add = e.target.closest('[data-add-line]');
    if (add) {
      const table = document.getElementById(add.dataset.addLine);
      const tpl = $('template', table.parentElement) || $('template', table);
      $('tbody', table).appendChild(tpl.content.cloneNode(true));
      renumber(table); recalc();
    }
    const del = e.target.closest('[data-del-line]');
    if (del) {
      const table = del.closest('table');
      if ($$('tbody tr', table).length > 1) del.closest('tr').remove(); else $$('input', del.closest('tr')).forEach(i => i.value = '');
      renumber(table); recalc();
    }
  });

  // Invoice builder: product picks fill description, price and tax; totals update live
  document.addEventListener('change', e => {
    const sel = e.target.closest('select[data-product]');
    if (sel) {
      const o = sel.selectedOptions[0]; const tr = sel.closest('tr');
      if (o && o.value) {
        $('[data-f=description]', tr).value = o.dataset.name;
        const cur = $('#builder').dataset.cur; let prices = {}; try { prices = JSON.parse(o.dataset.prices || '{}'); } catch (x) {}
        $('[data-f=unit_price]', tr).value = prices[cur] ?? o.dataset.price;
        $('[data-f=tax_pct]', tr).value = o.dataset.taxable === '1' ? ($('#builder').dataset.tax || 0) : 0;
      }
      recalc();
    }
    if (e.target.matches('#customer_id')) {
      const o = e.target.selectedOptions[0];
      $('#builder').dataset.tax = o?.dataset.tax || 0; $('#builder').dataset.cur = o?.dataset.cur || '';
      const card = $('#autofill');
      if (card && o && o.value) {
        card.hidden = false;
        ['name', 'address', 'contact', 'tin', 'terms', 'balance'].forEach(k => { const el = $('[data-af=' + k + ']', card); if (el) el.textContent = o.dataset[k] || '—'; });
        const issue = $('#issue_date').value; if (issue && o.dataset.days) { const d = new Date(issue); d.setDate(d.getDate() + Number(o.dataset.days)); $('#due_date').value = d.toISOString().slice(0, 10); }
        $$('[data-f=tax_pct]').forEach(i => { if (i.value === '' || i.dataset.auto !== '0') i.value = o.dataset.tax; });
        $$('select[data-product] option').forEach(op => { if (op.value) op.hidden = op.dataset.countries && !op.dataset.countries.split(',').includes(o.dataset.country); });
      }
      recalc();
    }
  });
  document.addEventListener('input', e => { if (e.target.closest('[data-calc]')) recalc(); });

  function recalc() {
    const b = $('#builder'); if (!b) return;
    const cur = b.dataset.cur || '';
    let sub = 0, disc = 0, tax = 0;
    $$('#items tbody tr').forEach(tr => {
      const q = +($('[data-f=quantity]', tr)?.value || 0), p = +($('[data-f=unit_price]', tr)?.value || 0), d = +($('[data-f=discount_pct]', tr)?.value || 0), t = +($('[data-f=tax_pct]', tr)?.value || 0);
      const g = q * p, dd = g * d / 100; sub += g; disc += dd; tax += (g - dd) * t / 100;
      const lt = $('.lt', tr); if (lt) lt.textContent = fmt(g - dd, cur);
    });
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = (cur ? cur + ' ' : '') + fmt(v, cur); };
    set('t-sub', sub); set('t-disc', disc); set('t-tax', tax); set('t-total', sub - disc + tax);
    // generic form totals
    const ft = $('#form-total'); if (ft) { let s = 0; $$('[data-amount]').forEach(i => s += +(i.value || 0)); ft.textContent = fmt(s, ft.dataset.cur); }
  }
  recalc();

  // Payment allocation: oldest first
  document.addEventListener('click', e => {
    if (!e.target.closest('[data-auto-allocate]')) return;
    let left = +($('#amount').value || 0);
    $$('[data-alloc]').forEach(i => { const v = Math.min(+i.dataset.max, Math.max(left, 0)); i.value = v > 0 ? v : ''; left -= v; });
  });

  // Share buttons: open the channel, and log the share on the server
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-log-share]');
    if (!a) return;
    fetch(a.dataset.logShare, { method: 'POST', headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').content, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ channel: a.dataset.channel }) });
    if (a.dataset.copy) { navigator.clipboard?.writeText(a.dataset.copy); e.preventDefault(); a.querySelector('span') && (a.querySelector('span').textContent = 'Copied'); }
  });

  // Chat: Enter sends, Shift+Enter adds a line; keep the thread scrolled to the newest message
  document.addEventListener('keydown', e => {
    if (e.target.id === 'msgText' && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.target.form.requestSubmit(); }
  });
  const chat = $('#chatBody'); if (chat) chat.scrollTop = chat.scrollHeight;

  // People picker filter
  document.addEventListener('input', e => {
    if (e.target.id !== 'peopleQ') return;
    const q = e.target.value.toLowerCase();
    $$('#peopleList .pp').forEach(l => l.hidden = q && !l.textContent.toLowerCase().includes(q));
  });
})();

/* Spreadsheet-style tables: every .tw > table.tbl gets row numbers, a filter box,
   click-to-sort headings, a totals row for money columns, CSV export and print. */
(function () {
  const NUMRX = /^(?:([A-Z]{3})\s)?(-?[\d,]+(?:\.\d+)?)$/;
  const cellTxt = c => c ? (c.firstChild ? c.firstChild.textContent : c.textContent).trim() : '';
  const esc = s => String(s).replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));

  function totals(gw) {
    const t = gw.querySelector('table'); if (!t || !t.tHead || gw.hasAttribute('data-nosum')) return;
    t.querySelectorAll('tfoot.gt-auto').forEach(f => f.remove());
    const ths = [...t.tHead.rows[0].cells];
    const vis = [...t.tBodies[0].rows].filter(r => !r.hidden && !r.classList.contains('tt'));
    const rows = vis.filter(r => { const p = r.querySelector('.pill'); return !(p && /^(Failed|Reversed|Cancelled|Void|Rejected|Refunded)$/i.test(p.textContent.trim())); });
    if (rows.length < 2 || t.querySelector('tr.tt')) return;
    const out = ths.map((th, j) => {
      if (!th.classList.contains('num')) return null;
      let cur = null, sum = 0, dec = 0, any = false;
      for (const r of rows) {
        const tx = cellTxt(r.cells[j]); if (!tx || tx === '—' || tx === '-') continue;
        const m = tx.match(NUMRX); if (!m) return null;
        const c = m[1] || ''; if (cur !== null && c !== cur) return null; cur = c; any = true;
        dec = Math.max(dec, (m[2].split('.')[1] || '').length); sum += parseFloat(m[2].replace(/,/g, ''));
      }
      return any ? (cur ? cur + ' ' : '') + sum.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : null;
    });
    if (!out.some(Boolean)) return;
    const first = ths.findIndex(th => !th.classList.contains('rn'));
    const tf = document.createElement('tfoot'); tf.className = 'gt-auto';
    tf.innerHTML = '<tr>' + ths.map((th, j) => `<td class="${th.classList.contains('num') ? 'num' : ''}${th.classList.contains('rn') ? ' rn' : ''}">${j === first && !out[j] ? 'Total · ' + rows.length + ' rows' : esc(out[j] || '')}</td>`).join('') + '</tr>';
    t.appendChild(tf);
  }
  function sort(th) {
    const t = th.closest('table'), j = th.cellIndex, dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
    [...t.tHead.rows[0].cells].forEach(h => h.removeAttribute('aria-sort'));
    th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
    const isNum = th.classList.contains('num');
    const key = r => {
      const c = r.cells[j];
      if (isNum) { const n = parseFloat(cellTxt(c).replace(/[^\d.\-]/g, '')); return isNaN(n) ? -Infinity : n; }
      const tx = c ? c.textContent.trim() : ''; const dm = tx.match(/^\d{1,2} [A-Z][a-z]{2} \d{4}/); const d = dm ? Date.parse(dm[0]) : NaN;
      return isNaN(d) ? tx.toLowerCase() : d;
    };
    const tb = t.tBodies[0];
    [...tb.rows].filter(r => !r.classList.contains('tt')).sort((a, b) => { const x = key(a), y = key(b); return (x > y ? 1 : x < y ? -1 : 0) * dir; })
      .forEach((r, i) => { tb.appendChild(r); const n = r.querySelector('td.rn'); if (n) n.textContent = i + 1; });
    tb.querySelectorAll('tr.tt').forEach(r => tb.appendChild(r));
  }
  function csv(gw) {
    const t = gw.querySelector('table'), ths = [...t.tHead.rows[0].cells];
    const keep = ths.map(th => !th.classList.contains('rn') && th.textContent.trim());
    const q = v => '"' + String(v).replace(/"/g, '""') + '"';
    const txt = c => [...c.childNodes].map(n => n.textContent.trim()).filter(Boolean).join(' · ');
    const lines = [ths.filter((_, j) => keep[j]).map(th => q(th.textContent.trim())).join(',')];
    [...t.tBodies[0].rows].filter(r => !r.hidden).forEach(r => lines.push([...r.cells].filter((_, j) => keep[j]).map(c => q(txt(c))).join(',')));
    t.querySelectorAll('tfoot tr').forEach(r => lines.push([...r.cells].filter((_, j) => keep[j]).map(c => q(c.textContent.trim())).join(',')));
    const title = (document.querySelector('.ph h1') || {}).textContent || 'table';
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    a.download = ('dopay-' + title + '-' + new Date().toISOString().slice(0, 10) + '.csv').toLowerCase().replace(/[^a-z0-9.-]+/g, '-');
    document.body.appendChild(a); a.click(); a.remove();
  }
  function print(gw) {
    document.body.classList.add('pgrid'); gw.classList.add('pg-on');
    let h = gw; while (h.parentElement && !h.parentElement.matches('main, .content')) h = h.parentElement; h.classList.add('pg-host');
    const done = () => { document.body.classList.remove('pgrid'); gw.classList.remove('pg-on'); h.classList.remove('pg-host'); };
    window.addEventListener('afterprint', done, { once: true }); window.print(); setTimeout(done, 1500);
  }
  function enhance(table) {
    if (table.closest('.gw') || table.closest('form') || !table.tHead || !table.tBodies[0]) return;
    const tw = table.closest('.tw') || table; const gw = document.createElement('div'); gw.className = 'gw';
    tw.parentNode.insertBefore(gw, tw); gw.appendChild(tw);
    const body = [...table.tBodies[0].rows].filter(r => !r.classList.contains('tt'));
    gw.dataset.n = body.length;
    const hr = table.tHead.rows[0]; const rn = document.createElement('th'); rn.className = 'rn'; hr.insertBefore(rn, hr.firstChild);
    [...table.tBodies[0].rows].forEach((r, i) => { const td = document.createElement('td'); td.className = 'rn'; td.textContent = r.classList.contains('tt') ? '' : i + 1; r.insertBefore(td, r.firstChild); });
    [...hr.cells].forEach(th => { if (!th.classList.contains('rn') && th.textContent.trim()) { th.classList.add('so'); th.dataset.gs = ''; th.tabIndex = 0; th.title = 'Sort'; } });
    if (body.length >= 3) {
      const bar = document.createElement('div'); bar.className = 'gt';
      bar.innerHTML = '<label class="gt-f"><input type="search" data-gq placeholder="Filter these rows" aria-label="Filter these rows"></label><span class="gt-n" data-gn>' + body.length + ' rows</span><span class="top-sp" style="flex:1"></span><button type="button" class="btn sm ghost" data-gx="csv">Export</button><button type="button" class="btn sm ghost" data-gx="print">Print</button>';
      gw.insertBefore(bar, tw);
    }
    totals(gw);
  }
  document.querySelectorAll('.tw > table.tbl').forEach(enhance);
  document.addEventListener('input', e => {
    const q = e.target.closest('[data-gq]'); if (!q) return;
    const gw = q.closest('.gw'), v = q.value.trim().toLowerCase(); let n = 0;
    gw.querySelectorAll('tbody tr:not(.tt)').forEach(r => { const show = !v || r.textContent.toLowerCase().includes(v); r.hidden = !show; if (show) n++; });
    gw.querySelector('[data-gn]').textContent = v ? n + ' of ' + gw.dataset.n + ' rows' : gw.dataset.n + ' rows';
    totals(gw);
  });
  document.addEventListener('click', e => {
    const th = e.target.closest('th[data-gs]'); if (th) { sort(th); return; }
    const x = e.target.closest('[data-gx]'); if (x) { const gw = x.closest('.gw'); x.dataset.gx === 'csv' ? csv(gw) : print(gw); }
  });
  document.addEventListener('keydown', e => { const th = e.target.closest && e.target.closest('th[data-gs]'); if (th && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); sort(th); } });
})();
