/*!
 * S3curity H3LIX — interface behaviour (vanilla ES6, CSP-safe: no inline handlers)
 */
(() => {
  'use strict';

  const ready = (fn) => (document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', fn, { once: true })
    : fn());

  ready(() => {
    // Show / hide password
    document.querySelectorAll('[data-hx-toggle-password]').forEach((btn) => {
      const input = btn.closest('.hx-password')?.querySelector('input');
      if (!input) return;
      const showLabel = btn.dataset.hxShow || btn.textContent.trim() || 'Show';
      const hideLabel = btn.dataset.hxHide || 'Hide';
      const ariaShow = btn.dataset.hxAriaShow || btn.getAttribute('aria-label') || 'Show password';
      const ariaHide = btn.dataset.hxAriaHide || 'Hide password';
      btn.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        btn.textContent = reveal ? hideLabel : showLabel;
        btn.setAttribute('aria-label', reveal ? ariaHide : ariaShow);
        btn.setAttribute('aria-pressed', String(reveal));
        input.focus();
      });
    });

    // Loading state on submit (forms with data-hx-loading)
    document.querySelectorAll('form[data-hx-loading]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !form.checkValidity()) return;
        const btn = event.submitter || form.querySelector('[type="submit"]');
        if (!btn || btn.classList.contains('is-loading')) return;
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        const spinner = document.createElement('span');
        spinner.className = 'hx-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        btn.prepend(spinner);
        // Disable after the submit has been dispatched so the button value is still sent.
        setTimeout(() => { btn.disabled = true; }, 0);
      });
    });

    // Confirmation for destructive actions
    document.addEventListener('click', (event) => {
      const el = event.target.closest('[data-hx-confirm]');
      if (el && !window.confirm(el.dataset.hxConfirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);

    // Mobile sidebar (QA-03): focus moves into the drawer on open, the page behind it is inert while open,
    // focus returns to the toggle on close. Escape and a click on the overlay close it.
    const toggle = document.querySelector('[data-hx-sidebar-toggle]');
    const sidebar = document.getElementById('hx-sidebar');
    const main = document.querySelector('.hx-main');
    if (toggle && sidebar && main) {
      const mobile = window.matchMedia('(max-width: 960px)');
      const isOpen = () => document.body.classList.contains('hx-sidebar-open');
      const sync = () => {
        const open = isOpen() && mobile.matches;
        if (!mobile.matches) document.body.classList.remove('hx-sidebar-open');
        // Closed off-canvas drawer must not take focus; an open drawer makes the rest of the page inert.
        sidebar.inert = mobile.matches && !open;
        if (sidebar.inert && sidebar.contains(document.activeElement)) toggle.focus();
        main.inert = open;
        toggle.setAttribute('aria-expanded', String(open));
      };
      const open = () => {
        document.body.classList.add('hx-sidebar-open');
        sync();
        const first = sidebar.querySelector('[data-hx-sidebar-close], a[href], button:not([disabled])');
        if (first) first.focus();
      };
      const close = (restore) => {
        if (!isOpen()) return;
        document.body.classList.remove('hx-sidebar-open');
        sync();
        if (restore) toggle.focus();
      };
      toggle.addEventListener('click', () => (isOpen() ? close(true) : open()));
      sidebar.querySelector('[data-hx-sidebar-close]')?.addEventListener('click', () => close(true));
      document.addEventListener('click', (event) => {
        if (isOpen() && !event.target.closest('.hx-sidebar') && !event.target.closest('[data-hx-sidebar-toggle]')) close(true);
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) { event.preventDefault(); close(true); }
      });
      // Keep Tab inside the drawer even where inert is unsupported.
      sidebar.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab' || !isOpen()) return;
        const items = Array.from(sidebar.querySelectorAll('a[href], button:not([disabled])')).filter((el) => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      });
      mobile.addEventListener('change', sync);
      sync();
    }

    // Account menu (QA-01): native <details>; close on Escape (focus back to its summary) and on outside click.
    document.querySelectorAll('details[data-hx-account]').forEach((menu) => {
      const summary = menu.querySelector('summary');
      const syncExpanded = () => summary.setAttribute('aria-expanded', String(menu.open));
      menu.addEventListener('toggle', syncExpanded);
      syncExpanded();
      menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) { event.stopPropagation(); menu.open = false; summary.focus(); }
      });
      document.addEventListener('click', (event) => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    });

    // Date range filters (QA-02): From must not be after To. The server rejects it too; this only gives early feedback
    // and disables actions that would use the range.
    document.querySelectorAll('form[data-hx-range]').forEach((form) => {
      const from = form.querySelector('input[name="from"]');
      const to = form.querySelector('input[name="to"]');
      const msg = form.querySelector('.hx-range-error');
      if (!from || !to || !msg) return;
      const dependents = Array.from(document.querySelectorAll('[data-hx-range-dependent]'));
      dependents.forEach((b) => { b.dataset.hxServerDisabled = b.disabled ? '1' : ''; });
      const check = () => {
        const bad = from.value !== '' && to.value !== '' && to.value < from.value;
        to.setCustomValidity(bad ? msg.dataset.hxRangeMsg : '');
        [from, to].forEach((input) => {
          input.classList.toggle('is-invalid', bad);
          if (bad) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', msg.id); }
          else { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); }
        });
        if (bad) { msg.textContent = msg.dataset.hxRangeMsg; msg.hidden = false; } else { msg.hidden = true; }
        dependents.forEach((b) => { b.disabled = bad || b.dataset.hxServerDisabled === '1'; });
      };
      from.addEventListener('change', check);
      to.addEventListener('change', check);
      form.addEventListener('submit', (event) => { check(); if (!form.checkValidity()) { event.preventDefault(); to.reportValidity(); } });
    });

    // Typed document upload: show only the selected type's fields (all visible without JS; the server validates).
    document.querySelectorAll('form[data-hx-doc-form]').forEach((form) => {
      const select = form.querySelector('select[name="document_type_id"]');
      const field = (k) => form.querySelector(`[data-doc-field="${k}"]`);
      const fileInput = form.querySelector('input[type="file"]');
      const apply = () => {
        const o = select.selectedOptions[0];
        const d = o && o.value ? o.dataset : null;
        const show = (k, on, required) => {
          const el = field(k);
          if (!el) return;
          el.hidden = !on;
          el.querySelectorAll('input').forEach((i) => { i.disabled = !on; i.required = !!required && on; });
        };
        show('number', !d || d.number !== 'none', d && d.number === 'required');
        show('expiry', true, d && d.expiry === 'required');
        show('issued', !d || d.period !== '1', d && d.issued === 'required');
        show('valid_from', !d || d.period === '1', d && d.period === '1');
        show('country', !d || d.country === '1', false);
        const label = form.querySelector('[data-doc-label]');
        if (label && d) label.textContent = d.numberLabel + (d.number === 'required' ? ' *' : '');
        if (fileInput && d && d.accept) fileInput.accept = d.accept;
      };
      select.addEventListener('change', apply);
      apply();
    });

    // Client-side hint only; the server remains the authority for validation.
    document.querySelectorAll('.hx-input.is-invalid').forEach((input) => {
      input.setAttribute('aria-invalid', 'true');
      input.addEventListener('input', () => {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
      }, { once: true });
    });
  });
})();
