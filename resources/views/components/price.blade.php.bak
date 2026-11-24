@props(['original', 'final', 'label' => null, 'suffix' => null])
@php $saving = max(0, (int) $original - (int) $final); @endphp
<div class="text-md-end">
    @if ($saving > 0)
        @if ($label)<span class="badge mb-1" style="background:#ECFDF3;color:#067647">{{ $label }}</span>@endif
        <div class="small text-decoration-line-through">{{ \App\Support\Money::idr($original) }}</div>
    @endif
    <div class="fs-4 fw-bold" style="color:#155EEF">{{ \App\Support\Money::idr($final) }}</div>
    @if ($saving > 0)<div class="small fw-semibold" style="color:#067647">Hemat {{ \App\Support\Money::idr($saving) }}</div>@endif
    @if ($suffix)<div class="small">{{ $suffix }}</div>@endif
</div>