@props(['dep', 'arr', 'from', 'to', 'fromName' => null, 'toName' => null, 'duration', 'note' => null])
@php $shift = (int) abs($dep->copy()->startOfDay()->diffInDays($arr->copy()->startOfDay())); @endphp
<div class="tk-trip">
    <div class="tk-trip-end">
        <div class="tk-time">{{ $dep->format('H:i') }}</div>
        <div class="small fw-semibold text-dark">{{ $from }}</div>
        @if ($fromName)<div class="small">{{ $fromName }}</div>@endif
    </div>
    <div class="tk-trip-line">
        <div class="tk-mono">{{ \App\Support\Format::duration($duration) }}</div>
        <div class="bar"></div>
        @if ($note)<div>{{ $note }}</div>@endif
    </div>
    <div class="tk-trip-end is-arr">
        <div class="tk-time">{{ $arr->format('H:i') }}@if ($shift > 0)<sup class="small"> +{{ $shift }}</sup>@endif</div>
        <div class="small fw-semibold text-dark">{{ $to }}</div>
        @if ($toName)<div class="small">{{ $toName }}</div>@endif
    </div>
</div>