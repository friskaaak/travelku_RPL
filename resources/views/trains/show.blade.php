@extends('layouts.app')
@section('title', $schedule->train->name)

@section('content')
@php
    $dayShift = (int) abs($schedule->departure_at->copy()->startOfDay()->diffInDays($schedule->arrival_at->copy()->startOfDay()));
    $counts = $map['counts'];
@endphp
<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3">
        <a href="{{ route('home') }}">Beranda</a> /
        <a href="{{ route('trains.index', ['origin' => $schedule->origin->city, 'destination' => $schedule->destination->city, 'date' => $schedule->service_date->toDateString(), 'passengers' => $pax]) }}">Kereta</a> /
        {{ $schedule->train->number }}
    </nav>

    @if ($errors->any())
        <div class="alert alert-warning" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($closed)
        <div class="alert alert-danger" role="alert"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Kereta ini sudah berangkat. Pemilihan kursi dinonaktifkan.</div>
    @endif

    <div class="tk-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <div>
                <h1 class="h4 mb-1">{{ $schedule->origin->city }} ke {{ $schedule->destination->city }}</h1>
                <div>{{ $schedule->train->name }} · {{ $schedule->train->number }}
                    <span class="badge" style="background:#EFF4FF;color:#155EEF">{{ $schedule->class }}</span>
                    <span class="badge text-bg-light border">Data demo</span>
                </div>
            </div>
            <div class="text-md-end small">{{ $schedule->service_date->translatedFormat('l, d F Y') }}</div>
        </div>

        <x-trip-line :dep="$schedule->departure_at" :arr="$schedule->arrival_at" :from="$schedule->origin->code" :to="$schedule->destination->code" :from-name="$schedule->origin->name" :to-name="$schedule->destination->name" :duration="$schedule->duration_minutes" />
        <p class="small mt-3 mb-0">Jam dalam WIB. Tarif {{ \App\Support\Money::idr($schedule->base_fare) }} per penumpang.
            @unless ($schedule->discount_eligible)<br>Tiket ini tidak termasuk program promo.@endunless</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <section class="mb-4" aria-labelledby="sec-fare">
                <h2 id="sec-fare" class="h5">Harga untuk {{ $pax }} penumpang</h2>

                @if ($promoCheck)
                    <div class="alert {{ $promoCheck['ok'] ? 'alert-success' : 'alert-warning' }} py-2" role="status">
                        <i class="bi {{ $promoCheck['ok'] ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1" aria-hidden="true"></i>{{ $promoCheck['message'] }}
                    </div>
                @endif

                <div class="tk-card p-3">
                    @if ($bookable)
                        <span class="badge" style="background:#ECFDF3;color:#067647">Tersedia {{ $available }} kursi</span>
                    @else
                        <span class="badge" style="background:#FEF3F2;color:#B42318">Kursi tidak cukup ({{ $available }})</span>
                    @endif
                    <div class="mt-2"><x-price :original="$original" :final="$final" :label="$label" :suffix="$pax.' penumpang'" /></div>
                    @if ($promoApplied)
                        <div class="small text-md-end" style="color:#067647"><i class="bi bi-tag-fill me-1" aria-hidden="true"></i>Kode {{ $promo }} diterapkan</div>
                    @elseif ($promoCheck['ok'] ?? false)
                        @if ($promoError)<div class="small text-md-end" style="color:#B54708">{{ $promoError }}</div>
                        @elseif ($notice)<div class="small text-md-end">{{ $notice }}</div>@endif
                    @endif
                    @if ($terms)<details class="small mt-2 text-md-end"><summary>Syarat promo</summary>{{ $terms }}</details>@endif
                </div>
            </section>

            <section class="tk-card p-4" aria-labelledby="sec-seats">
                <h2 id="sec-seats" class="h5">Pilih kursi</h2>
                <p class="small">
                    Tersedia <strong>{{ $counts['available'] }}</strong>, ditahan <strong>{{ $counts['held'] }}</strong>,
                    terjual <strong>{{ $counts['confirmed'] }}</strong>@if ($counts['mine'] > 0), milik Anda <strong>{{ $counts['mine'] }}</strong>@endif.
                    Status kursi dibaca dari database server saat halaman dibuka.
                </p>
               <x-seat-map :rows="$map['rows']" :max="$pax" aisle-after="B" :interactive="! $closed" fields-id="seatFields" submit-id="holdSubmit" />
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="tk-card p-3 position-sticky" style="top:80px">
                <h2 class="h6">Kursi pilihan</h2>
                <p id="seatSummary" class="small" aria-live="polite">Belum ada kursi dipilih.</p>
                @auth
                    <form method="POST" action="{{ route('trains.hold', $schedule->id) }}">
                        @csrf
                        <input type="hidden" name="passengers" value="{{ $pax }}">
                        <input type="hidden" name="promo" value="{{ $promo }}">
                        <div id="seatFields"></div>
                        <button type="submit" id="holdSubmit" class="btn btn-primary w-100 mb-1" disabled>Tahan kursi</button>
                    </form>
                    <p class="small mb-3">Kursi ditahan {{ config('payment.hold_expiry_minutes') }} menit setelah Anda menekan tombol. Harga akhir dihitung server dari kursi yang dipilih.</p>
                @else
                    <a href="{{ route('login.intend', ['to' => request()->getRequestUri()]) }}" class="btn btn-outline-primary w-100 mb-3">Masuk untuk memilih kursi</a>
                @endauth
                <hr>
                <h2 class="h6">Ubah penumpang</h2>
                <form method="GET" action="{{ route('trains.show', $schedule->id) }}">
                    <div class="mb-2"><label for="passengers" class="form-label">Penumpang</label>
                        <input type="number" id="passengers" name="passengers" class="form-control" min="1" max="4" value="{{ $pax }}" required></div>
                    <div class="mb-2"><label for="promo" class="form-label">Kode promo</label>
                        <input type="text" id="promo" name="promo" class="form-control text-uppercase" maxlength="30" value="{{ $promo }}" autocomplete="off"></div>
                    <button type="submit" class="btn btn-outline-primary w-100">Perbarui</button>
                </form>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/seatmap.js') }}"></script>
@endpush