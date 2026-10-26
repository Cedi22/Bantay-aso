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
      btn.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        btn.textContent = reveal ? hideLabel : showLabel;
        btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
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

    // Mobile sidebar
    const toggle = document.querySelector('[data-hx-sidebar-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.addEventListener('click', () => {
        const open = document.body.classList.toggle('hx-sidebar-open');
        toggle.setAttribute('aria-expanded', String(open));
      });
      document.addEventListener('click', (event) => {
        if (document.body.classList.contains('hx-sidebar-open')
          && !event.target.closest('.hx-sidebar')
          && !event.target.closest('[data-hx-sidebar-toggle]')) {
          document.body.classList.remove('hx-sidebar-open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          document.body.classList.remove('hx-sidebar-open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }

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
