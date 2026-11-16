<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') | {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg tk-nav sticky-top" aria-label="Navigasi utama">
    <div class="container">
        <a class="navbar-brand tk-brand" href="{{ route('home') }}">
            <i class="bi bi-airplane-engines-fill"></i> {{ config('app.name') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('hotels.*') ? 'active' : '' }}" href="{{ route('hotels.index') }}"><i class="bi bi-building me-1"></i>Hotel</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('flights.*') ? 'active' : '' }}" href="{{ route('flights.index') }}"><i class="bi bi-airplane me-1"></i>Pesawat</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('trains.*') ? 'active' : '' }}" href="{{ route('trains.index') }}"><i class="bi bi-train-front me-1"></i>Kereta</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}"><i class="bi bi-tags me-1"></i>Promo</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                @auth
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>{{ \Illuminate\Support\Str::limit(auth()->user()->name, 18) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('bookings.index') }}"><i class="bi bi-receipt me-2"></i>Pesanan Saya</a></li>
                            @if (auth()->user()->isAdmin())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Panel Admin</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
@if ($activeHold)
    <div class="bg-warning-subtle border-bottom">
        <div class="container py-2 d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <span>
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                Penahanan aktif: <strong>{{ $activeHold->details['title'] ?? 'Pesanan Anda' }}</strong>.
                Sisa waktu <strong data-countdown data-remaining="{{ max(0, $activeHold->expires_at->timestamp - now()->timestamp) }}">--:--</strong>
            </span>
            <a class="btn btn-sm btn-primary" href="{{ route('holds.show', $activeHold->hold_code) }}">Lihat penahanan</a>
        </div>
    </div>
@endif

<main class="flex-grow-1">
    @hasSection('flash')
        {{-- dilewati --}}
    @endif
    <div class="container mt-3"><x-flash /></div>
    @yield('content')
</main>

<footer class="tk-footer mt-5 py-4">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small">
        <div>&copy; {{ date('Y') }} {{ config('app.name') }}. Proyek demo lokal.</div>
        <div>Jadwal, harga, dan ketersediaan adalah <strong>data demonstrasi</strong>, bukan informasi maskapai, kereta, atau hotel yang sebenarnya.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/hold.js') }}"></script>
@stack('scripts')
</body>
</html>