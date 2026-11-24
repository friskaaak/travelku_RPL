@extends('layouts.admin')
@section('title', 'Tiket')

@section('admin')
<h1 class="h4 mb-3">Tiket dan voucher</h1>
<form method="GET" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-8"><label for="q" class="form-label">Nomor tiket atau kode booking</label><input id="q" name="q" class="form-control" maxlength="60" value="{{ $f['q'] ?? '' }}"></div>
        <div class="col-md-4 d-flex gap-1"><button class="btn btn-outline-primary flex-grow-1">Cari</button><a href="{{ route('admin.tickets.index') }}" class="btn btn-link">Reset</a></div>
    </div>
</form>

<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Tiket</th><th>Jenis</th><th>Nama</th><th>Booking</th><th>Terbit</th></tr></thead>
    <tbody>
    @forelse ($tickets as $t)
        @php $name = $t->snapshot['passenger']['name'] ?? ($t->snapshot['guests'][0]['name'] ?? '-'); @endphp
        <tr>
            <td><code>{{ $t->ticket_number }}</code></td>
            <td class="small">{{ $t->ticket_type }}</td>
            <td class="small">{{ $name }}</td>
            <td class="small"><a href="{{ route('admin.bookings.show', $t->booking_id) }}">{{ $t->booking->booking_code }}</a> ({{ $t->booking->status }})</td>
            <td class="small">{{ $t->issued_at->translatedFormat('d M Y, H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center py-4">Tidak ada tiket.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $tickets->links() }}</div>
@endsection