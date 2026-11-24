@extends('layouts.app')
@section('title', 'Cari Pesawat')

@section('content')
<div class="container py-4">
@if ($airports->isEmpty())
    <div class="tk-empty"><i class="bi bi-database-exclamation fs-2" style="color:var(--text-muted)" aria-hidden="true"></i><h1 class="h5 mt-2">Data penerbangan belum tersedia</h1><p class="mb-0">Jalankan <code>php artisan db:seed --class=FlightSeeder</code>, lalu muat ulang halaman.</p></div>
@else
<form method="GET" action="{{ route('flights.index') }}" data-route-form>

    <div class="tk-card p-3 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6"><label for="flight_origin" class="form-label">Asal</label>
                <select id="flight_origin" name="origin" class="form-select" required>@foreach ($airports as $a)<option value="{{ $a->iata }}" @selected($input['origin'] === $a->iata)>{{ $a->city }} ({{ $a->iata }})</option>@endforeach</select></div>
            <div class="col-lg-3 col-md-6"><label for="flight_destination" class="form-label">Tujuan</label>
                <select id="flight_destination" name="destination" class="form-select" required>@foreach ($airports as $a)<option value="{{ $a->iata }}" @selected($input['destination'] === $a->iata)>{{ $a->city }} ({{ $a->iata }})</option>@endforeach</select></div>
            <div class="col-lg-2 col-md-4 col-6"><label for="flight_date" class="form-label">Tanggal berangkat</label><input type="date" id="flight_date" name="date" class="form-control" value="{{ $input['date'] }}" data-min-today max="{{ $maxDate }}" required></div>
            <div class="col-lg-1 col-md-4 col-6"><label for="flight_pax" class="form-label">Penumpang</label><input type="number" id="flight_pax" name="passengers" class="form-control" min="1" max="9" value="{{ $input['passengers'] }}" required></div>
            <div class="col-lg-1 col-md-4 col-12"><label for="flight_class" class="form-label">Kelas</label><select id="flight_class" name="cabin" class="form-select">@foreach (['Economy', 'Business'] as $c)<option @selected($input['cabin'] === $c)>{{ $c }}</option>@endforeach</select></div>
            <div class="col-lg-2 col-12"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Cari</button></div>
        </div>
    </div>

    <div class="tk-demo-note mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Maskapai, jadwal, dan harga di sini adalah <strong>data demonstrasi</strong>, bukan informasi penerbangan sebenarnya. Semua jam dalam <strong>WIB</strong>.</div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Periksa kembali pencarian Anda:</strong><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="d-lg-none mb-3">
        <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="offcanvas" data-bs-target="#filterPanel" aria-controls="filterPanel"><i class="bi bi-sliders me-1" aria-hidden="true"></i>Filter dan kode promo</button>
    </div>

    <div class="row g-4">
        <aside class="col-lg-3" aria-label="Filter penerbangan">
            <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="filterPanel" aria-labelledby="filterTitle">
                <div class="offcanvas-header d-lg-none"><h2 class="offcanvas-title h5" id="filterTitle">Filter</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filterPanel" aria-label="Tutup"></button></div>
                <div class="offcanvas-body"><div class="tk-filter-box">
                    <h2 class="h6 mb-3 d-none d-lg-block">Filter</h2>
                    <fieldset class="mb-3"><legend class="form-label d-block">Maskapai</legend>
                        @foreach ($airlines as $al)<div class="form-check"><input class="form-check-input" type="checkbox" name="airlines[]" id="airline_{{ $al->code }}" value="{{ $al->code }}" @checked(in_array($al->code, $input['airlines'] ?? [], true))><label class="form-check-label" for="airline_{{ $al->code }}">{{ $al->name }}</label></div>@endforeach
                    </fieldset>
                    <fieldset class="mb-3"><legend class="form-label d-block">Waktu berangkat</legend>
                        @foreach (['pagi' => 'Pagi (05.00-10.59)', 'siang' => 'Siang (11.00-14.59)', 'sore' => 'Sore (15.00-18.59)', 'malam' => 'Malam (19.00-04.59)'] as $v => $l)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="times[]" id="time_{{ $v }}" value="{{ $v }}" @checked(in_array($v, $input['times'] ?? [], true))><label class="form-check-label" for="time_{{ $v }}">{{ $l }}</label></div>
                        @endforeach
                    </fieldset>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="direct_only" id="direct_only" value="1" @checked(! empty($input['direct_only']))><label class="form-check-label" for="direct_only">Hanya penerbangan langsung</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="discount_only" id="discount_only" value="1" @checked(! empty($input['discount_only']))><label class="form-check-label" for="discount_only">Hanya yang ada diskon</label></div>
                    <div class="mb-3"><label for="promo" class="form-label">Kode promo <span class="fw-normal">(opsional)</span></label><input type="text" id="promo" name="promo" class="form-control text-uppercase" maxlength="30" value="{{ $input['promo'] ?? '' }}" placeholder="LIBURAN15" autocomplete="off"></div>
                    <button type="submit" class="btn btn-primary w-100 mb-2">Terapkan filter</button>
                    <a href="{{ route('flights.index', \Illuminate\Support\Arr::only($input, ['origin', 'destination', 'date', 'passengers', 'cabin'])) }}" class="btn btn-outline-secondary w-100">Atur ulang</a>
                </div></div>
            </div>
        </aside>

        <section class="col-lg-9" aria-live="polite">
            @if ($searched)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div><strong class="text-dark">{{ $results->count() }} penerbangan</strong> · {{ $input['cabin'] }}, {{ $input['passengers'] }} penumpang</div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="sort" class="small mb-0">Urutkan</label>
                        <select id="sort" name="sort" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                            @foreach (['recommended' => 'Rekomendasi', 'price_asc' => 'Harga terendah', 'price_desc' => 'Harga tertinggi', 'departure' => 'Berangkat paling awal', 'duration' => 'Durasi tersingkat'] as $v => $l)<option value="{{ $v }}" @selected($input['sort'] === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>

                @forelse ($results as $r)
                    @php $s = $r['schedule']; $f = $s->flight; @endphp
                    <article class="tk-card tk-result p-3 mb-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-3">
                                <div class="fw-semibold text-dark">{{ $f->airline->name }}</div>
                                <div class="small tk-mono">{{ $f->flight_number }} · {{ $input['cabin'] }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <span class="badge {{ $f->is_direct ? 'tk-badge-success' : 'tk-badge-warning' }}">{{ $f->is_direct ? 'Langsung' : 'Transit' }}</span>
                                    <span class="badge tk-badge-neutral">Data demo</span>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <x-trip-line :dep="$s->departure_at" :arr="$s->arrival_at" :from="$f->origin->iata" :to="$f->destination->iata" :from-name="$f->origin->city" :to-name="$f->destination->city" :duration="$f->duration_minutes" :note="$f->is_direct ? 'Langsung' : 'Transit'" />
                                <div class="small mt-2">
                                    <i class="bi bi-luggage me-1" aria-hidden="true"></i>Bagasi {{ $f->baggage_kg }} kg, kabin {{ $f->cabin_baggage_kg }} kg
                                    @if ($r['available'] <= 5)<span class="ms-2 tk-text-warning">Sisa {{ $r['available'] }} kursi</span>@endif
                                </div>
                                @if ($f->connection_note)<div class="small mt-1 tk-text-warning">{{ $f->connection_note }}</div>@endif
                            </div>
                            <div class="col-lg-4">
                                <x-price :original="$r['original']" :final="$r['final']" :label="$r['label']" :suffix="$input['passengers'].' penumpang'" />
                                <a href="{{ route('flights.show', ['schedule' => $s->id, 'passengers' => $input['passengers'], 'cabin' => $input['cabin'], 'promo' => $input['promo'] ?? null]) }}" class="btn btn-primary w-100 mt-2">Lihat kursi</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="tk-empty"><i class="bi bi-airplane fs-2" style="color:var(--text-muted)" aria-hidden="true"></i><h2 class="h5 mt-2">Tidak ada penerbangan yang cocok</h2><p>Coba ubah tanggal, kelas, atau kurangi filter. Rute demo yang tersedia:</p><ul class="list-unstyled small mb-0">@foreach ($routes as $route)<li>{{ $route }}</li>@endforeach</ul></div>
                @endforelse

                @if ($soldOut > 0)<p class="small mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ $soldOut }} penerbangan lain tidak memiliki cukup kursi untuk {{ $input['passengers'] }} penumpang.</p>@endif
            @endif
        </section>
    </div>
</form>
@endif
</div>
@endsection