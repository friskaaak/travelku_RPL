@props(['step' => 1])
@php $items = ['Penahanan', 'Checkout', 'Pembayaran', 'Selesai']; @endphp
<ol class="tk-stepper no-print" aria-label="Tahapan pemesanan">
    @foreach ($items as $i => $label)
        @php $n = $i + 1; $state = $n < $step ? 'done' : ($n === $step ? 'current' : 'todo'); @endphp
        <li class="is-{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
            <span class="tk-step-dot">@if ($state === 'done')<i class="bi bi-check-lg" aria-hidden="true"></i>@else{{ $n }}@endif</span>
            <span class="tk-step-label">{{ $label }}</span>
        </li>
    @endforeach
</ol>