@php
    $pay = $s['payment'];
    $pr = $s['pricing'];
    $type = $s['service_type'];
    $d = $s['details'];
    $svc = ['hotel' => 'Hotel', 'flight' => 'Pesawat', 'train' => 'Kereta'][$type] ?? $type;
    $when = fn ($v) => \Carbon\Carbon::parse($v)->translatedFormat('d F Y, H:i').' WIB';
    $dt = fn ($v) => \Carbon\Carbon::parse($v)->translatedFormat('l, d F Y');
    $tm = fn ($v) => \Carbon\Carbon::parse($v)->format('H:i');
    $idr = fn ($v) => \App\Support\Money::idr($v);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $receipt->receipt_number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #101828; }
    h1 { font-size: 17px; margin: 0; }
    h2 { font-size: 11px; margin: 14px 0 4px; color: #155EEF; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 4px 6px; vertical-align: top; text-align: left; }
    .grid td, .grid th { border-bottom: 1px solid #EAECF0; }
    .muted { color: #475467; }
    .right { text-align: right; }
    .head td { border-bottom: 2px solid #155EEF; padding-bottom: 8px; }
    .box { border: 1px solid #FEDF89; background: #FFFAEB; color: #B54708; padding: 6px; margin: 8px 0; }
    .total td { border-top: 2px solid #155EEF; font-weight: bold; font-size: 12px; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td><h1>{{ $s['brand'] }}</h1><div class="muted">Bukti pembayaran</div></td>
        <td class="right"><div class="muted">Nomor receipt</div><strong>{{ $receipt->receipt_number }}</strong><div class="muted">Diterbitkan {{ $when($s['issued_at']) }}</div></td>
    </tr>
</table>

@if ($pay['is_simulated'])
    <div class="box">Pembayaran ini <strong>simulasi (mode demo)</strong>. Tidak ada uang sungguhan yang diproses. Dokumen ini bukan bukti transaksi keuangan yang sah.</div>
@endif

<table style="margin-top:8px">
    <tr>
        <td width="50%">
            <div class="muted">Kode booking</div><strong>{{ $s['booking']['code'] }}</strong><br><br>
            <div class="muted">Referensi pembayaran</div><strong>{{ $pay['reference'] }}</strong><br><br>
            <div class="muted">Nomor tagihan</div><strong>{{ $pay['invoice'] }}</strong><br><br>
            <div class="muted">Layanan</div><strong>{{ $svc }}</strong>
        </td>
        <td width="50%">
            <div class="muted">Pelanggan</div><strong>{{ $s['customer']['name'] }}</strong><br>{{ $s['customer']['email'] }}<br><br>
            <div class="muted">Metode pembayaran</div><strong>{{ $pay['method_label'] }}</strong><br><br>
            <div class="muted">Status pembayaran</div><strong>Berhasil (Paid)</strong><br><br>
            <div class="muted">Waktu transaksi</div><strong>{{ $when($pay['paid_at']) }}</strong><br><br>
            <div class="muted">Status pesanan</div><strong>{{ $s['booking']['status'] }}</strong>
        </td>
    </tr>
</table>

<h2>Rincian layanan</h2>
<table class="grid">
    <tr><td colspan="2"><strong>{{ $s['title'] }}</strong><br><span class="muted">{{ $s['subtitle'] }}</span></td></tr>
    @if ($type === 'hotel')
        <tr><td width="28%" class="muted">Alamat</td><td>{{ $d['address'] ?? '-' }}</td></tr>
        <tr><td class="muted">Check-in</td><td>{{ $dt($d['check_in']) }}, mulai {{ $d['check_in_time'] ?? '14:00' }}</td></tr>
        <tr><td class="muted">Check-out</td><td>{{ $dt($d['check_out']) }}, sebelum {{ $d['check_out_time'] ?? '12:00' }}</td></tr>
        <tr><td class="muted">Menginap</td><td>{{ $d['nights'] }} malam, {{ $d['room_qty'] }} kamar {{ $d['room_type'] }} ({{ collect($d['rooms'] ?? [])->pluck('room_number')->implode(', ') }})</td></tr>
        <tr><td class="muted">Tamu</td><td>{{ $d['adults'] }} dewasa, {{ $d['children'] }} anak</td></tr>
    @elseif ($type === 'flight')
        <tr><td width="28%" class="muted">Penerbangan</td><td>{{ $d['airline'] }} {{ $d['flight_number'] }}</td></tr>
        <tr><td class="muted">Rute</td><td>{{ $d['origin']['city'] }} ({{ $d['origin']['iata'] }}) ke {{ $d['destination']['city'] }} ({{ $d['destination']['iata'] }})</td></tr>
        <tr><td class="muted">Berangkat</td><td>{{ $dt($d['departure_at']) }}, {{ $tm($d['departure_at']) }} WIB</td></tr>
        <tr><td class="muted">Tiba</td><td>{{ $dt($d['arrival_at']) }}, {{ $tm($d['arrival_at']) }} WIB</td></tr>
        <tr><td class="muted">Bagasi</td><td>{{ $d['baggage_kg'] }} kg, kabin {{ $d['cabin_baggage_kg'] }} kg</td></tr>
    @else
        <tr><td width="28%" class="muted">Kereta</td><td>{{ $d['train_name'] }} ({{ $d['train_number'] }}), kelas {{ $d['class'] }}</td></tr>
        <tr><td class="muted">Rute</td><td>{{ $d['origin']['name'] }} ke {{ $d['destination']['name'] }}</td></tr>
        <tr><td class="muted">Berangkat</td><td>{{ $dt($d['departure_at']) }}, {{ $tm($d['departure_at']) }} WIB</td></tr>
        <tr><td class="muted">Tiba</td><td>{{ $dt($d['arrival_at']) }}, {{ $tm($d['arrival_at']) }} WIB</td></tr>
    @endif
</table>

<h2>{{ $type === 'hotel' ? 'Tamu' : 'Penumpang' }}</h2>
<table class="grid">
    <tr><th>Nama</th><th>{{ $type === 'hotel' ? 'Kamar' : 'Kursi' }}</th></tr>
    @foreach ($s['passengers'] as $p)
        <tr><td>{{ $p['name'] }}</td><td>{{ $type === 'hotel' ? ($p['room'] ?? '-') : ($p['seat'] ?? '-') }}</td></tr>
    @endforeach
</table>

<h2>Rincian pembayaran</h2>
<table class="grid">
    <tr><td>Subtotal (harga awal)</td><td class="right">{{ $idr($pr['base']) }}</td></tr>
    @forelse ($pr['discounts'] as $a)
        <tr><td>Diskon: {{ $a['name'] }}@if (! empty($a['code'])) (kode {{ $a['code'] }})@endif</td><td class="right">-{{ $idr($a['amount']) }}</td></tr>
    @empty
        <tr><td>Diskon</td><td class="right">-</td></tr>
    @endforelse
    <tr><td>Pajak ({{ $pr['tax_percent'] }}%)</td><td class="right">{{ $idr($pr['tax']) }}</td></tr>
    <tr><td>Biaya metode pembayaran</td><td class="right">{{ $pr['fee'] > 0 ? $idr($pr['fee']) : 'Gratis' }}</td></tr>
    <tr class="total"><td>Total dibayar</td><td class="right">{{ $idr($pay['amount']) }}</td></tr>
</table>

<p class="muted" style="margin-top:14px">Layanan, jadwal, harga, pajak, dan biaya pada dokumen ini adalah data demonstrasi. Dokumen disalin saat transaksi dan tidak berubah walau harga atau diskon di sistem diubah kemudian.</p>
</body>
</html>