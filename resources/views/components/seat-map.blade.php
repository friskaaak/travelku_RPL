@props(['rows', 'max' => 1, 'aisleAfter' => 'C', 'interactive' => true, 'summaryId' => 'seatSummary', 'noticeId' => 'seatNotice', 'fieldsId' => null, 'submitId' => null])
@php
    $stateText = [
        'available' => 'tersedia',
        'held' => 'sedang ditahan pelanggan lain',
        'confirmed' => 'sudah terjual',
        'mine' => 'ditahan untuk Anda',
    ];
    $lastClass = null;
@endphp

<div class="d-flex flex-wrap gap-3 small mb-3" aria-label="Keterangan kursi">
    <span><span class="tk-seat tk-seat-sample" data-state="available"></span> Tersedia</span>
    <span><span class="tk-seat tk-seat-sample" data-state="held"></span> Ditahan sementara</span>
    <span><span class="tk-seat tk-seat-sample is-selected"></span> Dipilih</span>
    <span><span class="tk-seat tk-seat-sample" data-state="confirmed"></span> Terjual</span>
</div>

<div class="overflow-x-auto">
        <div class="tk-seatmap" data-max="{{ $max }}" data-summary="{{ $summaryId }}" data-notice="{{ $noticeId }}"
         @if ($fieldsId) data-fields="{{ $fieldsId }}" @endif
         @if ($submitId) data-submit="{{ $submitId }}" @endif>
        @foreach ($rows as $row)
            @if ($row['class'] !== $lastClass)
                <div class="tk-seat-class">{{ $row['class'] }}</div>
                @php $lastClass = $row['class']; @endphp
            @endif
            <div class="tk-seat-row" role="group" aria-label="Baris {{ $row['row'] }}">
                <span class="tk-seat-rownum">{{ $row['row'] }}</span>
                @foreach ($row['seats'] as $seat)
                    <button type="button" class="tk-seat"
                            data-seat="{{ $seat['label'] }}" data-state="{{ $seat['state'] }}"
                            aria-pressed="false"
                            aria-label="Kursi {{ $seat['label'] }}, {{ $seat['class'] }}, {{ $stateText[$seat['state']] }}"
                            title="{{ $seat['label'] }} · {{ $seat['class'] }} · {{ \App\Support\Money::idr($seat['price']) }} · {{ $stateText[$seat['state']] }}"
                            @disabled(! $interactive || $seat['state'] !== 'available')>{{ $seat['letter'] }}</button>
                    @if ($seat['letter'] === $aisleAfter)<span class="tk-seat-aisle" aria-hidden="true"></span>@endif
                @endforeach
            </div>
        @endforeach
    </div>
</div>
<div id="{{ $noticeId }}" class="small mt-2" style="color:#B54708" role="status" aria-live="polite"></div>