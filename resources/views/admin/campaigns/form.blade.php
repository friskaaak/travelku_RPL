@extends('layouts.admin')
@section('title', $isEdit ? 'Ubah Kampanye' : 'Tambah Kampanye')

@section('admin')
@php
    $c = $campaign;
    $hasOld = session()->hasOldInput();
    $fmt = 'Y-m-d\TH:i';
    $svcSel = $hasOld ? (array) old('services', []) : ($c->services ?? ['hotel', 'flight', 'train']);
    $isPub = $hasOld ? (bool) old('is_published') : (bool) $c->is_published;
    $selIds = array_map('intval', $hasOld ? (array) old('discount_ids', []) : $linked);
    $start = $c->starts_at?->format($fmt) ?? now()->format($fmt);
    $end = $c->ends_at?->format($fmt) ?? now()->addDays(30)->format($fmt);
    $promo = old('promo_code', $c->promo_code);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? 'Ubah kampanye' : 'Tambah kampanye' }}</h1>
    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-link">Kembali</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Periksa kembali isian Anda:</strong>
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.campaigns.update', $c) : route('admin.campaigns.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Informasi</h2>
        <div class="row">
            <div class="col-md-8"><x-field name="name" label="Nama kampanye" :value="$c->name" maxlength="120" /></div>
            <div class="col-md-4"><x-field name="badge" label="Lencana" :value="$c->badge" :required="false" maxlength="40" placeholder="Hemat 15%" /></div>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Deskripsi <span class="text-danger" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" rows="3" maxlength="1000" required class="form-control @error('description') is-invalid @enderror">{{ old('description', $c->description) }}</textarea>
        </div>
        @if ($isEdit)<p class="small mb-0">Alamat publik: <code>/promo/{{ $c->slug }}</code> (tetap, tidak berubah saat nama diubah).</p>@endif
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Banner</h2>
        @if ($isEdit && $c->banner_image)
            <div class="tk-banner mb-2" style="{{ $c->bannerStyle() }};min-height:120px"><span class="small">Banner saat ini</span></div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remove_banner" id="remove_banner" value="1" @checked(old('remove_banner'))>
                <label class="form-check-label" for="remove_banner">Hapus banner (kembali ke gradien bawaan)</label>
            </div>
        @endif
        <label for="banner" class="form-label">{{ $isEdit && $c->banner_image ? 'Ganti banner' : 'Unggah banner' }} <span class="fw-normal">(opsional)</span></label>
        <input type="file" id="banner" name="banner" class="form-control @error('banner') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB. Gunakan gambar yang Anda punya haknya. Tanpa banner, tampil gradien.</div>
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Jadwal dan cakupan</h2>
        <div class="row">
            <div class="col-md-6"><x-field name="starts_at" label="Mulai (WIB)" type="datetime-local" :value="$start" /></div>
            <div class="col-md-6"><x-field name="ends_at" label="Berakhir (WIB)" type="datetime-local" :value="$end" /></div>
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

        <x-field name="min_purchase" label="Minimal transaksi (Rp)" type="number" :value="$c->min_purchase ?? 0" :required="false" min="0" help="Dipakai bersama syarat minimal pada diskon. Nilai yang lebih tinggi yang berlaku." />
    </div>

    <div class="tk-card p-4 mb-3">
        <h2 class="h6 mb-3">Kode promo dan diskon terkait</h2>

        <div class="mb-3">
            <label for="promo_code" class="form-label">Kode promo utama</label>
            <select id="promo_code" name="promo_code" class="form-select @error('promo_code') is-invalid @enderror">
                <option value="">(tanpa kode)</option>
                @foreach ($codes as $disc)
                    <option value="{{ $disc->code }}" @selected($promo === $disc->code)>{{ $disc->code }} - {{ $disc->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Pilih dari daftar diskon. Belum ada? <a href="{{ route('admin.discounts.create') }}">Buat diskon baru</a> lebih dulu.</div>
        </div>

        <fieldset class="mb-2">
            <legend class="form-label">Diskon terkait lainnya</legend>
            <div class="row">
                @forelse ($discounts as $disc)
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="discount_ids[]" id="disc_{{ $disc->id }}" value="{{ $disc->id }}" @checked(in_array($disc->id, $selIds, true))>
                            <label class="form-check-label" for="disc_{{ $disc->id }}">{{ $disc->name }} <span class="text-muted small">({{ $disc->is_automatic ? 'otomatis' : $disc->code }})</span></label>
                        </div>
                    </div>
                @empty
                    <p class="small mb-0">Belum ada diskon.</p>
                @endforelse
            </div>
        </fieldset>
        <div class="tk-demo-note mt-2">Kode yang tertaut ke kampanye hanya valid saat kampanye <strong>terbit</strong> dan dalam periodenya. Pastikan periode diskon mencakup periode kampanye.</div>
    </div>

    <div class="tk-card p-4 mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" @checked($isPub)>
            <label class="form-check-label" for="is_published">Terbitkan (tampil sesuai periode di atas)</label>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan perubahan' : 'Buat kampanye' }}</button>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection