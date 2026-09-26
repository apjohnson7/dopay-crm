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
