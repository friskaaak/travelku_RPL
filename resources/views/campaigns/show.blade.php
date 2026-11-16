@extends('layouts.app')
@section('title', $campaign->name)

@section('content')
<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3">
        <a href="{{ route('home') }}">Beranda</a> / <a href="{{ route('campaigns.index') }}">Promo</a> / {{ $campaign->name }}
    </nav>

    <div class="tk-banner mb-4" style="{{ $campaign->bannerStyle() }};min-height:220px">
        @if ($campaign->badge)<span class="tk-badge">{{ $campaign->badge }}</span>@endif
        <h1 class="h2">{{ $campaign->name }}</h1>
        <p class="mb-0">{{ $campaign->description }}</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            {{-- STATUS dan KODE: kode hanya tampil bila kampanye AKTIF --}}
            @if ($status === 'active')
                <section class="tk-card p-4 mb-3" aria-labelledby="sec-code">
                    <span class="badge mb-2" style="background:#ECFDF3;color:#067647">Sedang berlangsung</span>
                    <h2 id="sec-code" class="h5">Kode promo</h2>
                    @if ($campaign->promo_code)
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <code id="promoCode" class="fs-5 px-3 py-2 border rounded bg-white" style="color:#101828">{{ $campaign->promo_code }}</code>
                            <button type="button" class="btn btn-outline-primary" id="copyBtn">Salin kode</button>
                        </div>
                    @endif
                    <p class="small mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i>Berlaku sampai {{ $campaign->ends_at->translatedFormat('d F Y, H:i') }} WIB.</p>
                </section>
            @elseif ($status === 'upcoming')
                <section class="tk-card p-4 mb-3">
                    <span class="badge mb-2" style="background:#FFFAEB;color:#B54708">Belum dimulai</span>
                    <p class="mb-0">Promo ini dimulai pada <strong>{{ $campaign->starts_at->translatedFormat('d F Y, H:i') }} WIB</strong>. Kode promo akan ditampilkan saat kampanye berjalan.</p>
                </section>
            @else
                <section class="tk-card p-4 mb-3">
                    <span class="badge mb-2" style="background:#FEF3F2;color:#B42318">Telah berakhir</span>
                    <p class="mb-2">Promo ini berakhir pada {{ $campaign->ends_at->translatedFormat('d F Y') }} dan kodenya tidak dapat digunakan lagi.</p>
                    <a href="{{ route('campaigns.index') }}" class="btn btn-outline-primary btn-sm">Lihat promo yang sedang berlangsung</a>
                </section>
            @endif

            @if ($discount)
                <section class="tk-card p-4 mb-3" aria-labelledby="sec-terms">
                    <h2 id="sec-terms" class="h5">Syarat dan ketentuan</h2>
                    <p class="mb-2"><span class="badge" style="background:#EFF4FF;color:#155EEF">{{ $discount->label }}</span></p>
                    <p class="mb-0">{{ $discount->terms }}</p>
                </section>
            @endif

            @if ($status === 'active')
                <section class="tk-card p-4 mb-3" aria-labelledby="sec-check">
                    <h2 id="sec-check" class="h5">Cek kode promo</h2>
                    <form id="checkForm" action="{{ route('promo.check') }}" method="POST" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="check_service" class="form-label">Layanan</label>
                            <select id="check_service" name="service" class="form-select">
                                @foreach (['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'] as $v => $l)
                                    @if (in_array($v, $campaign->services ?? [], true))<option value="{{ $v }}">{{ $l }}</option>@endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="check_code" class="form-label">Kode</label>
                            <input type="text" id="check_code" name="code" class="form-control text-uppercase" maxlength="30"
                                   value="{{ $campaign->promo_code }}" autocomplete="off" required>
                        </div>
                        <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Cek kode</button></div>
                    </form>
                    <div id="checkResult" class="small mt-2" role="status" aria-live="polite"></div>
                </section>

                <section aria-labelledby="sec-products">
                    <h2 id="sec-products" class="h5">Produk yang memenuhi syarat</h2>

                    @if (in_array('hotel', $campaign->services ?? [], true))
                        <h3 class="h6 mt-3">Hotel</h3>
                        @foreach ($hotels as $h)
                            @php $from = $h->rooms->where('status', 'active')->min('base_price'); @endphp
                            <div class="tk-card p-3 mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong class="text-dark">{{ $h->name }}</strong><br>
                                    <small>{{ $h->city }} · mulai {{ \App\Support\Money::idr($from) }} / malam</small>
                                </div>
                                <a class="btn btn-primary btn-sm" href="{{ route('hotels.show', ['hotel' => $h->slug, 'promo' => $campaign->promo_code]) }}">Pesan dengan kode</a>
                            </div>
                        @endforeach
                    @endif

                    @if (in_array('flight', $campaign->services ?? [], true) || in_array('train', $campaign->services ?? [], true))
                        <div class="tk-empty mt-3 small">
                            Daftar penerbangan dan kereta yang memenuhi syarat tampil di sini setelah katalog pesawat dan kereta dibuat (Fase 4B).
                        </div>
                    @endif
                </section>
            @endif
        </div>

        <aside class="col-lg-4">
            <div class="tk-card p-3">
                <h2 class="h6">Berlaku untuk</h2>
                <ul class="mb-3 ps-3">
                    @foreach ($campaign->services ?? [] as $s)
                        <li>{{ ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'][$s] ?? $s }}</li>
                    @endforeach
                </ul>
                <h2 class="h6">Periode</h2>
                <p class="small mb-0">{{ $campaign->starts_at->translatedFormat('d M Y') }} sampai {{ $campaign->ends_at->translatedFormat('d M Y') }}</p>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const copyBtn = document.getElementById('copyBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const code = document.getElementById('promoCode').textContent.trim();
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(code);
        } else {
          const t = document.createElement('textarea');
          t.value = code; document.body.appendChild(t); t.select();
          document.execCommand('copy'); t.remove();
        }
        copyBtn.textContent = 'Tersalin';
      } catch (e) { copyBtn.textContent = 'Salin manual'; }
      setTimeout(() => { copyBtn.textContent = 'Salin kode'; }, 2000);
    });
  }

  const form = document.getElementById('checkForm');
  const out = document.getElementById('checkResult');
  if (!form || !out) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    out.style.color = ''; out.textContent = 'Memeriksa kode...';
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', 'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ code: form.elements['code'].value, service: form.elements['service'].value })
      });
      if (res.status === 429) { out.style.color = '#B54708'; out.textContent = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.'; return; }
      const data = await res.json();
      if (res.status === 422) { out.style.color = '#B42318'; out.textContent = Object.values(data.errors).flat()[0]; return; }
      if (!data.valid) { out.style.color = '#B42318'; out.textContent = data.message; return; }
      out.style.color = '#067647';
      out.textContent = data.message + ' ' + data.label + '. Minimal transaksi ' + data.min_purchase + '. Berlaku sampai ' + data.ends_at + '.';
    } catch (err) {
      out.style.color = '#B42318'; out.textContent = 'Gagal memeriksa kode. Coba lagi.';
    }
  });
})();
</script>
@endpush