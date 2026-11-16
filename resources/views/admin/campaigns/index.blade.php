@extends('layouts.admin')
@section('title', 'Kelola Kampanye')

@section('admin')
@php
    $badge = [
        'draft' => ['Draf', '#FFFAEB', '#B54708'],
        'upcoming' => ['Terjadwal', '#EFF4FF', '#155EEF'],
        'active' => ['Berjalan', '#ECFDF3', '#067647'],
        'ended' => ['Berakhir', '#F2F4F7', '#475467'],
    ];
    $names = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Kampanye musiman</h1>
        <p class="small mb-0">Kampanye tampil di publik hanya bila terbit dan sedang dalam periodenya (waktu server).</p>
    </div>
    <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah kampanye</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="GET" action="{{ route('admin.campaigns.index') }}" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label for="q" class="form-label">Cari nama atau kode</label>
            <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="60">
        </div>
        <div class="col-md-4">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                @foreach ($badge as $key => [$label])<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button class="btn btn-outline-primary flex-grow-1" type="submit">Saring</button>
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-link">Reset</a>
        </div>
    </div>
</form>

<div class="tk-card">
    <div class="table-responsive">
        <table class="table tk-table align-middle mb-0">
            <thead>
                <tr><th>Kampanye</th><th>Periode</th><th>Layanan</th><th>Kode</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $c)
                    @php [$sLabel, $sBg, $sFg] = $badge[$c->status()]; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $c->name }}</div>
                            @if ($c->badge)<span class="badge text-bg-light border">{{ $c->badge }}</span>@endif
                            <div class="small">/promo/{{ $c->slug }}</div>
                        </td>
                        <td class="small text-nowrap">{{ $c->starts_at->translatedFormat('d M Y H:i') }}<br>s.d. {{ $c->ends_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ collect($c->services)->map(fn ($s) => $names[$s] ?? $s)->implode(', ') }}</td>
                        <td class="small">
                            @if ($c->promo_code)<code>{{ $c->promo_code }}</code>@else - @endif
                            @if ($c->discounts->count() > 0)<br>{{ $c->discounts->count() }} diskon terkait @endif
                        </td>
                        <td><span class="badge" style="background:{{ $sBg }};color:{{ $sFg }}">{{ $sLabel }}</span></td>
                        <td class="text-end text-nowrap">
                            @if ($c->is_published)
                                <a href="{{ route('campaigns.show', $c->slug) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Lihat</a>
                            @endif
                            <a href="{{ route('admin.campaigns.edit', $c) }}" class="btn btn-sm btn-outline-primary">Ubah</a>

                            <form method="POST" action="{{ route('admin.campaigns.toggle', $c) }}" class="d-inline"
                                  data-confirm="{{ $c->is_published ? 'Tarik kampanye "'.$c->name.'" menjadi draf? Halamannya hilang dari publik dan kode promo yang tertaut berhenti berlaku.' : 'Terbitkan kampanye "'.$c->name.'"? Kampanye tampil sesuai periodenya.' }}"
                                  data-confirm-label="{{ $c->is_published ? 'Tarik' : 'Terbitkan' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $c->is_published ? 'Tarik' : 'Terbitkan' }}</button>
                            </form>

                            <form method="POST" action="{{ route('admin.campaigns.destroy', $c) }}" class="d-inline"
                                  data-confirm="Hapus kampanye &quot;{{ $c->name }}&quot; secara permanen? Diskon yang tertaut tidak ikut terhapus."
                                  data-confirm-label="Hapus">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">Tidak ada kampanye yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $campaigns->links() }}</div>
@endsection