@extends('layouts.app')

@section('title', 'Pesan Hotel, Pesawat, dan Kereta')

@section('content')
<section class="tk-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h1>Satu aplikasi untuk hotel, pesawat, dan kereta.</h1>
                <p class="fs-5 mb-0">Cari, pilih kamar atau kursi, bayar, dan terima e-tiket Anda. Semua dalam satu alur.</p>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="tk-card tk-search">
        <ul class="nav nav-pills mb-3" id="serviceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-hotel" type="button" role="tab" aria-selected="true"><i class="bi bi-building me-1"></i>Hotel</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-flight" type="button" role="tab" aria-selected="false"><i class="bi bi-airplane me-1"></i>Pesawat</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-train" type="button" role="tab" aria-selected="false"><i class="bi bi-train-front me-1"></i>Kereta</button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- HOTEL --}}
            <div class="tab-pane fade show active" id="tab-hotel" role="tabpanel">
                <form method="GET" action="{{ route('hotels.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label for="hotel_destination" class="form-label">Destinasi</label>
                        <select id="hotel_destination" name="destination" class="form-select" required>
                            @foreach ($hotelCities as $c)<option>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="hotel_check_in" class="form-label">Check-in</label>
                        <input type="date" id="hotel_check_in" name="check_in" class="form-control" data-min-today required>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label for="hotel_check_out" class="form-label">Check-out</label>
                        <input type="date" id="hotel_check_out" name="check_out" class="form-control" data-min-today required>
                    </div>
                    <div class="col-lg-1 col-4">
                        <label for="hotel_adults" class="form-label">Dewasa</label>
                        <input type="number" id="hotel_adults" name="adults" class="form-control" min="1" max="10" value="2" required>
                    </div>
                    <div class="col-lg-1 col-4">
                        <label for="hotel_children" class="form-label">Anak</label>
                        <input type="number" id="hotel_children" name="children" class="form-control" min="0" max="10" value="0">
                    </div>
                    <div class="col-lg-1 col-4">
                        <label for="hotel_rooms" class="form-label">Kamar</label>
                        <input type="number" id="hotel_rooms" name="rooms" class="form-control" min="1" max="5" value="1" required>
                    </div>
                    <div class="col-lg-2 col-12"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Cari Hotel</button></div>
                </form>
            </div>

            {{-- PESAWAT --}}
            <div class="tab-pane fade" id="tab-flight" role="tabpanel">
                <form method="GET" action="{{ route('flights.index') }}" class="row g-3 align-items-end" data-route-form>
                    <div class="col-lg-3 col-md-6">
                        <label for="flight_origin" class="form-label">Asal</label>
                        <select id="flight_origin" name="origin" class="form-select" required>
                            @foreach ($flightCities as $c)<option>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="flight_destination" class="form-label">Tujuan</label>
                        <select id="flight_destination" name="destination" class="form-select" required>
                            @foreach ($flightCities as $i => $c)<option @selected($i === 1)>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label for="flight_date" class="form-label">Tanggal berangkat</label>
                        <input type="date" id="flight_date" name="date" class="form-control" data-min-today required>
                    </div>
                    <div class="col-lg-1 col-md-4 col-6">
                        <label for="flight_pax" class="form-label">Penumpang</label>
                        <input type="number" id="flight_pax" name="passengers" class="form-control" min="1" max="9" value="1" required>
                    </div>
                    <div class="col-lg-1 col-md-4 col-12">
                        <label for="flight_class" class="form-label">Kelas</label>
                        <select id="flight_class" name="cabin" class="form-select"><option>Economy</option><option>Business</option></select>
                    </div>
                    <div class="col-lg-2 col-12"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Cari Pesawat</button></div>
                </form>
            </div>

            {{-- KERETA --}}
            <div class="tab-pane fade" id="tab-train" role="tabpanel">
                <form method="GET" action="{{ route('trains.index') }}" class="row g-3 align-items-end" data-route-form>
                    <div class="col-lg-3 col-md-6">
                        <label for="train_origin" class="form-label">Stasiun asal</label>
                        <select id="train_origin" name="origin" class="form-select" required>
                            @foreach ($trainCities as $c)<option>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="train_destination" class="form-label">Stasiun tujuan</label>
                        <select id="train_destination" name="destination" class="form-select" required>
                            @foreach ($trainCities as $i => $c)<option @selected($i === 3)>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-6">
                        <label for="train_date" class="form-label">Tanggal berangkat</label>
                        <input type="date" id="train_date" name="date" class="form-control" data-min-today required>
                    </div>
                    <div class="col-lg-1 col-md-6 col-6">
                        <label for="train_pax" class="form-label">Penumpang</label>
                        <input type="number" id="train_pax" name="passengers" class="form-control" min="1" max="4" value="1" required>
                    </div>
                    <div class="col-lg-2 col-12"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Cari Kereta</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- KAMPANYE AKTIF (dari database, waktu server) --}}
<section class="container mt-5" aria-labelledby="promo-title">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 id="promo-title" class="h4 mb-0">Promo yang sedang berlangsung</h2>
        <a href="{{ route('campaigns.index') }}" class="fw-semibold text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>

    @forelse ($campaigns as $c)
        @if ($loop->first)<div class="row g-3">@endif
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('campaigns.show', $c->slug) }}" class="tk-banner text-decoration-none" style="{{ $c->bannerStyle() }}">
                @if ($c->badge)<span class="tk-badge">{{ $c->badge }}</span>@endif
                <h3 class="h5">{{ $c->name }}</h3>
                <p class="mb-1 small">{{ \Illuminate\Support\Str::limit($c->description, 90) }}</p>
                <small class="opacity-75"><i class="bi bi-clock me-1"></i>Berakhir {{ $c->ends_at->translatedFormat('d F Y') }}</small>
            </a>
        </div>
        @if ($loop->last)</div>@endif
    @empty
        <div class="tk-empty">
            <i class="bi bi-tags fs-2 text-secondary"></i>
            <p class="mb-0 mt-2">Belum ada promo yang aktif saat ini. Kunjungi lagi nanti.</p>
        </div>
    @endforelse

    @if ($upcoming->isNotEmpty())
        <div class="mt-3 small">
            <strong>Segera hadir:</strong>
            @foreach ($upcoming as $u)
                {{ $u->name }} (mulai {{ $u->starts_at->translatedFormat('d F Y') }})@if(! $loop->last), @endif
            @endforeach
        </div>
    @endif
</section>

{{-- DESTINASI --}}
<section class="container mt-5" aria-labelledby="dest-title">
    <h2 id="dest-title" class="h4 mb-3">Destinasi populer</h2>
    <div class="row g-3">
        @foreach ($destinations as [$name, $tag, $icon, $bg])
            <div class="col-6 col-lg">
                <a class="tk-dest" style="background: {{ $bg }}" href="{{ route('hotels.index', ['destination' => $name]) }}">
                    <i class="bi {{ $icon }}"></i>
                    <h5>{{ $name }}</h5>
                    <small>{{ $tag }}</small>
                </a>
            </div>
        @endforeach
    </div>
</section>

{{-- KEUNGGULAN --}}
<section class="container mt-5" aria-labelledby="why-title">
    <h2 id="why-title" class="h4 mb-3">Kenapa memesan di sini?</h2>
    <div class="row g-3">
        @foreach ([
            ['bi-shield-check', 'Anti pesan ganda', 'Kamar dan kursi dikunci sementara saat Anda checkout, sehingga tidak bisa dipesan orang lain.'],
            ['bi-receipt', 'Bukti bayar langsung', 'Receipt tampil di layar begitu pembayaran terverifikasi, tanpa menunggu email.'],
            ['bi-tags', 'Diskon transparan', 'Harga awal, potongan, dan total akhir ditampilkan jelas sebelum Anda membayar.'],
            ['bi-ticket-perforated', 'E-tiket dan voucher', 'Tiket pesawat, kereta, dan voucher hotel tersedia untuk dilihat dan diunduh.'],
        ] as [$icon, $title, $text])
            <div class="col-md-6 col-lg-3">
                <div class="tk-card tk-feature p-3 h-100">
                    <i class="bi {{ $icon }}"></i>
                    <h3 class="h6 mt-2">{{ $title }}</h3>
                    <p class="small mb-0">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection