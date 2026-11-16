@extends('layouts.admin')
@section('title', $isEdit ? 'Ubah Diskon' : 'Tambah Diskon')

@section('admin')
@php
    $d = $discount;
    $hasOld = session()->hasOldInput();
    $fmt = 'Y-m-d\TH:i';
    $svcSel = $hasOld ? (array) old('services', []) : ($d->services ?? ['hotel', 'flight', 'train']);
    $isAuto = $hasOld ? (bool) old('is_automatic') : (bool) $d->is_automatic;
    $isStack = $hasOld ? (bool) old('is_stackable') : (bool) $d->is_stackable;
    $isActive = $hasOld ? (bool) old('is_active') : ($isEdit ? (bool) $d->is_active : true);
    $type = old('type', $d->type ?: 'percentage');
    $scope = old('flight_scope', $d->scope_filter['flight_scope'] ?? '');
    $start = $d->starts_at?->format($fmt) ?? now()->format($fmt);
    $end = $d->ends_at?->format($fmt) ?? now()->addDays(30)->format($fmt);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? 'Ubah diskon' : 'Tambah diskon' }}</h1>
    <a href="{{ route('admin.discounts.index') }}" class="btn btn-link">Kembali</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Periksa kembali isian Anda:</strong>
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if ($isEdit && $d->used_count > 0)
    <div class="tk-demo-note mb-3">Diskon ini sudah dipakai {{ $d->used_count }} kali. Perubahan hanya berlaku untuk pesanan baru. Pesanan yang sudah ada tetap memakai nilai saat dibuat.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.discounts.update', $d) : route('admin.discounts.store') }}" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Informasi</h2>
        <x-field name="name" label="Nama diskon" :value="$d->name" maxlength="120" />
        <div class="row">
            <div class="col-md-6"><x-field name="label" label="Label badge" :value="$d->label" :required="false" maxlength="40" placeholder="Hemat 10%" help="Tampil di kartu hasil pencarian." /></div>
            <div class="col-md-6"><x-field name="description" label="Deskripsi singkat" :value="$d->description" :required="false" maxlength="500" /></div>
        </div>
        <div class="mb-0">
            <label for="terms" class="form-label">Syarat dan ketentuan</label>
            <textarea id="terms" name="terms" rows="3" maxlength="1000" class="form-control @error('terms') is-invalid @enderror">{{ old('terms', $d->terms) }}</textarea>
            <div class="form-text">Kosongkan agar server menyusunnya otomatis dari aturan di bawah.</div>
        </div>
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Potongan</h2>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="type" class="form-label">Jenis <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                    <option value="percentage" @selected($type === 'percentage')>Persentase (%)</option>
                    <option value="fixed" @selected($type === 'fixed')>Nominal tetap (Rp)</option>
                </select>
            </div>
            <div class="col-md-4"><x-field name="value" label="Nilai" type="number" :value="$d->value" min="1" help="Persen 1 sampai 100, atau jumlah rupiah." /></div>
            <div class="col-md-4" id="maxdisc_wrap" {{ $type !== 'percentage' ? 'hidden' : '' }}>
                <x-field name="max_discount" label="Batas potongan (Rp)" type="number" :value="$d->max_discount" :required="false" min="1" help="Opsional. Hanya untuk persentase." />
            </div>
        </div>
        <x-field name="min_purchase" label="Minimal transaksi (Rp)" type="number" :value="$d->min_purchase ?? 0" :required="false" min="0" />
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Jenis dan cakupan</h2>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_automatic" id="is_automatic" value="1" @checked($isAuto)>
            <label class="form-check-label" for="is_automatic">Diskon otomatis (tanpa kode, tampil di hasil pencarian)</label>
        </div>

        <div id="code_wrap" {{ $isAuto ? 'hidden' : '' }}>
            <x-field name="code" label="Kode promo" :value="$d->code" :required="false" maxlength="30" class="text-uppercase" autocomplete="off" help="Huruf, angka, dan tanda hubung. Otomatis diubah ke huruf besar." />
        </div>

        <fieldset class="mb-3">
            <legend class="form-label">Layanan <span class="text-danger" aria-hidden="true">*</span></legend>
            @foreach ($services as $key => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="services[]" id="svc_{{ $key }}" value="{{ $key }}" @checked(in_array($key, $svcSel, true))>
                    <label class="form-check-label" for="svc_{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach
            @error('services')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>

        <div id="scope_wrap" class="mb-3" {{ in_array('flight', $svcSel, true) ? '' : 'hidden' }}>
            <label for="flight_scope" class="form-label">Cakupan penerbangan</label>
            <select id="flight_scope" name="flight_scope" class="form-select">
                <option value="" @selected($scope === '')>Semua penerbangan</option>
                <option value="domestic" @selected($scope === 'domestic')>Hanya domestik</option>
                <option value="international" @selected($scope === 'international')>Hanya internasional</option>
            </select>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_stackable" id="is_stackable" value="1" @checked($isStack)>
            <label class="form-check-label" for="is_stackable">Dapat digabung dengan diskon lain</label>
            <div class="form-text">Penggabungan hanya terjadi bila <strong>kedua</strong> diskon bertanda dapat digabung. Selain itu, pelanggan mendapat satu diskon terbesar.</div>
        </div>
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Jadwal dan kuota</h2>
        <div class="row">
            <div class="col-md-6"><x-field name="starts_at" label="Mulai (WIB)" type="datetime-local" :value="$start" /></div>
            <div class="col-md-6"><x-field name="ends_at" label="Berakhir (WIB)" type="datetime-local" :value="$end" /></div>
        </div>
        <div id="limits_wrap" class="row" {{ $isAuto ? 'hidden' : '' }}>
            <div class="col-md-6"><x-field name="usage_limit" label="Kuota total" type="number" :value="$d->usage_limit" :required="false" min="1" help="Kosong = tanpa batas. @if($isEdit)Pemakaian saat ini: {{ $d->used_count }}.@endif" /></div>
            <div class="col-md-6"><x-field name="per_user_limit" label="Batas per akun" type="number" :value="$d->per_user_limit" :required="false" min="1" help="Kosong = tanpa batas." /></div>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked($isActive)>
            <label class="form-check-label" for="is_active">Aktif (berlaku sesuai periode di atas)</label>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan perubahan' : 'Buat diskon' }}</button>
        <a href="{{ route('admin.discounts.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection