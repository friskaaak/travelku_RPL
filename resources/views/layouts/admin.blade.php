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