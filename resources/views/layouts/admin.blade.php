@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row g-4">
        <aside class="col-lg-2" aria-label="Menu admin">
            <div class="tk-card tk-sidenav p-2">
                <nav class="nav flex-column">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ringkasan</a>
                    <a class="nav-link {{ request()->routeIs('admin.discounts.*') ? 'active' : '' }}" href="{{ route('admin.discounts.index') }}"><i class="bi bi-percent me-1" aria-hidden="true"></i>Diskon</a>
                    <a class="nav-link {{ request()->routeIs('admin.campaigns.*') ? 'active' : '' }}" href="{{ route('admin.campaigns.index') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>Kampanye</a>
                    <a class="nav-link {{ request()->routeIs('admin.audit') ? 'active' : '' }}" href="{{ route('admin.audit') }}"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Log audit</a>
                    <a class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}"><i class="bi bi-card-list me-1" aria-hidden="true"></i>Pesanan</a>
                    <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Pembayaran</a>
                    <a class="nav-link {{ request()->routeIs('admin.holds.*') ? 'active' : '' }}" href="{{ route('admin.holds.index') }}"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Hold</a>
                    <a class="nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}"><i class="bi bi-ticket-perforated me-1" aria-hidden="true"></i>Tiket</a>
                    <a class="nav-link {{ request()->routeIs('admin.emails.*') ? 'active' : '' }}" href="{{ route('admin.emails.index') }}"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Log email</a></div>
                    <a class="nav-link {{ request()->routeIs('admin.hotels.*', 'admin.rooms.*') ? 'active' : '' }}" href="{{ route('admin.hotels.index') }}"><i class="bi bi-building me-1" aria-hidden="true"></i>Hotel</a>
                    <a class="nav-link {{ request()->is('admin/jadwal/pesawat*') ? 'active' : '' }}" href="{{ route('admin.schedules.index', 'pesawat') }}"><i class="bi bi-airplane me-1" aria-hidden="true"></i>Jadwal pesawat</a>
                    <a class="nav-link {{ request()->is('admin/jadwal/kereta*') ? 'active' : '' }}" href="{{ route('admin.schedules.index', 'kereta') }}"><i class="bi bi-train-front me-1" aria-hidden="true"></i>Jadwal kereta</a>
                </nav>
            </div>
        </aside>
        <div class="col-lg-10">
            @yield('admin')
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="confirmTitle">Konfirmasi tindakan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body"><p class="mb-0" data-confirm-message></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" data-confirm-ok>Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}"></script>
@endpush