@extends('layouts.admin')
@section('title', 'Hotel')

@section('admin')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Hotel</h1>
    <a href="{{ route('admin.hotels.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah hotel</a>
</div>
<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Hotel</th><th>Kota</th><th>Rating</th><th>Kamar</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @foreach ($hotels as $h)
        <tr>
            <td><div class="fw-semibold text-dark">{{ $h->name }}</div><div class="small">/hotel/{{ $h->slug }}</div></td>
            <td class="small">{{ $h->city }}</td>
            <td class="small tk-mono">{{ number_format($h->rating, 1, ',', '.') }}</td>
            <td class="small">{{ $h->rooms_count }}</td>
            <td><span class="badge {{ $h->is_published ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $h->is_published ? 'Tampil' : 'Disembunyikan' }}</span></td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.hotels.edit', $h) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                <form method="POST" action="{{ route('admin.hotels.toggle', $h) }}" class="d-inline"
                      data-confirm="{{ $h->is_published ? 'Sembunyikan hotel ini dari pencarian? Pesanan lama tidak terpengaruh.' : 'Tampilkan hotel ini di pencarian?' }}" data-confirm-label="{{ $h->is_published ? 'Sembunyikan' : 'Tampilkan' }}">
                    @csrf<button class="btn btn-sm btn-outline-secondary">{{ $h->is_published ? 'Sembunyikan' : 'Tampilkan' }}</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection