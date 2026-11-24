@extends('layouts.admin')
@section('title', 'Tagihan '.$payment->invoice_number)

@section('admin')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Tagihan <code>{{ $payment->invoice_number }}</code></h1>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-link">Kembali</a>
</div>

@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="tk-card p-4 mb-3">
            <dl class="row small mb-0">
                <dt class="col-5">Referensi</dt><dd class="col-7"><code>{{ $payment->payment_reference }}</code></dd>
                <dt class="col-5">Booking</dt><dd class="col-7"><a href="{{ route('admin.bookings.show', $payment->booking) }}">{{ $payment->booking->booking_code }}</a> ({{ $payment->booking->status }})</dd>
                <dt class="col-5">Pelanggan</dt><dd class="col-7">{{ $payment->booking->user?->name }}</dd>
                <dt class="col-5">Metode</dt><dd class="col-7">{{ $payment->method_code }} · {{ $payment->provider }}@if ($payment->is_simulated) (simulasi)@endif</dd>
                <dt class="col-5">Nominal</dt><dd class="col-7 tk-mono">{{ \App\Support\Money::idr($payment->amount) }}</dd>
                <dt class="col-5">Status</dt><dd class="col-7"><strong>{{ $payment->status }}</strong></dd>
                <dt class="col-5">Dibayar</dt><dd class="col-7">{{ $payment->paid_at?->translatedFormat('d M Y, H:i') ?? '-' }}</dd>
                <dt class="col-5">Catatan</dt><dd class="col-7">{{ $payment->failure_reason ?? '-' }}</dd>
            </dl>
        </div>

        @if ($payment->status === 'Reconciliation Required')
            <div class="tk-card p-4">
                <h2 class="h6">Selesaikan rekonsiliasi</h2>
                <p class="small">Pembayaran terverifikasi tetapi pesanan tidak dikonfirmasi. Satu-satunya penyelesaian yang tersedia: <strong>refund penuh {{ \App\Support\Money::idr($payment->amount) }}</strong>.</p>
                <form method="POST" action="{{ route('admin.payments.refund', $payment) }}"
                      data-confirm="Refund penuh {{ \App\Support\Money::idr($payment->amount) }} untuk tagihan ini? Tindakan tidak dapat dibatalkan." data-confirm-label="Refund penuh">
                    @csrf
                    <label for="note" class="form-label">Catatan (opsional)</label>
                    <input id="note" name="note" class="form-control mb-2" maxlength="200">
                    <button class="btn btn-primary w-100">Refund penuh</button>
                </form>
            </div>
        @endif
    </div>

    <div class="col-lg-6">
        <div class="tk-card p-4">
            <h2 class="h6">Riwayat peristiwa</h2>
            @forelse ($payment->events as $e)
                <div class="small border-top py-2">
                    <strong class="text-dark">{{ $e->event_type }}</strong> · {{ $e->source }}
                    <div>{{ $e->from_status ?? '-' }} ke {{ $e->to_status ?? '-' }}</div>
                    <div>{{ $e->created_at->translatedFormat('d M Y, H:i:s') }}</div>
                    @if ($e->payload)<div class="tk-code">{{ \Illuminate\Support\Str::limit(json_encode($e->payload, JSON_UNESCAPED_UNICODE), 160) }}</div>@endif
                </div>
            @empty
                <p class="small mb-0">Belum ada peristiwa.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection