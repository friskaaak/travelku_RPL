document.addEventListener('DOMContentLoaded', () => {

  // ---- 1. Dialog konfirmasi untuk form ber-atribut data-confirm ----
  const modalEl = document.getElementById('confirmModal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    const msg = modalEl.querySelector('[data-confirm-message]');
    const ok = modalEl.querySelector('[data-confirm-ok]');
    let pending = null;

    document.addEventListener('submit', e => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
      e.preventDefault();
      pending = form;
      msg.textContent = form.dataset.confirm;
      ok.textContent = form.dataset.confirmLabel || 'Ya, lanjutkan';
      modal.show();
    });

    ok.addEventListener('click', () => {
      if (!pending) return;
      const form = pending;
      pending = null;
      modal.hide();
      form.submit(); // submit() langsung tidak memicu event submit lagi
    });

    modalEl.addEventListener('hidden.bs.modal', () => { pending = null; });
  }

  // ---- 2. Form diskon: tampilkan kolom sesuai pilihan (server tetap memvalidasi ulang) ----
  const auto = document.getElementById('is_automatic');
  const type = document.getElementById('type');
  const flight = document.getElementById('svc_flight');
  const codeWrap = document.getElementById('code_wrap');
  const limitsWrap = document.getElementById('limits_wrap');
  const maxWrap = document.getElementById('maxdisc_wrap');
  const scopeWrap = document.getElementById('scope_wrap');

  const sync = () => {
    if (auto && codeWrap) codeWrap.hidden = auto.checked;
    if (auto && limitsWrap) limitsWrap.hidden = auto.checked;
    if (type && maxWrap) maxWrap.hidden = type.value !== 'percentage';
    if (flight && scopeWrap) scopeWrap.hidden = !flight.checked;
  };

  [auto, type, flight].forEach(el => el && el.addEventListener('change', sync));
  sync();
});