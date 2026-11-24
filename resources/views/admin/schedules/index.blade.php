@extends('layouts.admin')
@section('title', 'Jadwal '.$label)

@section('admin')
@php $cls = ['scheduled' => 'text-bg-success', 'closed' => 'text-bg-warning', 'cancelled' => 'text-bg-secondary']; $lbl = ['scheduled' => 'Dijadwalkan', 'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan']; @endphp
<h1 class="h4 mb-3">Jadwal {{ $label }}</h1>
<form method="GET" class="tk-card p-3 mb-3"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label for="date" class="form-label">Tanggal (kosong = mulai hari ini)</label><input type="date" id="date" name="date" class="form-control" value="{{ $f['date'] ?? '' }}"></div>
    <div class="col-md-4"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">Semua</option>@foreach ($lbl as $k => $l)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-4 d-flex gap-1"><button class="btn btn-outline-primary flex-grow-1">Saring</button><a href="{{ route('admin.schedules.index', $kind) }}" class="btn btn-link">Reset</a></div>
</div></form>
<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Berangkat</th><th>{{ $label }}</th><th>Harga</th><th>Kursi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @forelse ($rows as $s)
        <tr>
            <td class="small text-nowrap">{{ $s->departure_at->translatedFormat('d M Y') }}<br>{{ $s->departure_at->format('H:i') }}</td>
            <td class="small">@if ($kind === 'pesawat'){{ $s->flight->flight_number }} · {{ $s->flight->origin->iata }} ke {{ $s->flight->destination->iata }}@else{{ $s->train->name }} · {{ $s->class }}<br>{{ $s->origin->name }} ke {{ $s->destination->name }}@endif</td>
            <td class="small tk-mono">@if ($kind === 'pesawat'){{ \App\Support\Money::idr($s->economy_price) }}@if ($s->business_price)<br>{{ \App\Support\Money::idr($s->business_price) }}@endif @else{{ \App\Support\Money::idr($s->base_fare) }}@endif</td>
            <td class="small">Terjual {{ $s->sold }} · ditahan {{ $s->held }} · total {{ $s->total }}</td>
            <td><span class="badge {{ $cls[$s->status] ?? 'text-bg-secondary' }}">{{ $lbl[$s->status] ?? $s->status }}</span></td>
            <td class="text-end"><a href="{{ route('admin.schedules.edit', [$kind, $s->id]) }}" class="btn btn-sm btn-outline-primary">Ubah</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">Tidak ada jadwal. Jalankan seeder bila jadwal habis.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $rows->links() }}</div>
@endsection