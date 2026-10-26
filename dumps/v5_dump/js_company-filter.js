/* S3curity H3LIX — restricts company-dependent choices (options and checkboxes carrying data-company) to the
   company selected in the same form, and clears selections of another company when the company changes (QA-04).
   Convenience only: the server re-validates every id against the company. */
(() => {
  'use strict';
  document.querySelectorAll('form[data-hx-company-filter]').forEach((form) => {
    const companySelect = form.querySelector('select[name="company_id"]');

    const apply = () => {
      const company = companySelect ? companySelect.value : (form.dataset.company || '');
      form.querySelectorAll('select:not([name="company_id"])').forEach((select) => {
        Array.from(select.options).forEach((opt) => {
          if (!opt.dataset.company) return; // blank / company-neutral options (e.g. "None", "Rest day")
          const visible = company !== '' && opt.dataset.company === company;
          opt.hidden = !visible;
          opt.disabled = !visible;
          if (!visible && opt.selected) select.value = '';
        });
      });
      form.querySelectorAll('input[type="checkbox"][data-company]').forEach((box) => {
        const visible = company !== '' && box.dataset.company === company;
        box.disabled = !visible;
        if (!visible) box.checked = false;
        const label = box.closest('label');
        if (label) label.hidden = !visible;
      });
    };

    if (companySelect) companySelect.addEventListener('change', apply);
    apply();
  });
})();
