document.addEventListener('DOMContentLoaded', () => {
  const els = document.querySelectorAll('[data-countdown]');
  if (!els.length) return;

  // Sisa waktu dikirim server (detik), bukan dihitung dari jam perangkat pengguna.
  const start = performance.now();
  const base = new Map();
  els.forEach(el => base.set(el, parseInt(el.dataset.remaining, 10) || 0));

  const fmt = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  let reloaded = false;

  const tick = () => {
    const elapsed = Math.floor((performance.now() - start) / 1000);
    els.forEach(el => {
      const left = Math.max(0, base.get(el) - elapsed);
      el.textContent = fmt(left);
      if (left === 0) {
        el.classList.add('text-danger');
        // Muat ulang agar server menampilkan status kedaluwarsa yang sebenarnya.
        if (el.dataset.reload !== undefined && !reloaded) {
          reloaded = true;
          setTimeout(() => location.reload(), 1500);
        }
      }
    });
  };

  tick();
  setInterval(tick, 1000);
});