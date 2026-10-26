/*!
 * S3curity H3LIX — accessible combobox (WAI-ARIA 1.2 combobox + listbox), brief 2026-10-12 §2.
 * Progressive enhancement of a server-rendered <select data-hx-combobox="employees|local">: without JS the select works as before.
 *   employees — searches /api/lookup/employees (server-side scope); results are limited to the select's own options when it has any,
 *               so each form keeps its rules (company, team, leavers).
 *   local     — filters the select's options in the browser (users, candidates).
 * <input data-hx-global-search> in the top bar searches employees and opens the chosen profile; Enter without a choice submits the search form.
 * <input data-hx-checklist-filter="#id"> filters a list of checkbox labels.
 * Keyboard: ArrowDown/ArrowUp move, Enter chooses, Escape closes (or clears when closed), Home/End jump; results are announced.
 */
(() => {
  'use strict';
  const MIN = 2;
  const T = (el, key, fallback) => (el && el.dataset[key]) || fallback;
  let seq = 0;

  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  async function remote(q, company) {
    const u = new URL('/api/lookup/employees', location.origin);
    u.searchParams.set('q', q);
    if (company) u.searchParams.set('company', company);
    const r = await fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error(String(r.status));
    return (await r.json()).results || [];
  }

  function build({ input, onChoose, search, labels, describe }) {
    const id = `hx-cb-${++seq}`;
    const wrap = document.createElement('div');
    wrap.className = 'hx-combo';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const list = document.createElement('ul');
    list.className = 'hx-combo__list';
    list.id = `${id}-list`;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    const live = document.createElement('div');
    live.className = 'hx-visually-hidden';
    live.setAttribute('aria-live', 'polite');
    wrap.append(list, live);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('autocomplete', 'off');
    if (describe) input.setAttribute('aria-describedby', describe);
    let items = [];
    let active = -1;
    let lastQ = null;

    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
    const setActive = (i) => {
      const opts = list.querySelectorAll('[role="option"]');
      if (!opts.length) return;
      active = (i + opts.length) % opts.length;
      opts.forEach((o, n) => o.setAttribute('aria-selected', String(n === active)));
      input.setAttribute('aria-activedescendant', opts[active].id);
      opts[active].scrollIntoView({ block: 'nearest' });
    };
    const render = (res, q) => {
      items = res;
      list.textContent = '';
      res.forEach((it, n) => {
        const li = document.createElement('li');
        li.id = `${id}-o${n}`;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', 'false');
        li.className = 'hx-combo__option';
        const main = document.createElement('span');
        main.className = 'hx-combo__main';
        main.textContent = it.label;
        li.appendChild(main);
        if (it.meta) { const m = document.createElement('span'); m.className = 'hx-combo__meta'; m.textContent = it.meta; li.appendChild(m); }
        li.addEventListener('mousedown', (e) => e.preventDefault());
        li.addEventListener('click', () => choose(n));
        list.appendChild(li);
      });
      if (!res.length && q.length >= MIN) {
        const li = document.createElement('li');
        li.className = 'hx-combo__empty';
        li.textContent = labels.none;
        list.appendChild(li);
      }
      const open = q.length >= MIN || res.length > 0;
      list.hidden = !open;
      input.setAttribute('aria-expanded', String(open && res.length > 0));
      active = -1;
      live.textContent = q.length < MIN ? '' : (res.length ? labels.count.replace(':n', String(res.length)) : labels.none);
    };
    const choose = (n) => {
      const it = items[n];
      if (!it) return;
      close();
      onChoose(it);
    };
    const run = debounce(async () => {
      const q = input.value.trim();
      if (q === lastQ) return;
      lastQ = q;
      try { render(q.length < MIN && !search.local ? [] : await search(q), q); } catch (e) { live.textContent = labels.error; }
    }, 180);

    input.addEventListener('input', () => { lastQ = null; run(); });
    input.addEventListener('focus', () => { if (search.local) { lastQ = null; run(); } });
    input.addEventListener('keydown', (e) => {
      const open = !list.hidden && items.length > 0;
      if (e.key === 'ArrowDown') { e.preventDefault(); if (!open) { lastQ = null; run(); } else setActive(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (open) setActive(active - 1); }
      else if (e.key === 'Home' && open) { e.preventDefault(); setActive(0); }
      else if (e.key === 'End' && open) { e.preventDefault(); setActive(items.length - 1); }
      else if (e.key === 'Enter') { if (open && active >= 0) { e.preventDefault(); choose(active); } }
      else if (e.key === 'Escape') { if (!list.hidden) { e.preventDefault(); e.stopPropagation(); close(); } else if (input.value) { e.preventDefault(); input.value = ''; onChoose(null); } }
      else if (e.key === 'Tab') close();
    });
    input.addEventListener('blur', () => setTimeout(close, 120));
    return { close };
  }

  const labelsFrom = (el) => ({
    none: T(el, 'hxNone', 'No matches'), count: T(el, 'hxCount', ':n results'), error: T(el, 'hxError', 'Search is unavailable. Use the list instead.'),
  });

  // Enhanced selects.
  document.querySelectorAll('select[data-hx-combobox]').forEach((select) => {
    if (select.multiple) return;
    const mode = select.dataset.hxCombobox;
    const form = select.form;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = select.className.replace(/\bhx-input--sm\b/, '') + ' hx-combo__input';
    input.id = `${select.id || 'hx-sel'}-combo`;
    input.placeholder = T(select, 'hxPlaceholder', 'Type a name or number');
    input.required = select.required;
    const label = select.id ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`) : null;
    if (label) label.htmlFor = input.id;
    else if (select.getAttribute('aria-label')) input.setAttribute('aria-label', select.getAttribute('aria-label'));
    select.parentNode.insertBefore(input, select);
    select.hidden = true;
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.required = false;
    const optionText = () => { const o = select.selectedOptions[0]; return o && o.value ? o.textContent.trim() : ''; };
    input.value = optionText();
    const allowed = () => Array.from(select.options).filter((o) => o.value && !o.disabled);
    const local = async (q) => {
      const n = q.toLowerCase();
      return allowed().filter((o) => !n || o.textContent.toLowerCase().includes(n)).slice(0, 50).map((o) => ({ value: o.value, label: o.textContent.trim() }));
    };
    local.local = true;
    const search = mode === 'employees'
      ? async (q) => {
        const companySel = form ? form.querySelector('select[name="company_id"]') : null;
        const company = (companySel && companySel.value) || select.dataset.hxCompany || (form && form.dataset.company) || '';
        const ok = new Set(allowed().map((o) => o.value));
        const restrict = select.options.length > 1;
        return (await remote(q, company)).filter((r) => !restrict || ok.has(String(r.id))).map((r) => ({
          value: String(r.id), label: `${r.no} · ${r.name}${r.name_ar ? ` · ${r.name_ar}` : ''}`, meta: [r.position, r.company].filter(Boolean).join(' · '),
        }));
      }
      : local;
    build({ input, search, labels: labelsFrom(select), describe: select.getAttribute('aria-describedby'),
      onChoose: (it) => {
        if (it && !Array.from(select.options).some((o) => o.value === it.value)) select.add(new Option(it.label, it.value));
        select.value = it ? it.value : '';
        input.value = it ? it.label : '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
      } });
    // Keep the visible text in sync when other scripts change the select (e.g. the company filter clears it).
    if (form) form.addEventListener('change', (e) => { if (e.target !== select && e.target.name === 'company_id') setTimeout(() => { input.value = optionText(); }, 0); });
    if (select.classList.contains('is-invalid')) input.setAttribute('aria-invalid', 'true');
  });

  // Global employee search in the top bar.
  const g = document.querySelector('input[data-hx-global-search]');
  if (g) {
    build({ input: g, labels: labelsFrom(g), search: async (q) => (await remote(q, '')).map((r) => ({
      value: String(r.id), label: `${r.no} · ${r.name}`, meta: [r.position, r.company].filter(Boolean).join(' · '),
    })), onChoose: (it) => { if (it) location.assign(`/employees/${encodeURIComponent(it.value)}`); } });
  }

  // Checkbox list filter.
  document.querySelectorAll('input[data-hx-checklist-filter]').forEach((f) => {
    const box = document.querySelector(f.dataset.hxChecklistFilter);
    if (!box) return;
    f.hidden = false;
    f.addEventListener('input', () => {
      const n = f.value.trim().toLowerCase();
      box.querySelectorAll('label.hx-check').forEach((l) => {
        const cb = l.querySelector('input');
        const companyHidden = cb && cb.disabled && cb.dataset.company; // hidden by the company filter: keep hidden
        l.classList.toggle('hx-filtered-out', !companyHidden && n !== '' && !l.textContent.toLowerCase().includes(n));
      });
    });
  });
})();
