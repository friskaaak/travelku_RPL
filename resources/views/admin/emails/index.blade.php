@extends('layouts.admin')
@section('title', 'Log Email')

@section('admin')
@php $cls = ['queued' => 'text-bg-warning', 'sending' => 'text-bg-info', 'sent' => 'text-bg-success', 'failed' => 'text-bg-danger']; @endphp
<h1 class="h4 mb-3">Log email</h1>
<form method="GET" class="tk-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select" onchange="this.form.submit()"><option value="">Semua</option>@foreach (array_keys($cls) as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
    </div>
</form>

<div class="tk-card"><div class="table-responsive">
<table class="table tk-table align-middle mb-0">
    <thead><tr><th>Booking</th><th>Jenis</th><th>Penerima</th><th>Status</th><th>Percobaan</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    @forelse ($emails as $m)
        <tr>
            <td><a href="{{ route('admin.bookings.show', $m->booking_id) }}">{{ $m->booking?->booking_code }}</a></td>
            <td class="small">{{ $m->mail_type }}</td>
            <td class="small">{{ $m->recipient }}</td>
            <td><span class="badge {{ $cls[$m->status] ?? 'text-bg-secondary' }}">{{ $m->status }}</span>
                <div class="small">{{ $m->mailer }}@if ($m->mailer === 'log') (hanya log, tidak ke inbox)@endif</div>
                @if ($m->error_summary)<div class="small" style="color:var(--danger)">{{ $m->error_summary }}</div>@endif</td>
            <td class="small">{{ $m->attempts }}<br>{{ $m->last_attempt_at?->translatedFormat('d M, H:i') }}</td>
            <td class="text-end">
                @if (in_array($m->status, ['sent', 'failed']))
                    <form method="POST" action="{{ route('admin.emails.resend', $m) }}" class="d-inline"
                          data-confirm="Kirim ulang email ini ke {{ $m->recipient }}?" data-confirm-label="Kirim ulang">
                        @csrf<button class="btn btn-sm btn-outline-primary">Kirim ulang</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center py-4">Tidak ada catatan email.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $emails->links() }}</div>
@endsection