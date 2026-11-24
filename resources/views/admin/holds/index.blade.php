@extends('layouts.admin')
@section('title', 'Hold')

@section('admin')
<h1 class="h4 mb-3">Hold</h1>
<form method="GET" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select" onchange="this.form.submit()">@foreach (['Active', 'Expired', 'Consumed', 'Released'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
    </div>
</form>

<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Kode</th><th>Pengguna</th><th>Layanan</th><th>Berakhir</th><th class="text-end">Total</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @forelse ($holds as $h)
        <tr>
            <td><code>{{ $h->hold_code }}</code></td>
            <td class="small">{{ $h->user?->name }}<br>{{ $h->user?->email }}</td>
            <td class="small">{{ $h->service_type }}<br>{{ $h->details['title'] ?? '' }}</td>
            <td class="small">{{ $h->expires_at->translatedFormat('d M Y, H:i') }}</td>
            <td class="text-end tk-mono">{{ \App\Support\Money::idr($h->total_amount) }}</td>
            <td class="text-end">
                @if ($h->status === 'Active')
                    <form method="POST" action="{{ route('admin.holds.release', $h) }}" class="d-inline"
                          data-confirm="Lepas penahanan {{ $h->hold_code }}? Inventori kembali tersedia dan pesanan yang menunggu bayar ikut ditutup." data-confirm-label="Lepas">
                        @csrf<button class="btn btn-sm btn-outline-danger">Lepas</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">Tidak ada hold dengan status ini.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $holds->links() }}</div>
@endsection