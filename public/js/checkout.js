document.addEventListener('DOMContentLoaded', () => {
  const total = document.getElementById('sumTotal');
  const fee = document.getElementById('sumFee');
  const form = document.getElementById('checkoutForm');
  if (!total || !fee || !form) return;

  const base = parseInt(total.dataset.base, 10) || 0;
  const idr = n => 'Rp' + Math.round(n).toLocaleString('id-ID');

  // Hanya tampilan. Biaya dan total sebenarnya dihitung ulang oleh server saat pesanan dibuat.
  const update = radio => {
    const f = radio ? parseInt(radio.dataset.fee, 10) || 0 : 0;
    fee.textContent = radio ? (f > 0 ? idr(f) : 'Gratis') : 'Pilih metode';
    total.textContent = idr(base + f);
  };

  document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', () => update(r)));
  update(document.querySelector('input[name="payment_method"]:checked'));

  // Cegah klik ganda. Server juga idempoten, ini hanya kenyamanan.
  form.addEventListener('submit', () => {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }
  });

  window.addEventListener('pageshow', e => { if (e.persisted) location.reload(); });
});