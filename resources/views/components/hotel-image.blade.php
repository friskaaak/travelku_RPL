@props(['image' => null, 'height' => '100%', 'minHeight' => '0'])
@php
    $image = is_array($image) ? $image : [];
    $box = "height:{$height};min-height:{$minHeight}";
@endphp
@if (! empty($image['src']))
    <img src="{{ asset($image['src']) }}" alt="{{ $image['label'] ?? 'Foto hotel' }}" class="w-100 rounded-3" style="{{ $box }};object-fit:cover">
@else
    <div role="img" aria-label="{{ $image['label'] ?? 'Foto hotel' }}"
         class="d-flex flex-column align-items-center justify-content-center text-white rounded-3 w-100"
         style="{{ $box }};background:linear-gradient(135deg,{{ $image['from'] ?? '#155EEF' }},{{ $image['to'] ?? '#53B1FD' }})">
        <i class="bi {{ $image['icon'] ?? 'bi-building' }} fs-1" aria-hidden="true"></i>
        <small>{{ $image['label'] ?? '' }}</small>
    </div>
@endif