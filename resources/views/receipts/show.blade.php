@extends('layouts.app')
@section('title', 'Bukti Pembayaran '.$receipt->receipt_number)

@section('content')
@php
    $pay = $s['payment'];
    $pr = $s['pricing'];
    $type = $s['service_type'];
    $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'][$type] ?? $type;
    $when = fn ($v) => \Carbon\Carbon::parse($v)->translatedFormat('d F Y, H:i').' WIB';
    $docName = $type === 'hotel' ? 'voucher' : 'e-ticket';
    $ticketUrl = $tickets->count() === 1
        ? route('tickets.show', $tickets->first()->ticket_number)
        : route('bookings.show', $booking->booking_code).'#tiket';
    $mailLabel = ['queued' => 'Dalam antrean', 'sending' => 'Sedang dikirim', 'sent' => 'Diterima server email', 'failed' => 'Gagal terkirim'];
@endphp

<div class="container py-4" style="max-width:920px">

    <div class="tk-card p-4 mb-3 text-center no-print" style="border-color:#ABEFC6">
        <i class="bi bi-check-circle-fill" style="font-size:3rem;color:#067647" aria-hidden="true"></i>
        <h1 class="h3 mt-2 mb-1" style="color:#067647">Pembayaran Berhasil</h1>
        <p class="mb-0">Pembayaran sebesar <strong>{{ \App\Support\Money::idr($pay['amount']) }}</strong> telah terverifikasi oleh server dan pesanan Anda <strong>terkonfirmasi</strong>.</p>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="btn btn-outline-primary"><i class="bi bi-card-list me-1" aria-hidden="true"></i>Lihat pesanan</a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak receipt</button>
        <a href="{{ route('receipts.pdf', $receipt->receipt_number) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Unduh receipt PDF</a>
        <a href="{{ $ticketUrl }}" class="btn btn-primary"><i class="bi bi-ticket-perforated me-1" aria-hidden="true"></i>Lihat {{ $docName }}</a>
    </div>

    @if ($pay['is_simulated'])
        <div class="tk-demo-note mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Pembayaran ini <strong>simulasi (mode demo)</strong>. Tidak ada uang sungguhan yang diproses dan dokumen ini bukan bukti transaksi keuangan yang sah.</div>
    @endif

    <article class="tk-card p-4 mb-3 tk-receipt">
        <div class="tk-doc-head d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="fs-4 fw-bold text-dark"><i class="bi bi-airplane-engines-fill" style="color:#155EEF" aria-hidden="true"></i> {{ $s['brand'] }}</div>
                <div class="small">Bukti pembayaran</div>
            </div>
            <div class="text-md-end">
                <div class="small">Nomor receipt</div>
                <div class="fw-bold text-dark">{{ $receipt->receipt_number }}</div>
                <div class="small">Diterbitkan {{ $when($s['issued_at']) }}</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <dl class="row tk-dl mb-0">
                    <dt class="col-5">Kode booking</dt><dd class="col-7"><code>{{ $s['booking']['code'] }}</code></dd>
                    <dt class="col-5">Referensi bayar</dt><dd class="col-7"><code>{{ $pay['reference'] }}</code></dd>
                    <dt class="col-5">Nomor tagihan</dt><dd class="col-7"><code>{{ $pay['invoice'] }}</code></dd>
                    <dt class="col-5">Layanan</dt><dd class="col-7">{{ $svc }}</dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="row tk-dl mb-0">
                    <dt class="col-5">Pelanggan</dt><dd class="col-7">{{ $s['customer']['name'] }}<br><span class="small">{{ $s['customer']['email'] }}</span></dd>
                    <dt class="col-5">Metode bayar</dt><dd class="col-7">{{ $pay['method_label'] }}</dd>
                    <dt class="col-5">Status bayar</dt><dd class="col-7"><span class="badge" style="background:#ECFDF3;color:#067647">Berhasil (Paid)</span></dd>
                    <dt class="col-5">Waktu transaksi</dt><dd class="col-7">{{ $when($pay['paid_at']) }}</dd>
                    <dt class="col-5">Status pesanan</dt><dd class="col-7">{{ $s['booking']['status'] }} (saat diterbitkan)</dd>
                </dl>
            </div>
        </div>

        @if ($booking->status !== $s['booking']['status'])
            <div class="alert alert-warning py-2">Status pesanan saat ini: <strong>{{ $booking->status }}</strong>. Receipt di bawah tetap menunjukkan nilai saat transaksi.</div>
        @endif

        <h2 class="h6">Rincian layanan</h2>
        <div class="border rounded-3 p-3 mb-3">
            <div class="fw-semibold text-dark">{{ $s['title'] }}</div>
            <div class="small mb-2">{{ $s['subtitle'] }}</div>
            @include('documents._product', ['type' => $type, 'd' => $s['details']])
        </div>

        <h2 class="h6">{{ $type === 'hotel' ? 'Tamu' : 'Penumpang' }}</h2>
        <div class="table-responsive mb-3">
            <table class="table table-sm mb-0">
                <thead><tr><th>Nama</th><th>{{ $type === 'hotel' ? 'Kamar' : 'Kursi' }}</th>@if ($type !== 'hotel')<th>Jenis</th>@endif</tr></thead>
                <tbody>
                    @foreach ($s['passengers'] as $p)
                        <tr>
                            <td>{{ $p['name'] }}</td>
                            <td>{{ $type === 'hotel' ? ($p['room'] ?? '-') : ($p['seat'] ?? '-') }}</td>
                            @if ($type !== 'hotel')<td>{{ $p['type'] === 'child' ? 'Anak' : 'Dewasa' }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 class="h6">Rincian pembayaran</h2>
        <div class="border rounded-3 p-3">
            <div class="d-flex justify-content-between py-1"><span>Subtotal (harga awal)</span><span>{{ \App\Support\Money::idr($pr['base']) }}</span></div>
            @forelse ($pr['discounts'] as $a)
                <div class="d-flex justify-content-between py-1" style="color:#067647">
                    <span>Diskon: {{ $a['name'] }}@if (! empty($a['code'])) (kode {{ $a['code'] }})@endif</span>
                    <span>-{{ \App\Support\Money::idr($a['amount']) }}</span>
                </div>
            @empty
                <div class="d-flex justify-content-between py-1"><span>Diskon</span><span>-</span></div>
            @endforelse
            <div class="d-flex justify-content-between py-1"><span>Pajak ({{ $pr['tax_percent'] }}%)</span><span>{{ \App\Support\Money::idr($pr['tax']) }}</span></div>
            <div class="d-flex justify-content-between py-1"><span>Biaya metode pembayaran</span><span>{{ $pr['fee'] > 0 ? \App\Support\Money::idr($pr['fee']) : 'Gratis' }}</span></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between fs-5 fw-bold text-dark"><span>Total dibayar</span><span style="color:#155EEF">{{ \App\Support\Money::idr($pay['amount']) }}</span></div>
        </div>

        <p class="small mt-3 mb-0">Layanan, jadwal, harga, pajak, dan biaya pada dokumen ini adalah data demonstrasi. Dokumen ini disalin saat transaksi dan tidak berubah walau harga atau diskon di sistem diubah kemudian.</p>
    </article>

    @include('documents._email-status', ['booking' => $booking])
</div>
@endsection