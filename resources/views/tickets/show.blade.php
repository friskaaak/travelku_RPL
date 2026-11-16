@extends('layouts.app')
@section('title', 'Tiket '.$ticket->ticket_number)

@section('content')
@php
    $type = $ticket->ticket_type;
    $label = ['voucher' => 'Voucher Hotel', 'flight' => 'E-Ticket Pesawat', 'train' => 'E-Ticket Kereta'][$type] ?? 'Tiket';
    $t = $s['trip'] ?? [];
    $dt = fn ($v, $f = 'l, d F Y') => \Carbon\Carbon::parse($v)->translatedFormat($f);
    $tm = fn ($v) => \Carbon\Carbon::parse($v)->format('H:i');
    $h = $s['hotel'] ?? [];
@endphp

<div class="container py-4" style="max-width:880px">
    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <a href="{{ route('bookings.show', $booking->booking_code) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke pesanan</a>
        <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak</button>
        <a href="{{ route('tickets.pdf', $ticket->ticket_number) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Unduh PDF</a>
        @if ($delivery && in_array($delivery->status, ['sent', 'failed'], true))
            <form method="POST" action="{{ route('emails.resend', $delivery) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Kirim ulang email</button>
            </form>
        @else
            <button type="button" class="btn btn-outline-secondary" disabled title="Email masih diproses atau belum ada catatannya"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Kirim ulang email</button>
        @endif
    </div>

    @if ($booking->status !== 'Confirmed')
        <div class="alert alert-danger" role="alert"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Pesanan ini sekarang berstatus <strong>{{ $booking->status }}</strong>. Tiket ini <strong>tidak berlaku</strong>.</div>
    @endif

    @if ($s['is_simulated'])
        <div class="tk-demo-note mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Tiket <strong>demonstrasi</strong> dari pembayaran simulasi. Bukan tiket resmi dan tidak dapat dipakai untuk bepergian.</div>
    @endif

    <article class="tk-card tk-ticket mb-3">
        <div class="tk-ticket-band is-{{ $type }}">
            <strong class="fs-5">{{ $label }}</strong>
            <span>{{ $s['brand'] }} · {{ $ticket->ticket_number }}</span>
        </div>

        <div class="p-4">
            <div class="row g-4">
                <div class="col-md-8">
                    @if ($type === 'voucher')
                        <div class="small">Hotel</div>
                        <div class="fs-4 fw-bold text-dark">{{ $h['title'] }}</div>
                        <div class="mb-3">{{ $h['address'] }}</div>
                        <dl class="row tk-dl mb-0">
                            <dt class="col-sm-4">Check-in</dt><dd class="col-sm-8">{{ $dt($h['check_in']) }}, mulai {{ $h['check_in_time'] }}</dd>
                            <dt class="col-sm-4">Check-out</dt><dd class="col-sm-8">{{ $dt($h['check_out']) }}, sebelum {{ $h['check_out_time'] }}</dd>
                            <dt class="col-sm-4">Lama menginap</dt><dd class="col-sm-8">{{ $h['nights'] }} malam</dd>
                            <dt class="col-sm-4">Kamar</dt><dd class="col-sm-8">{{ $h['room_qty'] }} kamar {{ $h['room_type'] }}: @foreach ($h['rooms'] as $r)<span class="badge text-bg-light border">{{ $r }}</span> @endforeach</dd>
                            <dt class="col-sm-4">Tamu</dt>
                            <dd class="col-sm-8">
                                @foreach ($s['guests'] as $g){{ $g['name'] }}@if ($g['room']) (kamar {{ $g['room'] }})@endif @if (! $loop->last)<br>@endif @endforeach
                                <div class="small">{{ $h['adults'] }} dewasa, {{ $h['children'] }} anak</div>
                            </dd>
                            <dt class="col-sm-4">Pembatalan</dt><dd class="col-sm-8">{{ $h['cancellation_policy'] ?? '-' }}</dd>
                        </dl>
                    @else
                        <div class="small">Penumpang</div>
                        <div class="fs-4 fw-bold text-dark">{{ $s['passenger']['name'] }}</div>
                        <div class="mb-3 small">{{ ($s['passenger']['type'] ?? 'adult') === 'child' ? 'Anak' : 'Dewasa' }}@if (! empty($s['passenger']['id_number'])) · ID {{ $s['passenger']['id_number'] }}@endif</div>

                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-5">
                                <div class="fs-3 fw-bold text-dark">{{ $tm($t['departure_at']) }}</div>
                                <div class="text-dark">{{ $type === 'flight' ? $t['origin']['iata'] : $t['origin']['code'] }}</div>
                                <div class="small">{{ $t['origin']['name'] }}</div>
                            </div>
                            <div class="col-2 text-center small">{{ \App\Support\Format::duration($t['duration_minutes']) }}<hr class="my-1"></div>
                            <div class="col-5 text-end">
                                <div class="fs-3 fw-bold text-dark">{{ $tm($t['arrival_at']) }}</div>
                                <div class="text-dark">{{ $type === 'flight' ? $t['destination']['iata'] : $t['destination']['code'] }}</div>
                                <div class="small">{{ $t['destination']['name'] }}</div>
                            </div>
                        </div>

                        <dl class="row tk-dl mb-0">
                            <dt class="col-sm-4">{{ $type === 'flight' ? 'Penerbangan' : 'Kereta' }}</dt>
                            <dd class="col-sm-8">{{ $type === 'flight' ? $t['airline'].' '.$t['flight_number'] : $t['train_name'].' ('.$t['train_number'].')' }}</dd>
                            <dt class="col-sm-4">Tanggal berangkat</dt><dd class="col-sm-8">{{ $dt($t['departure_at']) }} WIB</dd>
                            <dt class="col-sm-4">Kursi</dt><dd class="col-sm-8"><strong>{{ $s['seat']['number'] }}</strong> · {{ $s['seat']['class'] }}</dd>
                            @if ($type === 'flight')
                                <dt class="col-sm-4">Bagasi</dt><dd class="col-sm-8">{{ $t['baggage_kg'] }} kg, kabin {{ $t['cabin_baggage_kg'] }} kg</dd>
                            @endif
                        </dl>
                    @endif
                </div>

                <div class="col-md-4 text-center">
                    @if ($qr)
                        <div class="tk-ticket-qr" role="img" aria-label="Kode QR verifikasi demo">{!! $qr !!}</div>
                    @else
                        <div class="small border rounded-3 p-2">QR tidak tersedia (paket QR belum terpasang). Gunakan kode di bawah.</div>
                    @endif
                    <div class="small mt-2">Kode verifikasi demo</div>
                    <div class="tk-code">{{ $ticket->verification_code }}</div>
                    <a class="small no-print" href="{{ $verifyUrl }}" target="_blank" rel="noopener">Buka halaman verifikasi</a>
                </div>
            </div>

            <hr>
            <div class="row small">
                <div class="col-md-3"><span class="text-muted">Kode booking</span><br><strong class="text-dark">{{ $s['booking_code'] }}</strong></div>
                <div class="col-md-3"><span class="text-muted">Tanggal pesan</span><br>{{ $dt($s['booked_at'], 'd F Y, H:i') }} WIB</div>
                <div class="col-md-3"><span class="text-muted">Status bayar</span><br>Paid (saat terbit)</div>
                <div class="col-md-3"><span class="text-muted">Diterbitkan</span><br>{{ $dt($s['issued_at'], 'd F Y, H:i') }} WIB</div>
            </div>
        </div>
    </article>

    <p class="small">Dokumen demonstrasi. QR berisi tautan verifikasi demo yang di komputer lokal hanya bisa dibuka dari komputer ini. Bukan sistem check-in resmi.</p>
</div>
@endsection