@extends('layouts.app')
@section('title', 'Pesanan Saya')

@section('content')
@php
    $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];
    $bk = [
        'Pending' => ['Menunggu pembayaran', 'text-bg-warning'],
        'Confirmed' => ['Terkonfirmasi', 'text-bg-success'],
        'Cancelled' => ['Dibatalkan', 'text-bg-secondary'],
        'Expired' => ['Kedaluwarsa', 'text-bg-secondary'],
    ];
@endphp
<div class="container py-4">
    <h1 class="h4 mb-3">Pesanan saya</h1>

    <form method="GET" action="{{ route('bookings.index') }}" class="tk-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($bk as $k => [$l])<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="service" class="form-label">Layanan</label>
                <select id="service" name="service" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($svc as $k => $l)<option value="{{ $k }}" @selected(($filters['service'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">Saring</button>
                <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="tk-card">
        <div class="table-responsive">
            <table class="table tk-table align-middle mb-0">
                <thead><tr><th>Kode booking</th><th>Layanan</th><th>Tanggal</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    @php [$l, $cls] = $bk[$b->status] ?? [$b->status, 'text-bg-secondary']; @endphp
                    <tr>
                        <td><code>{{ $b->booking_code }}</code><div class="small">{{ $b->created_at->translatedFormat('d M Y, H:i') }}</div></td>
                        <td><div class="fw-semibold text-dark">{{ $b->title }}</div><div class="small">{{ $svc[$b->service_type] ?? '' }} · {{ $b->subtitle }}</div></td>
                        <td class="small text-nowrap">{{ $b->start_date?->translatedFormat('d M Y') }}</td>
                        <td>
                            <span class="badge {{ $cls }}">{{ $l }}</span>
                            @if ($b->refund_amount > 0)<div class="small mt-1">Refund {{ \App\Support\Money::idr($b->refund_amount) }}</div>@endif
                        </td>
                        <td class="text-end tk-mono">{{ \App\Support\Money::idr($b->total_amount) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('bookings.show', $b->booking_code) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                            @if ($b->receipt)<a href="{{ route('receipts.show', $b->receipt->receipt_number) }}" class="btn btn-sm btn-outline-secondary">Receipt</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">Tidak ada pesanan yang cocok. <a href="{{ route('home') }}">Mulai mencari</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $bookings->links() }}</div>
</div>
@endsection