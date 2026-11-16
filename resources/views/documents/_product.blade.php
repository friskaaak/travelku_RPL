@php
    $dt = fn ($v, $f = 'l, d F Y') => \Carbon\Carbon::parse($v)->translatedFormat($f);
    $tm = fn ($v) => \Carbon\Carbon::parse($v)->format('H:i');
@endphp

@if ($type === 'hotel')
    <dl class="row tk-dl mb-0">
        <dt class="col-sm-4">Hotel</dt><dd class="col-sm-8">{{ $d['title'] ?? '-' }}</dd>
        <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $d['address'] ?? '-' }}</dd>
        <dt class="col-sm-4">Check-in</dt><dd class="col-sm-8">{{ $dt($d['check_in']) }}, mulai {{ $d['check_in_time'] ?? '14:00' }}</dd>
        <dt class="col-sm-4">Check-out</dt><dd class="col-sm-8">{{ $dt($d['check_out']) }}, sebelum {{ $d['check_out_time'] ?? '12:00' }}</dd>
        <dt class="col-sm-4">Lama menginap</dt><dd class="col-sm-8">{{ $d['nights'] }} malam</dd>
        <dt class="col-sm-4">Kamar</dt>
        <dd class="col-sm-8">{{ $d['room_qty'] }} kamar {{ $d['room_type'] }}:
            @foreach ($d['rooms'] ?? [] as $r)<span class="badge text-bg-light border">{{ $r['room_number'] }}</span>@endforeach
        </dd>
        <dt class="col-sm-4">Tamu</dt><dd class="col-sm-8">{{ $d['adults'] }} dewasa, {{ $d['children'] }} anak</dd>
    </dl>
@elseif ($type === 'flight')
    <dl class="row tk-dl mb-0">
        <dt class="col-sm-4">Penerbangan</dt><dd class="col-sm-8">{{ $d['airline'] }} {{ $d['flight_number'] }}</dd>
        <dt class="col-sm-4">Rute</dt><dd class="col-sm-8">{{ $d['origin']['city'] }} ({{ $d['origin']['iata'] }}) ke {{ $d['destination']['city'] }} ({{ $d['destination']['iata'] }})</dd>
        <dt class="col-sm-4">Berangkat</dt><dd class="col-sm-8">{{ $dt($d['departure_at']) }}, {{ $tm($d['departure_at']) }} WIB</dd>
        <dt class="col-sm-4">Tiba</dt><dd class="col-sm-8">{{ $dt($d['arrival_at']) }}, {{ $tm($d['arrival_at']) }} WIB</dd>
        <dt class="col-sm-4">Durasi</dt><dd class="col-sm-8">{{ \App\Support\Format::duration($d['duration_minutes']) }}, {{ ($d['is_direct'] ?? true) ? 'langsung' : 'transit' }}</dd>
        <dt class="col-sm-4">Bagasi</dt><dd class="col-sm-8">{{ $d['baggage_kg'] }} kg, kabin {{ $d['cabin_baggage_kg'] }} kg</dd>
    </dl>
@else
    <dl class="row tk-dl mb-0">
        <dt class="col-sm-4">Kereta</dt><dd class="col-sm-8">{{ $d['train_name'] }} ({{ $d['train_number'] }}), kelas {{ $d['class'] }}</dd>
        <dt class="col-sm-4">Rute</dt><dd class="col-sm-8">{{ $d['origin']['name'] }} ke {{ $d['destination']['name'] }}</dd>
        <dt class="col-sm-4">Berangkat</dt><dd class="col-sm-8">{{ $dt($d['departure_at']) }}, {{ $tm($d['departure_at']) }} WIB</dd>
        <dt class="col-sm-4">Tiba</dt><dd class="col-sm-8">{{ $dt($d['arrival_at']) }}, {{ $tm($d['arrival_at']) }} WIB</dd>
        <dt class="col-sm-4">Durasi</dt><dd class="col-sm-8">{{ \App\Support\Format::duration($d['duration_minutes']) }}</dd>
    </dl>
@endif