@extends('layouts.admin')
@section('title', 'Panel Admin')

@section('admin')
<h1 class="h4 mb-1">Panel admin</h1>
<p class="small mb-3">Masuk sebagai <strong>{{ auth()->user()->name }}</strong>. Statistik pemesanan dan pendapatan ditambahkan pada Fase 10.</p>

<div class="row g-3 mb-4">
    @foreach ([
        ['Diskon aktif', $stats['discounts_active'].' dari '.$stats['discounts_total'], route('admin.discounts.index', ['status' => 'active'])],
        ['Kampanye berjalan', $stats['campaigns_active'], route('admin.campaigns.index', ['status' => 'active'])],
        ['Kampanye terjadwal', $stats['campaigns_upcoming'], route('admin.campaigns.index', ['status' => 'upcoming'])],
        ['Kampanye draf', $stats['campaigns_draft'], route('admin.campaigns.index', ['status' => 'draft'])],
    ] as [$label, $value, $url])
        <div class="col-6 col-lg-3">
            <a href="{{ $url }}" class="text-decoration-none">
                <div class="tk-card p-3 h-100">
                    <div class="small">{{ $label }}</div>
                    <div class="fs-3 fw-bold" style="color:#101828">{{ $value }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="tk-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Aktivitas admin terbaru</h2>
        <a href="{{ route('admin.audit') }}" class="small">Lihat semua</a>
    </div>
    @forelse ($logs as $log)
        <div class="small border-top py-2">
            <strong class="text-dark">{{ $log->action }}</strong>
            oleh {{ $log->user?->name ?? 'sistem' }}
            <span class="text-muted">· {{ $log->created_at->translatedFormat('d M Y H:i') }}</span>
        </div>
    @empty
        <p class="small mb-0">Belum ada aktivitas tercatat.</p>
    @endforelse
</div>
@endsection