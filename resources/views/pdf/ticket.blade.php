<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Tiket</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #101828; }
    h1 { font-size: 15px; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 6px; vertical-align: top; }
    .band td { color: #ffffff; padding: 10px; }
    .flight td.b { background: #155EEF; } .train td.b { background: #067647; } .voucher td.b { background: #B54708; }
    .muted { color: #475467; } .right { text-align: right; }
    .box { border: 1px solid #FEDF89; background: #FFFAEB; color: #B54708; padding: 6px; margin: 8px 0; }
    .big { font-size: 15px; font-weight: bold; }
    .code { font-size: 7px; word-break: break-all; }
    .line td { border-top: 1px solid #EAECF0; }
</style>
</head>
<body>
@foreach ($items as $it)
    @php
        $s = $it['s'];
        $type = $it['ticket']->ticket_type;
        $label = ['voucher' => 'Voucher Hotel', 'flight' => 'E-Ticket Pesawat', 'train' => 'E-Ticket Kereta'][$type] ?? 'Tiket';
        $t = $s['trip'] ?? [];
        $h = $s['hotel'] ?? [];
        $dt = fn ($v, $f = 'l, d F Y') => \Carbon\Carbon::parse($v)->translatedFormat($f);
        $tm = fn ($v) => \Carbon\Carbon::parse($v)->format('H:i');
    @endphp

    <table class="band {{ $type }}">
        <tr><td class="b"><strong style="font-size:13px">{{ $label }}</strong></td><td class="b right">{{ $s['brand'] }} · {{ $s['ticket_number'] }}</td></tr>
    </table>

    @if ($s['is_simulated'])
        <div class="box">Tiket <strong>demonstrasi</strong> dari pembayaran simulasi. Bukan tiket resmi dan tidak dapat dipakai untuk bepergian.</div>
    @endif

    <table style="margin-top:8px">
        <tr>
            <td width="70%">
                @if ($type === 'voucher')
                    <div class="muted">Hotel</div>
                    <div class="big">{{ $h['title'] }}</div>
                    <div>{{ $h['address'] }}</div><br>
                    <div class="muted">Check-in</div><strong>{{ $dt($h['check_in']) }}, mulai {{ $h['check_in_time'] }}</strong><br><br>
                    <div class="muted">Check-out</div><strong>{{ $dt($h['check_out']) }}, sebelum {{ $h['check_out_time'] }}</strong><br><br>
                    <div class="muted">Kamar</div><strong>{{ $h['room_qty'] }} kamar {{ $h['room_type'] }}: {{ implode(', ', $h['rooms']) }}</strong> ({{ $h['nights'] }} malam)<br><br>
                    <div class="muted">Tamu</div>
                    @foreach ($s['guests'] as $g)<strong>{{ $g['name'] }}</strong>@if ($g['room']) (kamar {{ $g['room'] }})@endif<br>@endforeach
                    {{ $h['adults'] }} dewasa, {{ $h['children'] }} anak<br><br>
                    <div class="muted">Pembatalan</div>{{ $h['cancellation_policy'] ?? '-' }}
                @else
                    <div class="muted">Penumpang</div>
                    <div class="big">{{ $s['passenger']['name'] }}</div>
                    @if (! empty($s['passenger']['id_number']))<div class="muted">ID {{ $s['passenger']['id_number'] }}</div>@endif<br>
                    <table>
                        <tr>
                            <td width="40%"><div class="big">{{ $tm($t['departure_at']) }}</div>{{ $type === 'flight' ? $t['origin']['iata'] : $t['origin']['code'] }}<br><span class="muted">{{ $t['origin']['name'] }}</span></td>
                            <td width="20%" class="muted" style="text-align:center">{{ \App\Support\Format::duration($t['duration_minutes']) }}</td>
                            <td width="40%" class="right"><div class="big">{{ $tm($t['arrival_at']) }}</div>{{ $type === 'flight' ? $t['destination']['iata'] : $t['destination']['code'] }}<br><span class="muted">{{ $t['destination']['name'] }}</span></td>
                        </tr>
                    </table><br>
                    <div class="muted">{{ $type === 'flight' ? 'Penerbangan' : 'Kereta' }}</div>
                    <strong>{{ $type === 'flight' ? $t['airline'].' '.$t['flight_number'] : $t['train_name'].' ('.$t['train_number'].')' }}</strong><br><br>
                    <div class="muted">Tanggal berangkat</div><strong>{{ $dt($t['departure_at']) }} WIB</strong><br><br>
                    <div class="muted">Kursi</div><strong>{{ $s['seat']['number'] }} · {{ $s['seat']['class'] }}</strong>
                    @if ($type === 'flight')<br><br><div class="muted">Bagasi</div>{{ $t['baggage_kg'] }} kg, kabin {{ $t['cabin_baggage_kg'] }} kg @endif
                @endif
            </td>
            <td width="30%" style="text-align:center">
                @if ($it['qr'])
                    <img src="{{ $it['qr'] }}" width="130" height="130" alt="QR verifikasi demo">
                @else
                    <div class="muted">QR tidak tersedia</div>
                @endif
                <div class="muted" style="margin-top:4px">Kode verifikasi demo</div>
                <div class="code">{{ $it['code'] }}</div>
            </td>
        </tr>
    </table>

    <table class="line" style="margin-top:10px">
        <tr>
            <td><span class="muted">Kode booking</span><br><strong>{{ $s['booking_code'] }}</strong></td>
            <td><span class="muted">Tanggal pesan</span><br>{{ $dt($s['booked_at'], 'd F Y, H:i') }} WIB</td>
            <td><span class="muted">Status bayar</span><br>Paid (saat terbit)</td>
            <td><span class="muted">Diterbitkan</span><br>{{ $dt($s['issued_at'], 'd F Y, H:i') }} WIB</td>
        </tr>
    </table>
    <p class="muted" style="margin-top:8px">Dokumen demonstrasi. QR berisi tautan verifikasi demo. Bukan sistem check-in resmi.</p>

    @if (! $loop->last)<div style="page-break-after: always"></div>@endif
@endforeach
</body>
</html>