@extends('layouts.app')
@section('title', 'Cari Hotel')

@section('content')
<div class="container py-4">
<form method="GET" action="{{ route('hotels.index') }}" id="hotelForm">

    <div class="tk-card p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="hotel_destination" class="form-label">Destinasi</label>
                <select id="hotel_destination" name="destination" class="form-select">
                    <option value="">Semua kota</option>
                    @foreach ($cities as $c)<option value="{{ $c }}" @selected(($input['destination'] ?? '') === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6"><label for="hotel_check_in" class="form-label">Check-in</label><input type="date" id="hotel_check_in" name="check_in" class="form-control" value="{{ $input['check_in'] }}" data-min-today required></div>
            <div class="col-lg-2 col-md-3 col-6"><label for="hotel_check_out" class="form-label">Check-out</label><input type="date" id="hotel_check_out" name="check_out" class="form-control" value="{{ $input['check_out'] }}" data-min-today required></div>
            <div class="col-lg-1 col-4"><label for="hotel_adults" class="form-label">Dewasa</label><input type="number" id="hotel_adults" name="adults" class="form-control" min="1" max="10" value="{{ $input['adults'] }}" required></div>
            <div class="col-lg-1 col-4"><label for="hotel_children" class="form-label">Anak</label><input type="number" id="hotel_children" name="children" class="form-control" min="0" max="10" value="{{ $input['children'] }}"></div>
            <div class="col-lg-1 col-4"><label for="hotel_rooms" class="form-label">Kamar</label><input type="number" id="hotel_rooms" name="rooms" class="form-control" min="1" max="5" value="{{ $input['rooms'] }}" required></div>
            <div class="col-lg-2 col-12"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Cari</button></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Periksa kembali pencarian Anda:</strong><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="d-lg-none mb-3">
        <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="offcanvas" data-bs-target="#filterPanel" aria-controls="filterPanel"><i class="bi bi-sliders me-1" aria-hidden="true"></i>Filter</button>
    </div>

    <div class="row g-4">
        <aside class="col-lg-3" aria-label="Filter hotel">
            <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="filterPanel" aria-labelledby="filterTitle">
                <div class="offcanvas-header d-lg-none">
                    <h2 class="offcanvas-title h5" id="filterTitle">Filter</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filterPanel" aria-label="Tutup"></button>
                </div>
                <div class="offcanvas-body"><div class="tk-filter-box">
                    <h2 class="h6 mb-3 d-none d-lg-block">Filter</h2>
                    <fieldset class="mb-3">
                        <legend class="form-label d-block">Rating minimum</legend>
                        @foreach (['' => 'Semua', '3' => '3 ke atas', '4' => '4 ke atas', '4.5' => '4,5 ke atas'] as $v => $l)
                            <div class="form-check"><input class="form-check-input" type="radio" name="min_rating" id="rating_{{ $loop->index }}" value="{{ $v }}" @checked((string) ($input['min_rating'] ?? '') === (string) $v)><label class="form-check-label" for="rating_{{ $loop->index }}">{{ $l }}</label></div>
                        @endforeach
                    </fieldset>
                    <fieldset class="mb-3">
                        <legend class="form-label d-block">Fasilitas</legend>
                        @foreach ($amenityOptions as $a)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="amenities[]" id="amenity_{{ $loop->index }}" value="{{ $a }}" @checked(in_array($a, $input['amenities'] ?? [], true))><label class="form-check-label" for="amenity_{{ $loop->index }}">{{ $a }}</label></div>
                        @endforeach
                    </fieldset>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="discount_only" id="discount_only" value="1" @checked(! empty($input['discount_only']))><label class="form-check-label" for="discount_only">Hanya yang ada diskon</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="free_cancel" id="free_cancel" value="1" @checked(! empty($input['free_cancel']))><label class="form-check-label" for="free_cancel">Pembatalan gratis</label></div>
                    <button type="submit" class="btn btn-primary w-100 mb-2">Terapkan filter</button>
                    <a href="{{ route('hotels.index', array_merge(['destination' => $input['destination'] ?? null], $stay)) }}" class="btn btn-outline-secondary w-100">Atur ulang</a>
                </div></div>
            </div>
        </aside>

        <section class="col-lg-9" aria-live="polite">
            @if ($nights)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div><strong class="text-dark">{{ $results->count() }} hotel</strong> tersedia · {{ $nights }} malam, {{ $input['rooms'] }} kamar, {{ (int) $input['adults'] + (int) $input['children'] }} tamu</div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="sort" class="small mb-0">Urutkan</label>
                        <select id="sort" name="sort" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                            @foreach (['recommended' => 'Rekomendasi', 'price_asc' => 'Harga terendah', 'price_desc' => 'Harga tertinggi', 'rating' => 'Rating tertinggi'] as $v => $l)<option value="{{ $v }}" @selected($input['sort'] === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>

                @forelse ($results as $r)
                    @php $h = $r['hotel']; @endphp
                    <article class="tk-card tk-result overflow-hidden mb-3">
                        <div class="row g-0">
                            <div class="col-md-4"><x-hotel-image :image="$h->images[0] ?? null" height="100%" min-height="210px" /></div>
                            <div class="col-md-5 p-3">
                                <h2 class="h5 mb-1"><a href="{{ route('hotels.show', ['hotel' => $h->slug] + $stay) }}" class="text-decoration-none" style="color:var(--text-primary)">{{ $h->name }}</a></h2>
                                <div class="mb-1" aria-label="Bintang {{ $h->star_rating }}">@for ($i = 0; $i < $h->star_rating; $i++)<i class="bi bi-star-fill small" style="color:var(--accent-primary)" aria-hidden="true"></i>@endfor</div>
                                <div class="small mb-2"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $h->address }}</div>
                                <div class="mb-2"><span class="badge tk-badge-info tk-mono">{{ number_format($h->rating, 1, ',', '.') }}</span> <span class="small">({{ number_format($h->review_count, 0, ',', '.') }} ulasan)</span></div>
                                <div class="d-flex flex-wrap gap-1 mb-2">@foreach (array_slice($h->amenities ?? [], 0, 4) as $a)<span class="badge tk-badge-neutral">{{ $a }}</span>@endforeach</div>
                                <div class="small">
                                    @if ($h->free_cancellation)<span class="tk-text-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Pembatalan gratis</span>@endif
                                    @if ($r['available'] <= 3)<span class="ms-2 tk-text-warning">Sisa {{ $r['available'] }} kamar</span>@endif
                                </div>
                            </div>
                            <div class="col-md-3 p-3 d-flex flex-column justify-content-between border-start">
                                <x-price :original="$r['original']" :final="$r['final']" :label="$r['label']" :suffix="$nights.' malam, '.$input['rooms'].' kamar'" />
                                <div class="small text-md-end mt-2 tk-mono">mulai {{ \App\Support\Money::idr($r['unit']) }} / malam</div>
                                <a href="{{ route('hotels.show', ['hotel' => $h->slug] + $stay) }}" class="btn btn-primary mt-2">Lihat kamar</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="tk-empty"><i class="bi bi-search fs-2" style="color:var(--text-muted)" aria-hidden="true"></i><h2 class="h5 mt-2">Tidak ada hotel yang cocok</h2><p>Coba ubah tanggal, jumlah kamar, atau kurangi filter.</p><a href="{{ route('hotels.index', $stay) }}" class="btn btn-outline-primary">Atur ulang filter</a></div>
                @endforelse

                @if ($soldOut > 0)<p class="small mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ $soldOut }} hotel lain tidak memiliki cukup kamar kosong untuk seluruh tanggal ini.</p>@endif
            @endif
        </section>
    </div>
</form>
</div>
@endsection