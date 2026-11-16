@props(['type', 'details', 'base', 'applied' => [], 'taxPercent' => 0, 'tax' => 0, 'fee' => null, 'total', 'dynamic' => false])

<h2 class="h5 mb-1">{{ $details['title'] ?? '' }}</h2>
<p class="small mb-2">{{ $details['subtitle'] ?? '' }}</p>

@if ($type === 'hotel')
    <div class="small mb-2">
        <div><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($details['check_in'])->translatedFormat('d M Y') }} sampai {{ \Carbon\Carbon::parse($details['check_out'])->translatedFormat('d M Y') }} ({{ $details['nights'] }} malam)</div>
        <div class="mt-1">Kamar:
            @foreach ($details['rooms'] as $r)<span class="badge text-bg-light border">{{ $r['room_number'] }}</span>@endforeach
        </div>
    </div>
@else
    <div class="small mb-2">
        <div><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($details['departure_at'])->translatedFormat('l, d M Y, H:i') }} WIB</div>
        <div class="mt-1">Kursi:
            @foreach ($details['seats'] as $s)<span class="badge text-bg-light border">{{ $s['number'] }}</span>@endforeach
        </div>
    </div>
@endif

<hr class="my-2">
<div class="d-flex justify-content-between py-1"><span>Harga awal</span><span>{{ \App\Support\Money::idr($base) }}</span></div>

@forelse ($applied as $a)
    <div class="d-flex justify-content-between py-1" style="color:#067647">
        <span>{{ $a['name'] }}@if (! empty($a['code'])) (kode {{ $a['code'] }})@endif</span>
        <span>-{{ \App\Support\Money::idr($a['amount']) }}</span>
    </div>
@empty
    <div class="d-flex justify-content-between py-1"><span>Diskon</span><span>-</span></div>
@endforelse

<div class="d-flex justify-content-between py-1"><span>Pajak ({{ $taxPercent }}%)</span><span>{{ \App\Support\Money::idr($tax) }}</span></div>

<div class="d-flex justify-content-between py-1">
    <span>Biaya metode pembayaran</span>
    @if ($dynamic)
        <span id="sumFee">Pilih metode</span>
    @else
        <span>{{ ($fee ?? 0) > 0 ? \App\Support\Money::idr($fee) : 'Gratis' }}</span>
    @endif
</div>

<hr class="my-2">
<div class="d-flex justify-content-between fs-5 fw-bold text-dark">
    <span>Total bayar</span>
    @if ($dynamic)
        <span id="sumTotal" data-base="{{ $total }}" style="color:#155EEF">{{ \App\Support\Money::idr($total) }}</span>
    @else
        <span style="color:#155EEF">{{ \App\Support\Money::idr($total) }}</span>
    @endif
</div>