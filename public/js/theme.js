(function () {
  var root = document.documentElement;
  var KEY = 'tk-theme';

  function current() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme); // agar komponen Bootstrap ikut berganti
    var label = theme === 'dark' ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.setAttribute('aria-label', label);
      b.setAttribute('title', label);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    apply(current());

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = current() === 'dark' ? 'light' : 'dark';
        root.classList.add('tk-theme-anim');
        apply(next);
        try { localStorage.setItem(KEY, next); } catch (e) { /* penyimpanan diblokir: tema tetap berganti untuk sesi ini */ }
        setTimeout(function () { root.classList.remove('tk-theme-anim'); }, 350);
      });
    });
  });

  // Ikuti pengaturan sistem hanya bila pengguna belum memilih sendiri.
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function (e) {
      var saved = null;
      try { saved = localStorage.getItem(KEY); } catch (err) {}
      if (!saved) apply(e.matches ? 'dark' : 'light');
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
  }
})();