@extends('layouts.app')
@section('title', 'Pembayaran '.$payment->invoice_number)

@section('content')
@php
    use App\Models\Payment as P;
    $d = $booking->snapshot['details'] ?? [];
    $pricing = $booking->snapshot['pricing'] ?? [];
    $type = $booking->service_type;
    $statusMap = [
        P::PENDING => ['Menunggu pembayaran', '#FFFAEB', '#B54708'],
        P::PAID => ['Berhasil', '#ECFDF3', '#067647'],
        P::FAILED => ['Gagal', '#FEF3F2', '#B42318'],
        P::EXPIRED => ['Kedaluwarsa', '#F2F4F7', '#475467'],
        P::CANCELLED => ['Dibatalkan', '#F2F4F7', '#475467'],
        P::REFUNDED => ['Dana dikembalikan', '#EFF4FF', '#155EEF'],
        P::RECONCILIATION => ['Perlu rekonsiliasi', '#FFFAEB', '#B54708'],
    ];
    [$sLabel, $sBg, $sFg] = $statusMap[$payment->status] ?? [$payment->status, '#F2F4F7', '#475467'];
    $method = config('payment.methods.'.$payment->method_code);
    $flow = $method['flow'] ?? null;
    $pending = $payment->status === P::PENDING;
    $remaining = $pending ? max(0, $payment->expires_at->timestamp - now()->timestamp) : 0;
    $groups = $payment->instructions['groups'] ?? [];
@endphp

<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3"><a href="{{ route('home') }}">Beranda</a> / Pembayaran</nav>
    <x-stepper :step="3" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Pembayaran</h1>
            <div class="small">Booking <code>{{ $booking->booking_code }}</code> · Tagihan <code>{{ $payment->invoice_number }}</code></div>
        </div>
        <span class="badge fs-6" style="background:{{ $sBg }};color:{{ $sFg }}">{{ $sLabel }}</span>
    </div>

    @if ($payment->is_simulated)
        <div class="tk-demo-note mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ config('payment.demo_labels.banner') }}</div>
    @endif

    @if ($latest && $latest->id !== $payment->id && $booking->status === \App\Models\Booking::PENDING)
        <div class="alert alert-info" role="status">Ada tagihan yang lebih baru untuk pesanan ini. <a href="{{ route('payments.show', $latest->payment_reference) }}">Buka tagihan terbaru</a>.</div>
    @endif

    {{-- ===================== PANEL STATUS ===================== --}}
    @if ($payment->status === P::PAID)
        <div class="tk-card p-4 mb-4 text-center" style="border-color:#ABEFC6">
            <i class="bi bi-check-circle-fill" style="font-size:3rem;color:#067647" aria-hidden="true"></i>
            <h2 class="h3 mt-2" style="color:#067647">Pembayaran Berhasil</h2>
            <p class="mb-1">Pembayaran Anda sebesar <strong>{{ \App\Support\Money::idr($payment->amount) }}</strong> telah terverifikasi oleh server dan pesanan berstatus <strong>Confirmed</strong>.</p>
            <p class="small mb-3">Dibayar pada {{ $payment->paid_at?->translatedFormat('d F Y, H:i') }} WIB · Referensi <code>{{ $payment->payment_reference }}</code></p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="{{ route('bookings.index') }}" class="btn btn-outline-primary">Lihat pesanan</a>
                <button type="button" class="btn btn-outline-secondary" disabled title="Dibuat pada Fase 8">Cetak receipt</button>
                <button type="button" class="btn btn-outline-secondary" disabled title="Dibuat pada Fase 8">Unduh receipt PDF</button>
                <button type="button" class="btn btn-outline-secondary" disabled title="Dibuat pada Fase 8">Lihat {{ $type === 'hotel' ? 'voucher' : 'e-ticket' }}</button>
            </div>
            <p class="small mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Receipt dan {{ $type === 'hotel' ? 'voucher' : 'e-ticket' }} dibuat pada Fase 8, dan pengiriman email pada Fase 9. Belum ada email yang dikirim.</p>
        </div>

    @elseif ($payment->status === P::FAILED)
        <div class="alert alert-danger" role="alert">
            <h2 class="h5"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Pembayaran gagal</h2>
            <p class="mb-0">{{ $payment->failure_reason ?? 'Pembayaran tidak berhasil.' }}
                @if ($canChange) Anda masih bisa mencoba metode lain sebelum waktu pesanan habis. @else Pesanan ini sudah tidak dapat dilanjutkan. @endif</p>
        </div>

    @elseif ($payment->status === P::EXPIRED)
        <div class="alert alert-secondary" role="alert">
            <h2 class="h5"><i class="bi bi-hourglass-bottom me-1" aria-hidden="true"></i>Batas waktu pembayaran habis</h2>
            <p class="mb-2">Pesanan ditutup dan {{ $type === 'hotel' ? 'kamar' : 'kursi' }} dilepas. Silakan pilih ulang.</p>
            @if ($hold)<a href="{{ route('holds.show', $hold->hold_code) }}" class="btn btn-sm btn-outline-primary">Pilih ulang</a>@endif
        </div>

    @elseif ($payment->status === P::CANCELLED)
        <div class="alert alert-secondary" role="alert">
            <h2 class="h5">Tagihan dibatalkan</h2>
            <p class="mb-0">{{ $payment->failure_reason ?? 'Tagihan ini dibatalkan.' }} Jangan membayar tagihan ini.</p>
        </div>

    @elseif ($payment->status === P::RECONCILIATION)
        <div class="alert alert-warning" role="alert">
            <h2 class="h5"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Perlu rekonsiliasi</h2>
            <p class="mb-1">Pembayaran Anda terverifikasi, tetapi pesanan <strong>tidak dapat dikonfirmasi otomatis</strong>: {{ $payment->failure_reason }}.</p>
            <p class="mb-0">Jangan membayar lagi. Simpan referensi <code>{{ $payment->payment_reference }}</code>. Admin akan meninjau dan menentukan penyelesaiannya. Sistem tidak mengalokasikan ulang kamar atau kursi secara diam-diam.</p>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">

            {{-- ===================== INSTRUKSI (Pending) ===================== --}}
            @if ($pending)
                <section class="tk-card p-4 mb-3" aria-labelledby="sec-pay">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 id="sec-pay" class="h5 mb-0">Bayar dengan {{ $method['label'] ?? $payment->method_code }}</h2>
                        <div class="text-end">
                            <div class="small">Sisa waktu bayar</div>
                            <strong class="fs-4"><span data-countdown data-reload data-remaining="{{ $remaining }}">--:--</span></strong>
                        </div>
                    </div>
                    <p class="small">Bayar sebelum {{ $payment->expires_at->translatedFormat('d F Y, H:i') }} WIB. Total <strong>{{ \App\Support\Money::idr($payment->amount) }}</strong>.</p>

                    @if ($payment->is_simulated && $flow)
                        <div class="tk-demo-note mb-3">{{ config('payment.demo_labels.'.$flow) }}</div>
                    @endif

                    @if ($payment->provider === 'midtrans')
                        @if (! empty($payment->instructions['redirect_url']))
                            <a href="{{ $payment->instructions['redirect_url'] }}" class="btn btn-primary btn-lg w-100 mb-2" rel="noopener">Bayar dengan DANA</a>
                        @endif
                        <form method="POST" action="{{ route('payments.check', $payment->payment_reference) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">Saya sudah membayar, periksa status</button>
                        </form>
                        <p class="small">Status berubah otomatis setelah Midtrans mengonfirmasi pembayaran.@if (! config('payment.midtrans.is_production')) Mode sandbox: tidak ada uang sungguhan.@endif</p>
                    @endif

                    @if (in_array($flow, ['va', 'store', 'ewallet'], true) && $payment->payment_code)
                        <div class="mb-3">
                            <div class="small">{{ $flow === 'va' ? 'Nomor Virtual Account' : ($flow === 'store' ? 'Kode pembayaran' : 'Referensi dompet digital') }}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="tk-paycode" id="payCode">{{ $payment->payment_code }}</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="copyPay">Salin</button>
                            </div>
                        </div>
                    @endif

                    @if ($flow === 'qris')
                        <div class="text-center mb-3">
                            @if ($qr)
                                <div class="d-inline-block p-2 bg-white border rounded-3" role="img" aria-label="Kode QR simulasi, bukan QRIS asli">{!! $qr !!}</div>
                            @else
                                <div class="p-3 bg-white border rounded-3 small text-break">Isi QR simulasi:<br><code>{{ $payment->qr_payload }}</code><br>Pasang paket <code>simplesoftwareio/simple-qrcode</code> untuk melihat gambar QR.</div>
                            @endif
                            <div class="small mt-2">Nominal <strong>{{ \App\Support\Money::idr($payment->amount) }}</strong></div>
                        </div>
                    @endif

                    <div class="accordion" id="instrAcc">
                        @foreach ($groups as $g)
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#ins{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">{{ $g['title'] }}</button>
                                </h3>
                                <div id="ins{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#instrAcc">
                                    <div class="accordion-body"><ol class="mb-0">@foreach ($g['steps'] as $s)<li>{{ $s }}</li>@endforeach</ol></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ===================== SIMULASI (hanya demo + local) ===================== --}}
                @if ($simulation)
                    <section class="tk-card p-4 mb-3" style="border-color:#FEDF89" aria-labelledby="sec-sim">
                        <h2 id="sec-sim" class="h5"><i class="bi bi-wrench-adjustable me-1" aria-hidden="true"></i>Alat simulasi (mode demo)</h2>
                        <p class="small">Tombol ini hanya ada di mode demo lokal. Hasilnya diproses oleh server melalui jalur yang sama dengan pembayaran sungguhan. Tidak ada uang yang bergerak.</p>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ([
                                ['success', 'Simulasikan Pembayaran Berhasil', 'btn-success'],
                                ['fail', 'Simulasikan Pembayaran Gagal', 'btn-danger'],
                                ['pending', 'Simulasikan Tetap Menunggu', 'btn-outline-secondary'],
                                ['expire', 'Simulasikan Kedaluwarsa', 'btn-outline-secondary'],
                                ['late', 'Simulasikan Pembayaran Terlambat', 'btn-outline-warning'],
                            ] as [$action, $label, $class])
                                <form method="POST" action="{{ route('payments.simulate', $payment->payment_reference) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $action }}">
                                    <button type="submit" class="btn btn-sm {{ $class }}">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                        <p class="small mt-2 mb-0">"Terlambat" menutup tagihan lebih dulu, lalu mengirim pembayaran sukses. Hasilnya <strong>Reconciliation Required</strong>.</p>
                    </section>
                @endif
            @endif

            {{-- ===================== GANTI METODE / COBA LAGI ===================== --}}
            @if ($canChange)
                <section class="tk-card p-4 mb-3" aria-labelledby="sec-change">
                    <h2 id="sec-change" class="h5">{{ $payment->status === P::FAILED ? 'Coba metode lain' : 'Ganti metode pembayaran' }}</h2>
                    @if ($payment->status === P::PENDING)
                        <p class="small">Tagihan ini akan dibatalkan dan diganti tagihan baru. <strong>Jangan membayar tagihan lama</strong> setelah mengganti metode.</p>
                    @endif
                    <form method="POST" action="{{ route('payments.change', $payment->payment_reference) }}">
                        @csrf
                        @error('payment_method')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        @foreach ($categories as $key => $cat)
                            <fieldset class="mb-2">
                                <legend class="form-label mb-1"><i class="bi {{ $cat['icon'] }} me-1" aria-hidden="true"></i>{{ $cat['label'] }}</legend>
                                <div class="row g-2">
                                    @foreach ($cat['methods'] as $code => $m)
                                        <div class="col-md-6">
                                            <label class="tk-method">
                                                <input class="form-check-input" type="radio" name="payment_method" value="{{ $code }}" required @checked($payment->method_code === $code && $payment->status === P::PENDING)>
                                                <span class="fw-semibold text-dark">{{ $m['label'] }}</span>
                                                <span class="small ms-auto">{{ $m['fee'] > 0 ? '+'.\App\Support\Money::idr($m['fee']) : 'Gratis' }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                        <button type="submit" class="btn btn-primary mt-2">{{ $payment->status === P::FAILED ? 'Buat tagihan baru' : 'Ganti metode' }}</button>
                    </form>
                </section>
            @endif
        </div>

        {{-- ===================== RINGKASAN ===================== --}}
        <aside class="col-lg-5">
            <div class="tk-card p-4 mb-3">
                <x-order-summary :type="$type" :details="$d" :base="$booking->base_amount" :applied="$pricing['applied'] ?? []"
                                 :tax-percent="$pricing['tax_percent'] ?? 0" :tax="$booking->tax_amount" :fee="$booking->fee_amount" :total="$booking->total_amount" />
            </div>

            <div class="tk-card p-4 mb-3">
                <h2 class="h6">Rincian tagihan</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Kode booking</dt><dd class="col-7"><code>{{ $booking->booking_code }}</code></dd>
                    <dt class="col-5">Nomor tagihan</dt><dd class="col-7"><code>{{ $payment->invoice_number }}</code></dd>
                    <dt class="col-5">Referensi</dt><dd class="col-7"><code>{{ $payment->payment_reference }}</code></dd>
                    <dt class="col-5">Metode</dt><dd class="col-7">{{ $method['label'] ?? $payment->method_code }}</dd>
                    <dt class="col-5">Status tagihan</dt><dd class="col-7">{{ $sLabel }} ({{ $payment->status }})</dd>
                    <dt class="col-5">Status pesanan</dt><dd class="col-7">{{ $booking->status }}</dd>
                    <dt class="col-5">Batas bayar</dt><dd class="col-7">{{ $payment->expires_at->translatedFormat('d M Y, H:i') }} WIB</dd>
                </dl>
            </div>

            @if ($booking->status === \App\Models\Booking::PENDING && $hold && $hold->status === \App\Models\Hold::ACTIVE)
                <form method="POST" action="{{ route('payments.cancel', $payment->payment_reference) }}"
                      onsubmit="return confirm('Batalkan pesanan ini? Kamar atau kursi akan dilepas dan tagihan ditutup.')">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100">Batalkan pesanan</button>
                </form>
                <p class="small mt-2"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Memilih produk lain akan otomatis membatalkan pesanan yang menunggu pembayaran ini.</p>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  const copy = document.getElementById('copyPay');
  if (copy) {
    copy.addEventListener('click', async () => {
      const text = document.getElementById('payCode').textContent.trim();
      try {
        if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); }
        else { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
        copy.textContent = 'Tersalin';
      } catch (e) { copy.textContent = 'Salin manual'; }
      setTimeout(() => { copy.textContent = 'Salin'; }, 2000);
    });
  }

  @if ($pending)
  // Periksa status ke server tiap 8 detik. Bila berubah (misalnya webhook masuk), muat ulang halaman.
  const url = @json(route('payments.status', $payment->payment_reference));
  setInterval(async () => {
    try {
      const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();
      if (d.status !== 'Pending') location.reload();
    } catch (e) {}
  }, 8000);
  @endif
})();
</script>
@endpush