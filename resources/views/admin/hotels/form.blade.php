@extends('layouts.admin')
@section('title', $isEdit ? 'Ubah Hotel' : 'Tambah Hotel')

@section('admin')
@php
    $h = $hotel; $hasOld = session()->hasOldInput();
    $amSel = $hasOld ? (array) old('amenities', []) : ($h->amenities ?? []);
    $isPub = $hasOld ? (bool) old('is_published') : ($isEdit ? (bool) $h->is_published : true);
    $isFree = $hasOld ? (bool) old('free_cancellation') : (bool) $h->free_cancellation;
    $t = fn ($v, $d) => $v ? substr($v, 0, 5) : $d;
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? 'Ubah hotel' : 'Tambah hotel' }}</h1>
    <a href="{{ route('admin.hotels.index') }}" class="btn btn-link">Kembali</a>
</div>
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $isEdit ? route('admin.hotels.update', $h) : route('admin.hotels.store') }}" novalidate>
    @csrf @if ($isEdit) @method('PUT') @endif
    <div class="tk-card p-4 mb-3">
        <div class="row">
            <div class="col-md-8"><x-field name="name" label="Nama hotel" :value="$h->name" maxlength="120" /></div>
            <div class="col-md-4"><x-field name="city" label="Kota" :value="$h->city" maxlength="60" /></div>
        </div>
        <x-field name="address" label="Alamat" :value="$h->address" maxlength="255" />
        <div class="mb-3"><label for="description" class="form-label">Deskripsi <span class="text-danger">*</span></label>
            <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control @error('description') is-invalid @enderror">{{ old('description', $h->description) }}</textarea></div>
        <div class="row">
            <div class="col-md-3"><x-field name="star_rating" label="Bintang" type="number" :value="$h->star_rating ?? 3" min="1" max="5" /></div>
            <div class="col-md-3"><x-field name="rating" label="Rating (0-5)" type="number" :value="$h->rating ?? 0" step="0.1" min="0" max="5" /></div>
            <div class="col-md-3"><x-field name="latitude" label="Latitude" :value="$h->latitude" :required="false" /></div>
            <div class="col-md-3"><x-field name="longitude" label="Longitude" :value="$h->longitude" :required="false" /></div>
        </div>
    </div>

    <div class="tk-card p-4 mb-3">
        <fieldset class="mb-3"><legend class="form-label">Fasilitas</legend>
            @foreach ($amenityOptions as $a)
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="amenities[]" id="am{{ $loop->index }}" value="{{ $a }}" @checked(in_array($a, $amSel, true))><label class="form-check-label" for="am{{ $loop->index }}">{{ $a }}</label></div>
            @endforeach
        </fieldset>
        <div class="row">
            <div class="col-md-3"><x-field name="check_in_time" label="Jam check-in" type="time" :value="$t($h->check_in_time, '14:00')" /></div>
            <div class="col-md-3"><x-field name="check_out_time" label="Jam check-out" type="time" :value="$t($h->check_out_time, '12:00')" /></div>
            <div class="col-md-3"><x-field name="cancellation_hours" label="Batas batal gratis (jam)" type="number" :value="$h->cancellation_hours ?? 24" min="1" max="720" /></div>
            <div class="col-md-3 d-flex align-items-center"><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="free_cancellation" id="free_cancellation" value="1" @checked($isFree)><label class="form-check-label" for="free_cancellation">Pembatalan gratis</label></div></div>
        </div>
        <div class="mb-3"><label for="cancellation_policy" class="form-label">Kebijakan pembatalan</label>
            <textarea id="cancellation_policy" name="cancellation_policy" rows="2" maxlength="1000" class="form-control">{{ old('cancellation_policy', $h->cancellation_policy) }}</textarea>
            <div class="form-text">Kosongkan agar server menyusunnya otomatis.</div></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" @checked($isPub)><label class="form-check-label" for="is_published">Tampil di pencarian</label></div>
    </div>
    <div class="d-flex gap-2 mb-4"><button class="btn btn-primary">{{ $isEdit ? 'Simpan perubahan' : 'Buat hotel' }}</button><a href="{{ route('admin.hotels.index') }}" class="btn btn-outline-secondary">Batal</a></div>
</form>

@if ($isEdit)
<div class="tk-card p-4">
    <h2 class="h6">Kamar ({{ $h->rooms->count() }})</h2>
    <p class="small">Kamar tidak dihapus. Pakai status Perawatan untuk menonaktifkan. Harga baru berlaku untuk hold baru.</p>
    <div class="table-responsive"><table class="table tk-table align-middle">
        <thead><tr><th>No</th><th>Tipe</th><th>Kapasitas</th><th>Tempat tidur</th><th>Harga / malam</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($h->rooms as $r)
            <tr>
                <form method="POST" action="{{ route('admin.rooms.update', $r) }}">@csrf @method('PUT')
                <td><code>{{ $r->room_number }}</code></td><td class="small">{{ $r->room_type }}</td>
                <td><input type="number" name="capacity" value="{{ $r->capacity }}" min="1" max="10" class="form-control form-control-sm" style="width:72px" aria-label="Kapasitas kamar {{ $r->room_number }}"></td>
                <td><input name="bed_type" value="{{ $r->bed_type }}" maxlength="40" class="form-control form-control-sm" aria-label="Tempat tidur kamar {{ $r->room_number }}"></td>
                <td><input type="number" name="base_price" value="{{ $r->base_price }}" min="50000" class="form-control form-control-sm" style="width:130px" aria-label="Harga kamar {{ $r->room_number }}"></td>
                <td><select name="status" class="form-select form-select-sm" aria-label="Status kamar {{ $r->room_number }}"><option value="active" @selected($r->status === 'active')>Aktif</option><option value="maintenance" @selected($r->status === 'maintenance')>Perawatan</option></select></td>
                <td><button class="btn btn-sm btn-outline-primary">Simpan</button></td>
                </form>
            </tr>
        @endforeach
        </tbody>
    </table></div>

    <h3 class="h6 mt-3">Tambah kamar</h3>
    <form method="POST" action="{{ route('admin.rooms.store', $h) }}" class="row g-2 align-items-end">@csrf
        <div class="col-md-2"><label for="room_number" class="form-label">Nomor</label><input id="room_number" name="room_number" class="form-control" maxlength="10" required></div>
        <div class="col-md-2"><label for="room_type" class="form-label">Tipe</label><select id="room_type" name="room_type" class="form-select">@foreach (['Standard', 'Superior', 'Deluxe', 'Family'] as $ty)<option>{{ $ty }}</option>@endforeach</select></div>
        <div class="col-md-2"><label for="capacity_new" class="form-label">Kapasitas</label><input id="capacity_new" type="number" name="capacity" class="form-control" min="1" max="10" value="2" required></div>
        <div class="col-md-3"><label for="bed_new" class="form-label">Tempat tidur</label><input id="bed_new" name="bed_type" class="form-control" maxlength="40"></div>
        <div class="col-md-2"><label for="price_new" class="form-label">Harga</label><input id="price_new" type="number" name="base_price" class="form-control" min="50000" required></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">Tambah</button></div>
    </form>
</div>
@endif
@endsection