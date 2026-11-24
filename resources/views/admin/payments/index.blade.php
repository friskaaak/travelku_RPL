@extends('layouts.admin')
@section('title', 'Pembayaran')

@section('admin')
@php
    $all = ['Pending', 'Paid', 'Failed', 'Expired', 'Cancelled', 'Refunded', 'Reconciliation Required'];
    $cls = ['Pending' => 'text-bg-warning', 'Paid' => 'text-bg-success', 'Failed' => 'text-bg-danger', 'Reconciliation Required' => 'text-bg-warning', 'Refunded' => 'text-bg-info'];
@endphp
<h1 class="h4 mb-3">Pembayaran</h1>

<form method="GET" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label for="q" class="form-label">Referensi atau nomor tagihan</label><input id="q" name="q" class="form-control" maxlength="60" value="{{ $f['q'] ?? '' }}"></div>
        <div class="col-md-4"><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select"><option value="">Semua</option>@foreach ($all as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="col-md-3 d-flex gap-1"><button class="btn btn-outline-primary flex-grow-1">Saring</button><a href="{{ route('admin.payments.index') }}" class="btn btn-link">Reset</a></div>
    </div>
</form>

<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Tagihan</th><th>Booking</th><th>Metode</th><th>Status</th><th class="text-end">Nominal</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @forelse ($payments as $p)
        <tr>
            <td><code>{{ $p->invoice_number }}</code><div class="small">{{ $p->created_at->translatedFormat('d M Y, H:i') }}</div></td>
            <td class="small">{{ $p->booking?->booking_code }}<br>{{ $p->booking?->contact_email }}</td>
            <td class="small">{{ $p->method_code }}@if ($p->is_simulated) <span class="badge text-bg-light border">Simulasi</span>@endif</td>
            <td><span class="badge {{ $cls[$p->status] ?? 'text-bg-secondary' }}">{{ $p->status }}</span></td>
            <td class="text-end tk-mono">{{ \App\Support\Money::idr($p->amount) }}</td>
            <td class="text-end"><a href="{{ route('admin.payments.show', $p) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">Tidak ada tagihan.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection