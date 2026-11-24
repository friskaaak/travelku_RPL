<!DOCTYPE html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Beranda') | {{ config('app.name') }}</title>

    {{-- Pasang tema SEBELUM halaman digambar agar tidak berkedip --}}
    <script>
        (function () {
            var d = document.documentElement, t = 'light';
            try {
                var s = localStorage.getItem('tk-theme');
                if (s === 'light' || s === 'dark') t = s;
                else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) t = 'dark';
            } catch (e) {}
            d.setAttribute('data-theme', t);
            d.setAttribute('data-bs-theme', t);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

<a class="visually-hidden-focusable btn btn-primary position-absolute m-2" style="z-index:2000" href="#konten">Lewati ke konten</a>

<nav class="navbar navbar-expand-lg tk-nav" aria-label="Navigasi utama">
    <div class="container">
        <a class="tk-brand" href="{{ route('home') }}">
            <x-logo /> <span>{{ config('app.name') }}</span>
        </a>

        <div class="d-flex align-items-center gap-2 ms-auto d-lg-none">
            <x-theme-toggle />
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-lg-1">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('hotels.*') ? 'active' : '' }}" href="{{ route('hotels.index') }}"><i class="bi bi-building me-1" aria-hidden="true"></i>Hotel</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('flights.*') ? 'active' : '' }}" href="{{ route('flights.index') }}"><i class="bi bi-airplane me-1" aria-hidden="true"></i>Pesawat</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('trains.*') ? 'active' : '' }}" href="{{ route('trains.index') }}"><i class="bi bi-train-front me-1" aria-hidden="true"></i>Kereta</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('campaigns.*') ? 'active' : '' }}" href="{{ route('campaigns.index') }}"><i class="bi bi-tags me-1" aria-hidden="true"></i>Promo</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <x-theme-toggle class="d-none d-lg-inline-flex" />
                @auth
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1" aria-hidden="true"></i>{{ \Illuminate\Support\Str::limit(auth()->user()->name, 18) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('bookings.index') }}"><i class="bi bi-receipt me-2" aria-hidden="true"></i>Pesanan Saya</a></li>
                            @if (auth()->user()->isAdmin())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Panel Admin</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit" style="color:var(--danger)"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

@if (! empty($activeHold))
    <div class="tk-hold-bar">
        <div class="container py-2 d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <span>
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                Penahanan aktif: <strong>{{ $activeHold->details['title'] ?? 'Pesanan Anda' }}</strong>.
                Sisa waktu <strong class="tk-mono" data-countdown data-remaining="{{ max(0, $activeHold->expires_at->timestamp - now()->timestamp) }}">--:--</strong>
            </span>
            <a class="btn btn-sm btn-primary" href="{{ route('holds.show', $activeHold->hold_code) }}">Lihat penahanan</a>
        </div>
    </div>
@endif

<main id="konten" class="flex-grow-1">
    <div class="container mt-3"><x-flash /></div>
    @yield('content')
</main>

<footer class="tk-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="tk-brand mb-2" href="{{ route('home') }}"><x-logo :size="30" /> <span>{{ config('app.name') }}</span></a>
                <p class="small mb-0" style="max-width:42ch">Cari dan pesan hotel, pesawat, dan kereta dalam satu alur. Kamar dan kursi Anda ditahan selama checkout, dan bukti bayar tampil langsung setelah pembayaran terverifikasi.</p>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="tk-footer-title">Layanan</h2>
                <ul>
                    <li><a href="{{ route('hotels.index') }}">Hotel</a></li>
                    <li><a href="{{ route('flights.index') }}">Pesawat</a></li>
                    <li><a href="{{ route('trains.index') }}">Kereta</a></li>
                    <li><a href="{{ route('campaigns.index') }}">Promo</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h2 class="tk-footer-title">Akun</h2>
                <ul>
                    @auth
                        <li><a href="{{ route('bookings.index') }}">Pesanan Saya</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Masuk</a></li>
                        <li><a href="{{ route('register') }}">Daftar</a></li>
                    @endauth
                </ul>
            </div>
        </div>
        <hr class="my-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small">
            <div>&copy; {{ date('Y') }} {{ config('app.name') }}. Proyek demo lokal.</div>
            <div>Jadwal, harga, dan ketersediaan adalah <strong>data demonstrasi</strong>, bukan informasi maskapai, kereta, atau hotel yang sebenarnya.</div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/hold.js') }}"></script>
@stack('scripts')
</body>
</html>