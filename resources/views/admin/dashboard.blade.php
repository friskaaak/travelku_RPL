@extends('layouts.admin')
@section('title', 'Panel Admin')

@section('admin')
@php $idr = fn ($v) => \App\Support\Money::idr($v); $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta']; @endphp
<h1 class="h4 mb-1">Panel admin</h1>
<p class="small mb-3">Masuk sebagai <strong>{{ auth()->user()->name }}</strong>.</p>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="tk-card p-3 h-100"><div class="small">Pendapatan bersih</div><div class="fs-4 fw-bold tk-mono text-dark">{{ $idr($money['net']) }}</div><div class="small">Dibayar {{ $idr($money['gross']) }}, refund {{ $idr($money['refunded']) }}</div></div></div>
    <div class="col-md-4"><div class="tk-card p-3 h-100"><div class="small">Pesanan terkonfirmasi</div><div class="fs-4 fw-bold text-dark">{{ $byStatus['Confirmed'] ?? 0 }}</div><div class="small">Menunggu bayar {{ $byStatus['Pending'] ?? 0 }} · Dibatalkan {{ $byStatus['Cancelled'] ?? 0 }} · Kedaluwarsa {{ $byStatus['Expired'] ?? 0 }}</div></div></div>
    <div class="col-md-4"><div class="tk-card p-3 h-100"><div class="small">Terkonfirmasi per layanan</div>
        @foreach ($svc as $k => $l)<div class="small d-flex justify-content-between"><span>{{ $l }}</span><span class="tk-mono">{{ $byService[$k]->c ?? 0 }} · {{ $idr($byService[$k]->s ?? 0) }}</span></div>@endforeach</div></div>
</div>
<p class="small">Pendapatan dihitung dari tagihan terverifikasi (Paid dan Refunded) dikurangi refund. Tagihan Reconciliation Required tidak dihitung.</p>

<h2 class="h6">Perlu perhatian</h2>
<div class="row g-3 mb-4">
    @foreach ([
        ['Rekonsiliasi', $attention['reconciliation'], route('admin.payments.index', ['status' => 'Reconciliation Required'])],
        ['Refund menunggu', $attention['refund_pending'], route('admin.bookings.index', ['refund' => 'pending'])],
        ['Hold aktif', $attention['holds_active'], route('admin.holds.index')],
        ['Email gagal', $attention['emails_failed'], route('admin.emails.index', ['status' => 'failed'])],
    ] as [$label, $value, $url])
        <div class="col-6 col-lg-3"><a href="{{ $url }}" class="text-decoration-none"><div class="tk-card p-3 h-100"><div class="small">{{ $label }}</div><div class="fs-3 fw-bold text-dark">{{ $value }}</div></div></a></div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Diskon aktif', $stats['discounts_active'].' dari '.$stats['discounts_total'], route('admin.discounts.index', ['status' => 'active'])],
        ['Kampanye berjalan', $stats['campaigns_active'], route('admin.campaigns.index', ['status' => 'active'])],
        ['Kampanye terjadwal', $stats['campaigns_upcoming'], route('admin.campaigns.index', ['status' => 'upcoming'])],
        ['Kampanye draf', $stats['campaigns_draft'], route('admin.campaigns.index', ['status' => 'draft'])],
    ] as [$label, $value, $url])
        <div class="col-6 col-lg-3"><a href="{{ $url }}" class="text-decoration-none"><div class="tk-card p-3 h-100"><div class="small">{{ $label }}</div><div class="fs-4 fw-bold text-dark">{{ $value }}</div></div></a></div>
    @endforeach
</div>

<div class="tk-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Aktivitas admin terbaru</h2><a href="{{ route('admin.audit') }}" class="small">Lihat semua</a></div>
    @forelse ($logs as $log)
        <div class="small border-top py-2"><strong class="text-dark">{{ $log->action }}</strong> oleh {{ $log->user?->name ?? 'sistem' }} · {{ $log->created_at->translatedFormat('d M Y H:i') }}</div>
    @empty
        <p class="small mb-0">Belum ada aktivitas tercatat.</p>
    @endforelse
</div>
@endsection