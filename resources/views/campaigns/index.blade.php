@extends('layouts.app')
@section('title', 'Promo dan Kampanye')

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-1">Promo dan kampanye</h1>
    <p class="mb-4">Kode promo hanya berlaku selama periode kampanye, menurut waktu server.</p>

    <h2 class="h5 mb-3">Sedang berlangsung</h2>
    @forelse ($active as $c)
        @if ($loop->first)<div class="row g-3 mb-4">@endif
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('campaigns.show', $c->slug) }}" class="tk-banner text-decoration-none h-100" style="{{ $c->bannerStyle() }}">
                @if ($c->badge)<span class="tk-badge">{{ $c->badge }}</span>@endif
                <h3 class="h5">{{ $c->name }}</h3>
                <p class="small mb-2">{{ \Illuminate\Support\Str::limit($c->description, 100) }}</p>
                @if ($c->promo_code)
                    <span class="small"><i class="bi bi-ticket-perforated me-1" aria-hidden="true"></i>Kode <strong>{{ $c->promo_code }}</strong></span>
                @endif
                <small class="opacity-75 mt-1"><i class="bi bi-clock me-1" aria-hidden="true"></i>Berakhir {{ $c->ends_at->translatedFormat('d F Y') }}</small>
            </a>
        </div>
        @if ($loop->last)</div>@endif
    @empty
        <div class="tk-empty mb-4">
            <i class="bi bi-tags fs-2 text-secondary" aria-hidden="true"></i>
            <p class="mb-0 mt-2">Belum ada promo yang aktif saat ini.</p>
        </div>
    @endforelse

    @if ($upcoming->isNotEmpty())
        <h2 class="h5 mb-3">Segera hadir</h2>
        <div class="row g-3">
            @foreach ($upcoming as $c)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('campaigns.show', $c->slug) }}" class="text-decoration-none">
                        <div class="tk-card p-3 h-100">
                            <span class="badge mb-2" style="background:#FFFAEB;color:#B54708">Belum dimulai</span>
                            <h3 class="h6 mb-1" style="color:#101828">{{ $c->name }}</h3>
                            <p class="small mb-2">{{ \Illuminate\Support\Str::limit($c->description, 90) }}</p>
                            <small>Mulai {{ $c->starts_at->translatedFormat('d F Y') }}</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection