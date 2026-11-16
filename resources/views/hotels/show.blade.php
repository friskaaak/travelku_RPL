@extends('layouts.app')
@section('title', $hotel->name)

@section('content')
<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3">
        <a href="{{ route('home') }}">Beranda</a> /
        <a href="{{ route('hotels.index', $stay) }}">Hotel</a> / {{ $hotel->name }}
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $hotel->name }}</h1>
            <div class="mb-1" aria-label="Bintang {{ $hotel->star_rating }}">
                @for ($i = 0; $i < $hotel->star_rating; $i++)<i class="bi bi-star-fill text-warning" aria-hidden="true"></i>@endfor
            </div>
            <div class="small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $hotel->address }}</div>
        </div>
        <div class="text-md-end">
            <span class="badge fs-6" style="background:#EFF4FF;color:#155EEF">{{ number_format($hotel->rating, 1, ',', '.') }}</span>
            <div class="small">{{ number_format($hotel->review_count, 0, ',', '.') }} ulasan</div>
        </div>
    </div>

    {{-- GALERI --}}
    <div class="row g-2 mb-4">
        <div class="col-lg-8"><x-hotel-image :image="$images[0] ?? null" height="316px" /></div>
        <div class="col-lg-4 d-flex flex-column gap-2">
            @foreach (array_slice($images, 1, 3) as $img)
                <x-hotel-image :image="$img" height="100px" />
            @endforeach
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Periksa tanggal dan jumlah tamu:</strong>
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">

            <section class="tk-card p-4 mb-3" aria-labelledby="sec-desc">
                <h2 id="sec-desc" class="h5">Tentang hotel</h2>
                <p class="mb-0">{{ $hotel->description }}</p>
            </section>

            <section class="tk-card p-4 mb-3" aria-labelledby="sec-amen">
                <h2 id="sec-amen" class="h5">Fasilitas</h2>
                <div class="row g-2">
                    @foreach ($hotel->amenities ?? [] as $a)
                        <div class="col-6 col-md-4"><i class="bi bi-check2-circle me-1" style="color:#067647" aria-hidden="true"></i>{{ $a }}</div>
                    @endforeach
                </div>
            </section>

            <section class="mb-3" aria-labelledby="sec-rooms">
                <h2 id="sec-rooms" class="h5">Tipe kamar</h2>

                @if ($valid)
                    <p class="small">
                        {{ $checkIn->translatedFormat('d M Y') }} sampai {{ $checkOut->translatedFormat('d M Y') }}
                        ({{ $nights }} malam), {{ $roomQty }} kamar. Ketersediaan dicek untuk seluruh malam.
                    </p>
                    @if ($promoCheck)
                        <div class="alert {{ $promoCheck['ok'] ? 'alert-success' : 'alert-warning' }} py-2" role="status">
                            <i class="bi {{ $promoCheck['ok'] ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1" aria-hidden="true"></i>{{ $promoCheck['message'] }}
                        </div>
                    @endif

                    @foreach ($types as $t)
                        <div class="tk-card p-3 mb-3">
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <h3 class="h6 mb-1">Kamar {{ $t['type'] }}</h3>
                                    <div class="small mb-2">
                                        <i class="bi bi-people me-1" aria-hidden="true"></i>Maks. {{ $t['capacity'] }} tamu
                                        <span class="mx-1">·</span><i class="bi bi-moon me-1" aria-hidden="true"></i>{{ $t['bed'] }}
                                    </div>

                                    @if ($t['available'] > 0 && $t['bookable'])
                                        <span class="badge" style="background:#ECFDF3;color:#067647">Tersedia {{ $t['available'] }} dari {{ $t['total'] }} kamar</span>
                                    @elseif ($t['available'] > 0)
                                        <span class="badge" style="background:#FFFAEB;color:#B54708">Hanya {{ $t['available'] }} kamar, Anda memilih {{ $roomQty }}</span>
                                    @elseif (! $t['fits'])
                                        <span class="badge" style="background:#FFFAEB;color:#B54708">Kapasitas kurang untuk {{ $perRoom }} tamu per kamar</span>
                                    @else
                                        <span class="badge" style="background:#FEF3F2;color:#B42318">Tidak tersedia pada tanggal ini</span>
                                    @endif

                                    @if ($t['numbers'])
                                        <div class="small mt-2">Nomor kamar kosong:
                                            @foreach ($t['numbers'] as $n)<span class="badge text-bg-light border">{{ $n }}</span> @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-5">
                                    <x-price :original="$t['original']" :final="$t['final']" :label="$t['label']" :suffix="$nights.' malam, '.$roomQty.' kamar'" />
                                    @if ($t['promo_applied'])
                                        <div class="small text-md-end" style="color:#067647"><i class="bi bi-tag-fill me-1" aria-hidden="true"></i>Kode {{ $promoCode }} diterapkan</div>
                                    @elseif ($promoCheck['ok'] ?? false)
                                        @if ($t['promo_error'])
                                            <div class="small text-md-end" style="color:#B54708">{{ $t['promo_error'] }}</div>
                                        @elseif ($t['notice'])
                                            <div class="small text-md-end">{{ $t['notice'] }}</div>
                                        @endif
                                    @endif
                                    <div class="small text-md-end">{{ \App\Support\Money::idr($t['unit']) }} / malam</div>
                                    @if ($t['terms'])
                                        <details class="small mt-2 text-md-end"><summary>Syarat promo</summary>{{ $t['terms'] }}</details>
                                    @endif
                                    @if (! $t['bookable'])
                                        <button type="button" class="btn btn-primary w-100 mt-2" disabled>Tidak tersedia</button>
                                    @elseif (auth()->check())
                                        <form method="POST" action="{{ route('hotels.hold', $hotel->slug) }}">
                                            @csrf
                                            <input type="hidden" name="room_type" value="{{ $t['type'] }}">
                                            <input type="hidden" name="check_in" value="{{ $input['check_in'] }}">
                                            <input type="hidden" name="check_out" value="{{ $input['check_out'] }}">
                                            <input type="hidden" name="adults" value="{{ $input['adults'] }}">
                                            <input type="hidden" name="children" value="{{ $input['children'] }}">
                                            <input type="hidden" name="rooms" value="{{ $input['rooms'] }}">
                                            <input type="hidden" name="promo" value="{{ $promoCode }}">
                                            <button type="submit" class="btn btn-primary w-100 mt-2">Pilih kamar</button>
                                        </form>
                                    @else
                                        <a href="{{ route('login.intend', ['to' => request()->getRequestUri()]) }}" class="btn btn-outline-primary w-100 mt-2">Masuk untuk memesan</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <p class="small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Kamar ditahan {{ config('payment.hold_expiry_minutes') }} menit setelah Anda menekan "Pilih kamar". Kamar yang ditahan tidak bisa dipilih pelanggan lain.</p>
                @else
                    <div class="tk-empty">Isi tanggal dan jumlah tamu yang valid untuk melihat ketersediaan kamar.</div>
                @endif
            </section>

            <section class="tk-card p-4 mb-3" aria-labelledby="sec-policy">
                <h2 id="sec-policy" class="h5">Kebijakan</h2>
                <div class="row g-3">
                    <div class="col-md-6"><strong class="text-dark">Check-in</strong><br>mulai {{ substr($hotel->check_in_time, 0, 5) }}</div>
                    <div class="col-md-6"><strong class="text-dark">Check-out</strong><br>sebelum {{ substr($hotel->check_out_time, 0, 5) }}</div>
                    <div class="col-12">
                        <strong class="text-dark">Pembatalan</strong>
                        @if ($hotel->free_cancellation)
                            <span class="badge ms-1" style="background:#ECFDF3;color:#067647">Pembatalan gratis</span>
                        @endif
                        <p class="mb-0 mt-1">{{ $hotel->cancellation_policy }}</p>
                    </div>
                </div>
            </section>

            <section class="tk-card p-4" aria-labelledby="sec-loc">
                <h2 id="sec-loc" class="h5">Lokasi</h2>
                <p class="mb-1"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $hotel->address }}</p>
                <p class="small mb-2">Koordinat perkiraan area: {{ $hotel->latitude }}, {{ $hotel->longitude }}. Hotel ini fiktif untuk demonstrasi.</p>
                <a href="https://www.google.com/maps?q={{ $hotel->latitude }},{{ $hotel->longitude }}" target="_blank" rel="noopener noreferrer" class="small">Buka area di peta <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
            </section>
        </div>

        {{-- UBAH TANGGAL --}}
        <aside class="col-lg-4">
            <div class="tk-card p-3 position-sticky" style="top:80px">
                <h2 class="h6 mb-3">Ubah tanggal dan tamu</h2>
                <form method="GET" action="{{ route('hotels.show', $hotel->slug) }}">
                    <div class="mb-2">
                        <label for="hotel_check_in" class="form-label">Check-in</label>
                        <input type="date" id="hotel_check_in" name="check_in" class="form-control" value="{{ $input['check_in'] }}" data-min-today required>
                    </div>
                    <div class="mb-2">
                        <label for="hotel_check_out" class="form-label">Check-out</label>
                        <input type="date" id="hotel_check_out" name="check_out" class="form-control" value="{{ $input['check_out'] }}" data-min-today required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label for="hotel_adults" class="form-label">Dewasa</label><input type="number" id="hotel_adults" name="adults" class="form-control" min="1" max="10" value="{{ $input['adults'] }}" required></div>
                        <div class="col-4"><label for="hotel_children" class="form-label">Anak</label><input type="number" id="hotel_children" name="children" class="form-control" min="0" max="10" value="{{ $input['children'] }}"></div>
                        <div class="col-4"><label for="hotel_rooms" class="form-label">Kamar</label><input type="number" id="hotel_rooms" name="rooms" class="form-control" min="1" max="5" value="{{ $input['rooms'] }}" required></div>
                    </div>
                    <div class="mb-3">
                        <label for="promo" class="form-label">Kode promo <span class="fw-normal">(opsional)</span></label>
                        <input type="text" id="promo" name="promo" class="form-control text-uppercase" maxlength="30"
                               value="{{ $promoCode }}" placeholder="LIBURAN15" autocomplete="off">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Perbarui ketersediaan</button>
                </form>
            </div>
        </aside>
    </div>
</div>
@endsection