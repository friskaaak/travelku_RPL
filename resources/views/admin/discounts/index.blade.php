@extends('layouts.admin')
@section('title', 'Kelola Diskon')

@section('admin')
@php
    $badge = [
        'active' => ['Aktif', '#ECFDF3', '#067647'],
        'scheduled' => ['Dijadwalkan', '#EFF4FF', '#155EEF'],
        'ended' => ['Berakhir', '#F2F4F7', '#475467'],
        'inactive' => ['Nonaktif', '#FFFAEB', '#B54708'],
        'exhausted' => ['Kuota habis', '#FEF3F2', '#B42318'],
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Diskon dan kode promo</h1>
        <p class="small mb-0">Status dihitung dengan waktu server (WIB).</p>
    </div>
    <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah diskon</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="GET" action="{{ route('admin.discounts.index') }}" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label">Cari nama atau kode</label>
            <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="60">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                @foreach ($badge as $key => [$label])<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="service" class="form-label">Layanan</label>
            <select id="service" name="service" class="form-select">
                <option value="">Semua</option>
                @foreach ($services as $key => $label)<option value="{{ $key }}" @selected(($filters['service'] ?? '') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-outline-primary flex-grow-1" type="submit">Saring</button>
            <a href="{{ route('admin.discounts.index') }}" class="btn btn-link">Reset</a>
        </div>
    </div>
</form>

<div class="tk-card">
    <div class="table-responsive">
        <table class="table tk-table align-middle mb-0">
            <thead>
                <tr><th>Diskon</th><th>Layanan</th><th>Potongan</th><th>Periode</th><th>Pemakaian</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($discounts as $d)
                    @php [$sLabel, $sBg, $sFg] = $badge[$d->status()]; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $d->name }}</div>
                            @if ($d->is_automatic)
                                <span class="badge text-bg-light border">Otomatis</span>
                            @else
                                <code>{{ $d->code }}</code>
                            @endif
                            @if ($d->is_stackable)<span class="badge text-bg-light border">Dapat digabung</span>@endif
                        </td>
                        <td class="small">{{ collect($d->services)->map(fn ($s) => $services[$s] ?? $s)->implode(', ') }}</td>
                        <td class="small">
                            {{ $d->type === 'percentage' ? $d->value.'%' : \App\Support\Money::idr($d->value) }}
                            @if ($d->max_discount)<br>maks. {{ \App\Support\Money::idr($d->max_discount) }}@endif
                            @if ($d->min_purchase > 0)<br>min. {{ \App\Support\Money::idr($d->min_purchase) }}@endif
                        </td>
                        <td class="small text-nowrap">{{ $d->starts_at->translatedFormat('d M Y H:i') }}<br>s.d. {{ $d->ends_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">
                            {{ $d->used_count }} / {{ $d->usage_limit ?? 'tanpa batas' }}
                            @if ($d->per_user_limit)<br>maks. {{ $d->per_user_limit }} per akun @endif
                        </td>
                        <td><span class="badge" style="background:{{ $sBg }};color:{{ $sFg }}">{{ $sLabel }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.discounts.edit', $d) }}" class="btn btn-sm btn-outline-primary">Ubah</a>

                            <form method="POST" action="{{ route('admin.discounts.toggle', $d) }}" class="d-inline"
                                  data-confirm="{{ $d->is_active ? 'Nonaktifkan diskon "'.$d->name.'"? Potongan dan kodenya langsung berhenti berlaku untuk pesanan baru.' : 'Aktifkan diskon "'.$d->name.'"? Diskon berlaku sesuai periodenya.' }}"
                                  data-confirm-label="{{ $d->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $d->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>

                            @if ($inUse->has($d->id))
                                <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Sudah pernah dipakai. Nonaktifkan saja.">Hapus</button>
                            @else
                                <form method="POST" action="{{ route('admin.discounts.destroy', $d) }}" class="d-inline"
                                      data-confirm="Hapus diskon &quot;{{ $d->name }}&quot; secara permanen? Tindakan ini tidak dapat dibatalkan."
                                      data-confirm-label="Hapus">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4">Tidak ada diskon yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $discounts->links() }}</div>
@endsection