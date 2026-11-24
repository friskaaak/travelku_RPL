@props(['original', 'final', 'label' => null, 'suffix' => null])
@php $saving = max(0, (int) $original - (int) $final); @endphp
<div class="text-md-end">
    @if ($saving > 0)
        @if ($label)<span class="badge tk-badge-success mb-1">{{ $label }}</span>@endif
        <div class="small text-decoration-line-through tk-mono">{{ \App\Support\Money::idr($original) }}</div>
    @endif
    <div class="tk-price">{{ \App\Support\Money::idr($final) }}</div>
    @if ($saving > 0)<div class="small fw-semibold tk-text-success tk-mono">Hemat {{ \App\Support\Money::idr($saving) }}</div>@endif
    @if ($suffix)<div class="small">{{ $suffix }}</div>@endif
</div>