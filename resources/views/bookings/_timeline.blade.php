<div class="tk-card p-4 mb-3">
    <h2 class="h6">Linimasa pesanan</h2>
    <ol class="list-unstyled mb-0">
        @foreach ($timeline as $e)
            <li class="d-flex gap-2 py-2 border-top small">
                <i class="bi {{ $e['icon'] }}" aria-hidden="true"></i>
                <div>
                    <strong class="text-dark">{{ $e['label'] }}</strong>
                    <div>{{ $e['at']->translatedFormat('d M Y, H:i') }} WIB</div>
                    @if (! empty($e['note']))<div>{{ $e['note'] }}</div>@endif
                </div>
            </li>
        @endforeach
    </ol>
</div>