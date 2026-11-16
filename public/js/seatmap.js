document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tk-seatmap').forEach(map => {
    const max = parseInt(map.dataset.max, 10) || 1;
    const summary = document.getElementById(map.dataset.summary);
    const notice = document.getElementById(map.dataset.notice);
    const fields = map.dataset.fields ? document.getElementById(map.dataset.fields) : null;
    const submit = map.dataset.submit ? document.getElementById(map.dataset.submit) : null;
    const selected = [];

    const render = () => {
      if (summary) {
        summary.textContent = selected.length
          ? 'Kursi dipilih (' + selected.length + ' dari ' + max + '): ' + selected.join(', ')
          : 'Belum ada kursi dipilih. Pilih ' + max + ' kursi.';
      }
      // Kolom tersembunyi untuk formulir. Server tetap memvalidasi ulang semuanya.
      if (fields) {
        fields.replaceChildren(...selected.map(label => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'seats[]';
          input.value = label;
          return input;
        }));
      }
      if (submit) submit.disabled = selected.length !== max;
    };

    // Pilihan di layar hanya penanda. Yang menentukan tetap server saat tombol ditekan.
    map.addEventListener('click', e => {
      const btn = e.target.closest('.tk-seat');
      if (!btn || btn.disabled) return;

      const label = btn.dataset.seat;
      const idx = selected.indexOf(label);
      if (notice) notice.textContent = '';

      if (idx >= 0) {
        selected.splice(idx, 1);
        btn.classList.remove('is-selected');
        btn.setAttribute('aria-pressed', 'false');
      } else {
        if (selected.length >= max) {
          if (notice) notice.textContent = 'Anda hanya dapat memilih ' + max + ' kursi sesuai jumlah penumpang. Batalkan satu kursi lebih dulu.';
          return;
        }
        selected.push(label);
        btn.classList.add('is-selected');
        btn.setAttribute('aria-pressed', 'true');
      }
      render();
    });

    render();
  });
});