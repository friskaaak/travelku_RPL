document.addEventListener('DOMContentLoaded', () => {
  const today = new Date().toISOString().slice(0, 10);

  // Batas tanggal minimal (hanya kenyamanan UI; server tetap memvalidasi ulang).
  document.querySelectorAll('input[type="date"][data-min-today]').forEach(el => { el.min = today; });

  // Check-out selalu setelah check-in.
  const ci = document.getElementById('hotel_check_in');
  const co = document.getElementById('hotel_check_out');
  if (ci && co) {
    ci.addEventListener('change', () => {
      const d = new Date(ci.value);
      if (isNaN(d)) return;
      d.setDate(d.getDate() + 1);
      const min = d.toISOString().slice(0, 10);
      co.min = min;
      if (!co.value || co.value < min) co.value = min;
    });
  }

  // Asal dan tujuan tidak boleh sama.
  document.querySelectorAll('form[data-route-form]').forEach(form => {
    form.addEventListener('submit', e => {
      const from = form.querySelector('[name="origin"]');
      const to = form.querySelector('[name="destination"]');
      if (from && to && from.value === to.value) {
        e.preventDefault();
        to.setCustomValidity('Tujuan harus berbeda dari asal.');
        to.reportValidity();
        to.addEventListener('change', () => to.setCustomValidity(''), { once: true });
      }
    });
  });

  // Tutup notifikasi otomatis setelah 6 detik.
  document.querySelectorAll('.alert[data-autohide]').forEach(el => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 6000);
  });
});