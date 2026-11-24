@extends('layouts.app')
@section('title', 'Pesanan '.$booking->booking_code)

@section('content')
@php
    $d = $booking->snapshot['details'] ?? [];
    $pricing = $booking->snapshot['pricing'] ?? [];
    $type = $booking->service_type;
    $bk = [
        'Pending' => ['Menunggu pembayaran', '#FFFAEB', '#B54708'],
        'Confirmed' => ['Terkonfirmasi', '#ECFDF3', '#067647'],
        'Cancelled' => ['Dibatalkan', '#F2F4F7', '#475467'],
        'Expired' => ['Kedaluwarsa', '#F2F4F7', '#475467'],
    ];
    [$sl, $sb, $sf] = $bk[$booking->status] ?? [$booking->status, '#F2F4F7', '#475467'];
    $latest = $booking->payments->first();
    $docName = $type === 'hotel' ? 'Voucher' : 'E-ticket';
@endphp

<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3"><a href="{{ route('home') }}">Beranda</a> / <a href="{{ route('bookings.index') }}">Pesanan saya</a> / {{ $booking->booking_code }}</nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Detail pesanan</h1>
            <div>Kode booking <code>{{ $booking->booking_code }}</code> · dipesan {{ $booking->created_at->translatedFormat('d F Y, H:i') }} WIB</div>
        </div>
        <span class="badge fs-6" style="background:{{ $sb }};color:{{ $sf }}">{{ $sl }}</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="tk-card p-4 mb-3">
                <x-order-summary :type="$type" :details="$d" :base="$booking->base_amount" :applied="$pricing['applied'] ?? []"
                                 :tax-percent="$pricing['tax_percent'] ?? 0" :tax="$booking->tax_amount" :fee="$booking->fee_amount" :total="$booking->total_amount" />
            </section>

            <section class="tk-card p-4">
                <h2 class="h5">{{ $type === 'hotel' ? 'Tamu' : 'Penumpang' }}</h2>
                <ul class="mb-0">
                    @foreach ($booking->passengers->sortBy('id') as $p)
                        <li>{{ $p->full_name }}@if ($p->seat_label) · kursi {{ $p->seat_label }}@endif @if ($p->room_label) · kamar {{ $p->room_label }}@endif</li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="col-lg-5">
            @if ($booking->status === 'Pending' && $latest)
                <div class="tk-card p-4 mb-3">
                    <h2 class="h6">Menunggu pembayaran</h2>
                    <p class="small">Selesaikan pembayaran sebelum {{ $latest->expires_at->translatedFormat('d M Y, H:i') }} WIB.</p>
                    <a href="{{ route('payments.show', $latest->payment_reference) }}" class="btn btn-primary w-100">Lanjutkan pembayaran</a>
                </div>
            @endif

            <div class="tk-card p-4 mb-3" id="tiket">
                <h2 class="h6">Dokumen</h2>
                @if ($booking->receipt)
                    <a href="{{ route('receipts.show', $booking->receipt->receipt_number) }}" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Lihat receipt</a>
                @else
                    <p class="small">Receipt tersedia setelah pembayaran berhasil.</p>
                @endif

                @forelse ($tickets as $t)
                    <div class="d-flex justify-content-between align-items-center border-top py-2 small">
                        <span>{{ $docName }}
                            @if ($t->ticket_type !== 'voucher'){{ $t->snapshot['passenger']['name'] ?? '' }} ({{ $t->snapshot['seat']['number'] ?? '-' }})@endif
                            <br><code>{{ $t->ticket_number }}</code></span>
                        <span class="text-nowrap">
                            <a href="{{ route('tickets.show', $t->ticket_number) }}" class="btn btn-sm btn-outline-primary">Buka</a>
                            <a href="{{ route('tickets.pdf', $t->ticket_number) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                        </span>
                    </div>
                @empty
                    <p class="small mb-0">{{ $docName }} tersedia setelah pembayaran berhasil.</p>
                @endforelse

                @if ($tickets->count() > 1)
                    <a href="{{ route('bookings.tickets.pdf', $booking->booking_code) }}" class="btn btn-outline-secondary w-100 mt-2">Unduh semua tiket (satu PDF)</a>
                @endif
            </div>

            @include('bookings._cancel', ['booking' => $booking, 'quote' => $quote])
            @include('bookings._timeline', ['timeline' => $timeline])
            @if ($booking->status === 'Confirmed')
                @include('documents._email-status', ['booking' => $booking])
            @endif
            <div class="tk-card p-4">
                <h2 class="h6">Riwayat tagihan</h2>
                @foreach ($booking->payments as $p)
                    <div class="small border-top py-2">
                        <code>{{ $p->invoice_number }}</code> · {{ config('payment.methods.'.$p->method_code.'.label', $p->method_code) }}<br>
                        {{ \App\Support\Money::idr($p->amount) }} · <strong>{{ $p->status }}</strong>@if ($p->is_simulated) <span class="badge text-bg-light border">Simulasi</span>@endif
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</div>
@endsection