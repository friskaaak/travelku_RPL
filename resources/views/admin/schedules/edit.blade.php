@extends('layouts.admin')
@section('title', 'Ubah jadwal')

@section('admin')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Ubah jadwal {{ $label }}</h1>
    <a href="{{ route('admin.schedules.index', $kind) }}" class="btn btn-link">Kembali</a>
</div>
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="tk-card p-4" style="max-width:560px">
    <p class="small">
        @if ($kind === 'pesawat'){{ $s->flight->airline->name }} {{ $s->flight->flight_number }}, {{ $s->flight->origin->iata }} ke {{ $s->flight->destination->iata }}@else{{ $s->train->name }}, {{ $s->origin->name }} ke {{ $s->destination->name }}@endif
        <br>{{ $s->departure_at->translatedFormat('l, d F Y, H:i') }} WIB · kursi terjual {{ $s->sold }}
    </p>
    <form method="POST" action="{{ route('admin.schedules.update', [$kind, $s->id]) }}" novalidate>
        @csrf @method('PUT')
        @if ($kind === 'pesawat')
            <x-field name="economy_price" label="Harga Economy (Rp)" type="number" :value="$s->economy_price" min="10000" />
            @if ($s->business_price !== null)<x-field name="business_price" label="Harga Business (Rp)" type="number" :value="$s->business_price" min="10000" />@endif
        @else
            <x-field name="base_fare" label="Tarif (Rp)" type="number" :value="$s->base_fare" min="10000" />
        @endif
        <div class="mb-3"><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="scheduled" @selected(old('status', $s->status) === 'scheduled')>Dijadwalkan</option>
                <option value="closed" @selected(old('status', $s->status) === 'closed')>Ditutup (tidak muncul di pencarian)</option>
                <option value="cancelled" @selected(old('status', $s->status) === 'cancelled')>Dibatalkan</option>
            </select>
            <div class="form-text">Dibatalkan ditolak bila sudah ada kursi terjual.</div></div>
        <p class="small">Harga baru berlaku untuk kursi yang masih kosong dan hold baru. Hold dan pesanan yang sudah ada tidak berubah.</p>
        <button class="btn btn-primary">Simpan</button>
    </form>
</div>
@endsection