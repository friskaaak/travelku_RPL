@extends('layouts.app')
@section('title', 'Pesanan Saya')

@section('content')
@php
    $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];
    $bk = [
        'Pending' => ['Menunggu pembayaran', '#FFFAEB', '#B54708'],
        'Confirmed' => ['Terkonfirmasi', '#ECFDF3', '#067647'],
        'Cancelled' => ['Dibatalkan', '#F2F4F7', '#475467'],
        'Expired' => ['Kedaluwarsa', '#F2F4F7', '#475467'],
    ];
@endphp
<div class="container py-4">
    <h1 class="h4 mb-3">Pesanan saya</h1>

    <div class="tk-card">
        <div class="table-responsive">
            <table class="table tk-table align-middle mb-0">
                <thead><tr><th>Kode booking</th><th>Layanan</th><th>Tanggal</th><th>Status pesanan</th><th>Status bayar</th><th class="text-end">Total</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    @php [$l, $bg, $fg] = $bk[$b->status] ?? [$b->status, '#F2F4F7', '#475467']; $pay = $b->payments->first(); @endphp
                    <tr>
                        <td><code>{{ $b->booking_code }}</code><div class="small">{{ $b->created_at->translatedFormat('d M Y, H:i') }}</div></td>
                        <td><div class="fw-semibold text-dark">{{ $b->title }}</div><div class="small">{{ $svc[$b->service_type] ?? '' }} · {{ $b->subtitle }}</div></td>
                        <td class="small text-nowrap">{{ $b->start_date?->translatedFormat('d M Y') }}</td>
                        <td><span class="badge" style="background:{{ $bg }};color:{{ $fg }}">{{ $l }}</span></td>
                        <td class="small">{{ $pay?->status ?? '-' }}</td>
                        <td class="text-end">{{ \App\Support\Money::idr($b->total_amount) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('bookings.show', $b->booking_code) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                            @if ($b->receipt)<a href="{{ route('receipts.show', $b->receipt->receipt_number) }}" class="btn btn-sm btn-outline-secondary">Receipt</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4">Belum ada pesanan. <a href="{{ route('home') }}">Mulai mencari</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $bookings->links() }}</div>
    <p class="small mt-3">Filter, pembatalan, dan linimasa status pesanan ditambahkan pada Fase 10.</p>
</div>
@endsection