@extends('layouts.admin')
@section('title', 'Pesanan '.$booking->booking_code)

@section('admin')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Pesanan <code>{{ $booking->booking_code }}</code></h1>
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-link">Kembali</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="tk-card p-4 mb-3">
            <h2 class="h6">Ringkasan</h2>
            <dl class="row small mb-0">
                <dt class="col-4">Produk</dt><dd class="col-8">{{ $booking->title }}<br>{{ $booking->subtitle }}</dd>
                <dt class="col-4">Status</dt><dd class="col-8">{{ $booking->status }}@if ($booking->cancellation_reason) ({{ $booking->cancellation_reason }})@endif</dd>
                <dt class="col-4">Pelanggan</dt><dd class="col-8">{{ $booking->contact_name }}, {{ $booking->contact_email }}, {{ $booking->contact_phone }}</dd>
                <dt class="col-4">Hold</dt><dd class="col-8">{{ $booking->hold?->hold_code }} ({{ $booking->hold?->status }})</dd>
                <dt class="col-4">Harga awal</dt><dd class="col-8 tk-mono">{{ \App\Support\Money::idr($booking->base_amount) }}</dd>
                <dt class="col-4">Diskon</dt><dd class="col-8 tk-mono">-{{ \App\Support\Money::idr($booking->discount_amount) }}</dd>
                <dt class="col-4">Pajak dan biaya</dt><dd class="col-8 tk-mono">{{ \App\Support\Money::idr($booking->tax_amount + $booking->fee_amount) }}</dd>
                <dt class="col-4">Total</dt><dd class="col-8 tk-mono">{{ \App\Support\Money::idr($booking->total_amount) }}</dd>
                <dt class="col-4">Refund</dt><dd class="col-8 tk-mono">{{ \App\Support\Money::idr($booking->refund_amount) }} ({{ $booking->refund_status ?? '-' }})</dd>
            </dl>
        </div>
        <div class="tk-card p-4">
            <h2 class="h6">{{ $booking->service_type === 'hotel' ? 'Tamu' : 'Penumpang' }}</h2>
            <ul class="small mb-0">@foreach ($booking->passengers as $p)<li>{{ $p->full_name }}@if ($p->seat_label) · {{ $p->seat_label }}@endif @if ($p->room_label) · kamar {{ $p->room_label }}@endif</li>@endforeach</ul>
        </div>
    </div>
    <div class="col-lg-5">
        @if ($booking->refund_status === 'pending')
            <div class="tk-card p-4 mb-3">
                <h2 class="h6">Refund menunggu diproses</h2>
                <p class="small">Setelah dana dikembalikan lewat penyedia pembayaran, tandai sebagai diproses.</p>
                <form method="POST" action="{{ route('admin.bookings.refund-processed', $booking) }}"
                      data-confirm="Tandai refund {{ \App\Support\Money::idr($booking->refund_amount) }} sudah diproses?" data-confirm-label="Tandai diproses">
                    @csrf<button class="btn btn-primary w-100">Tandai refund diproses</button>
                </form>
            </div>
        @endif
        <div class="tk-card p-4 mb-3">
            <h2 class="h6">Tagihan</h2>
            @foreach ($booking->payments as $p)
                <div class="small border-top py-2"><a href="{{ route('admin.payments.show', $p) }}"><code>{{ $p->invoice_number }}</code></a><br>{{ $p->method_code }} · {{ \App\Support\Money::idr($p->amount) }} · <strong>{{ $p->status }}</strong></div>
            @endforeach
        </div>
        <div class="tk-card p-4">
            <h2 class="h6">Dokumen</h2>
            <div class="small">Receipt: {{ $booking->receipt?->receipt_number ?? 'belum ada' }}<br>Tiket: {{ $booking->tickets->count() }}<br>Email: {{ $booking->emailDeliveries->pluck('status')->implode(', ') ?: 'belum ada' }}</div>
        </div>
    </div>
</div>
@endsection