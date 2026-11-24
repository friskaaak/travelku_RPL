@extends('layouts.admin')
@section('title', 'Pesanan')

@section('admin')
@php
    $cls = ['Pending' => 'text-bg-warning', 'Confirmed' => 'text-bg-success', 'Cancelled' => 'text-bg-secondary', 'Expired' => 'text-bg-secondary'];
    $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];
@endphp
<h1 class="h4 mb-3">Pesanan</h1>

<form method="GET" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label for="q" class="form-label">Kode, nama, atau email</label><input id="q" name="q" class="form-control" maxlength="60" value="{{ $f['q'] ?? '' }}"></div>
        <div class="col-md-2"><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select"><option value="">Semua</option>@foreach (array_keys($cls) as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="col-md-2"><label for="service" class="form-label">Layanan</label>
            <select id="service" name="service" class="form-select"><option value="">Semua</option>@foreach ($svc as $k => $l)<option value="{{ $k }}" @selected(($f['service'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="refund" id="refund" value="pending" @checked(! empty($f['refund']))><label class="form-check-label" for="refund">Refund menunggu</label></div></div>
        <div class="col-md-2 d-flex gap-1"><button class="btn btn-outline-primary flex-grow-1">Saring</button><a href="{{ route('admin.bookings.index') }}" class="btn btn-link">Reset</a></div>
    </div>
</form>

<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Booking</th><th>Pelanggan</th><th>Layanan</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @forelse ($bookings as $b)
        <tr>
            <td><code>{{ $b->booking_code }}</code><div class="small">{{ $b->created_at->translatedFormat('d M Y, H:i') }}</div></td>
            <td class="small">{{ $b->contact_name }}<br>{{ $b->contact_email }}</td>
            <td class="small">{{ $svc[$b->service_type] ?? '' }}<br>{{ $b->title }}</td>
            <td><span class="badge {{ $cls[$b->status] ?? 'text-bg-secondary' }}">{{ $b->status }}</span>@if ($b->refund_status === 'pending')<div class="small">Refund menunggu</div>@endif</td>
            <td class="text-end tk-mono">{{ \App\Support\Money::idr($b->total_amount) }}</td>
            <td class="text-end"><a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">Tidak ada pesanan.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $bookings->links() }}</div>
@endsection