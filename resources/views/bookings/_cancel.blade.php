@php
    $idr = fn ($v) => \App\Support\Money::idr($v);
    $demo = \App\Support\PaymentMode::isDemo();
@endphp

@if ($booking->status === 'Cancelled' && $booking->refund_status)
    <div class="tk-card p-4 mb-3">
        <h2 class="h6">Pembatalan dan refund</h2>
        <dl class="row small mb-2">
            <dt class="col-6">Biaya pembatalan</dt><dd class="col-6 tk-mono">{{ $idr($booking->penalty_amount) }}</dd>
            <dt class="col-6">Refund</dt><dd class="col-6 tk-mono">{{ $idr($booking->refund_amount) }}</dd>
        </dl>
        <p class="small mb-0">
            @if ($booking->refund_status === 'simulated') Refund simulasi tercatat. Mode demo: tidak ada uang sungguhan yang dikembalikan.
            @elseif ($booking->refund_status === 'pending') Refund menunggu diproses admin.
            @elseif ($booking->refund_status === 'processed') Refund telah diproses admin.
            @else Tidak ada refund karena biaya pembatalan sama dengan total pembayaran.
            @endif
        </p>
    </div>

@elseif ($booking->status === 'Confirmed')
    <div class="tk-card p-4 mb-3">
        <h2 class="h6">Pembatalan</h2>
        @if ($quote['allowed'])
            @if ($quote['free'])
                <span class="badge text-bg-success mb-2">Pembatalan gratis</span>
            @else
                <span class="badge text-bg-warning mb-2">Dikenai biaya pembatalan</span>
            @endif
            @if ($quote['deadline'])
                <p class="small mb-2">Batas pembatalan gratis: {{ $quote['deadline']->translatedFormat('d M Y, H:i') }} WIB.</p>
            @endif
            <dl class="row small mb-2">
                <dt class="col-6">Biaya pembatalan</dt><dd class="col-6 tk-mono">{{ $idr($quote['penalty']) }}</dd>
                <dt class="col-6">Refund</dt><dd class="col-6 tk-mono">{{ $idr($quote['refund']) }}</dd>
            </dl>
            @if ($demo)<p class="small">Mode demo: refund hanya dicatat sebagai simulasi.</p>@endif

            <form method="POST" action="{{ route('bookings.cancel', $booking->booking_code) }}"
                  onsubmit="return confirm('Batalkan pesanan ini? Biaya pembatalan {{ $idr($quote['penalty']) }}, refund {{ $idr($quote['refund']) }}. Tindakan ini tidak dapat dibatalkan.')">
                @csrf
                <label for="reason" class="form-label">Alasan (opsional)</label>
                <input type="text" id="reason" name="reason" class="form-control mb-2" maxlength="200">
                <button type="submit" class="btn btn-outline-danger w-100">Batalkan pesanan</button>
            </form>
        @else
            <p class="small mb-0">{{ $quote['reason'] }}</p>
        @endif
    </div>
@endif