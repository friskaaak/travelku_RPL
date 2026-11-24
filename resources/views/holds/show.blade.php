@extends('layouts.app')
@section('title', 'Penahanan '.$hold->hold_code)

@section('content')
@php
    $d = $hold->details;
    $snap = $hold->pricing_snapshot ?? [];
    $isActive = $hold->status === \App\Models\Hold::ACTIVE;
    $statusMap = [
        'Active' => ['Aktif', '#ECFDF3', '#067647'],
        'Expired' => ['Kedaluwarsa', '#FEF3F2', '#B42318'],
        'Released' => ['Dilepas', '#F2F4F7', '#475467'],
        'Consumed' => ['Dipakai untuk pemesanan', '#EFF4FF', '#155EEF'],
    ];
    [$sLabel, $sBg, $sFg] = $statusMap[$hold->status];
    $remaining = $isActive ? max(0, $hold->expires_at->timestamp - now()->timestamp) : 0;
    $type = $hold->service_type;
@endphp

<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3">
        <a href="{{ route('home') }}">Beranda</a> / Penahanan {{ $hold->hold_code }}
    </nav>
    <x-stepper :step="1" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Penahanan {{ $type === 'hotel' ? 'kamar' : 'kursi' }}</h1>
            <div>Kode <code>{{ $hold->hold_code }}</code>
                <span class="badge ms-1" style="background:{{ $sBg }};color:{{ $sFg }}">{{ $sLabel }}</span>
            </div>
        </div>
    </div>

    @if ($isActive)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
            <div><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                {{ $type === 'hotel' ? 'Kamar' : 'Kursi' }} Anda ditahan. Selesaikan pemesanan sebelum waktu habis, jika tidak penahanan dilepas otomatis.</div>
            <div class="fs-4 fw-bold"><span data-countdown data-reload data-remaining="{{ $remaining }}">--:--</span></div>
        </div>
    @elseif ($hold->status === \App\Models\Hold::EXPIRED)
        <div class="alert alert-danger" role="alert"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>
            Waktu penahanan habis dan {{ $type === 'hotel' ? 'kamar' : 'kursi' }} sudah dilepas. Pilih ulang dari halaman produk.</div>
    @elseif ($hold->status === \App\Models\Hold::RELEASED)
        <div class="alert alert-secondary" role="status"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            Penahanan ini sudah dilepas{{ $hold->release_reason ? ' ('.$hold->release_reason.')' : '' }}.</div>
    @else
        <div class="alert alert-info" role="status">Penahanan ini sudah dipakai untuk pemesanan.</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">

            <section class="tk-card p-4 mb-3" aria-labelledby="sec-product">
                <h2 id="sec-product" class="h5">{{ $d['title'] }}</h2>
                <p class="mb-3">{{ $d['subtitle'] }}</p>

                @if ($type === 'hotel')
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $d['address'] }}</dd>
                        <dt class="col-sm-4">Check-in</dt><dd class="col-sm-8">{{ \Carbon\Carbon::parse($d['check_in'])->translatedFormat('l, d F Y') }}, mulai {{ $d['check_in_time'] }}</dd>
                        <dt class="col-sm-4">Check-out</dt><dd class="col-sm-8">{{ \Carbon\Carbon::parse($d['check_out'])->translatedFormat('l, d F Y') }}, sebelum {{ $d['check_out_time'] }}</dd>
                        <dt class="col-sm-4">Lama menginap</dt><dd class="col-sm-8">{{ $d['nights'] }} malam</dd>
                        <dt class="col-sm-4">Tamu</dt><dd class="col-sm-8">{{ $d['adults'] }} dewasa, {{ $d['children'] }} anak</dd>
                        <dt class="col-sm-4">Kamar</dt>
                        <dd class="col-sm-8">
                            @foreach ($d['rooms'] as $r)
                                <span class="badge text-bg-light border">Kamar {{ $r['room_number'] }}</span>
                            @endforeach
                            <div class="small">{{ $d['room_qty'] }} kamar tipe {{ $d['room_type'] }}</div>
                        </dd>
                    </dl>
                @else
                    @php $from = $d['origin']; $to = $d['destination']; @endphp
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-5">
                            <div class="fs-4 fw-bold text-dark">{{ \Carbon\Carbon::parse($d['departure_at'])->format('H:i') }}</div>
                            <div class="text-dark">{{ $from['iata'] ?? $from['code'] }}</div>
                            <div class="small">{{ $from['name'] }}</div>
                        </div>
                        <div class="col-2 text-center small">{{ \App\Support\Format::duration($d['duration_minutes']) }}<hr class="my-1"></div>
                        <div class="col-5 text-end">
                            <div class="fs-4 fw-bold text-dark">{{ \Carbon\Carbon::parse($d['arrival_at'])->format('H:i') }}</div>
                            <div class="text-dark">{{ $to['iata'] ?? $to['code'] }}</div>
                            <div class="small">{{ $to['name'] }}</div>
                        </div>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Berangkat</dt><dd class="col-sm-8">{{ \Carbon\Carbon::parse($d['departure_at'])->translatedFormat('l, d F Y, H:i') }} WIB</dd>
                        @if ($type === 'flight')
                            <dt class="col-sm-4">Bagasi</dt><dd class="col-sm-8">{{ $d['baggage_kg'] }} kg, kabin {{ $d['cabin_baggage_kg'] }} kg</dd>
                        @else
                            <dt class="col-sm-4">Kelas</dt><dd class="col-sm-8">{{ $d['class'] }}</dd>
                        @endif
                        <dt class="col-sm-4">Kursi</dt>
                        <dd class="col-sm-8">
                            @foreach ($d['seats'] as $s)
                                <span class="badge text-bg-light border">{{ $s['number'] }} · {{ $s['cabin'] ?? $s['class'] }}</span>
                            @endforeach
                        </dd>
                    </dl>
                @endif
                <p class="small mt-3 mb-0">Data jadwal dan harga adalah demonstrasi. Data tamu atau penumpang diisi pada langkah checkout.</p>
            </section>

            <section class="tk-card p-4" aria-labelledby="sec-cancel">
                <h2 id="sec-cancel" class="h5">Ketentuan pembatalan</h2>
                @if ($type === 'hotel')
                    @if ($d['free_cancellation'])
                        <span class="badge mb-2" style="background:#ECFDF3;color:#067647">Pembatalan gratis</span>
                    @endif
                    <p class="mb-0">{{ $d['cancellation_policy'] }}</p>
                @else
                    <p class="mb-0">{{ config('travel.cancellation.'.$type) }}</p>
                @endif
            </section>
        </div>

        <aside class="col-lg-5">
            <div class="tk-card p-4 position-sticky" style="top:80px">
                <h2 class="h5">Rincian harga</h2>
                <div class="d-flex justify-content-between py-1"><span>Harga awal</span><span>{{ \App\Support\Money::idr($hold->base_amount) }}</span></div>

                @forelse ($snap['applied'] ?? [] as $a)
                    <div class="d-flex justify-content-between py-1" style="color:#067647">
                        <span>{{ $a['name'] }}@if ($a['code']) (kode {{ $a['code'] }})@endif</span>
                        <span>-{{ \App\Support\Money::idr($a['amount']) }}</span>
                    </div>
                @empty
                    <div class="d-flex justify-content-between py-1"><span>Diskon</span><span>-</span></div>
                @endforelse

                <div class="d-flex justify-content-between py-1">
                    <span>Pajak ({{ $snap['tax_percent'] ?? 0 }}%)</span><span>{{ \App\Support\Money::idr($hold->tax_amount) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold text-dark">
                    <span>Total</span><span style="color:#155EEF">{{ \App\Support\Money::idr($hold->total_amount) }}</span>
                </div>
                <p class="small mt-2">Biaya metode pembayaran ditambahkan saat Anda memilih metode bayar pada langkah checkout. Pajak adalah angka demonstrasi.</p>

                @if ($isActive)
                    <a href="{{ route('checkout.show', $hold->hold_code) }}" class="btn btn-primary w-100 mb-2">Lanjut ke checkout</a>
                @endif

                @if ($isActive)
                    <form method="POST" action="{{ route('holds.release', $hold->hold_code) }}"
                          onsubmit="return confirm('Lepas penahanan ini? Kamar atau kursi akan kembali tersedia untuk pelanggan lain.')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Lepas penahanan</button>
                    </form>
                @else
                    <a href="{{ $productUrl }}" class="btn btn-outline-primary w-100">Pilih ulang</a>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection