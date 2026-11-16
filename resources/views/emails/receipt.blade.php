@extends('emails.layout')
@section('subject', 'Bukti pembayaran '.$booking->booking_code)

@section('body')
@php
    $idr = fn ($v) => \App\Support\Money::idr($v);
    $pay = $s['payment'];
    $pr = $s['pricing'];
    $paidAt = \Carbon\Carbon::parse($pay['paid_at'])->translatedFormat('d F Y, H:i').' WIB';
    $summary = [
        ['Kode booking', $booking->booking_code],
        ['Nomor receipt', $s['receipt_number']],
        ['Referensi pembayaran', $pay['reference']],
        ['Nomor tagihan', $pay['invoice']],
        ['Metode pembayaran', $pay['method_label']],
        ['Waktu pembayaran', $paidAt],
        ['Status pembayaran', 'Berhasil (Paid)'],
    ];
@endphp

<p style="margin:0 0 12px;">Halo <strong style="color:#101828;">{{ $s['customer']['name'] }}</strong>,</p>
<p style="margin:0 0 12px;">Pembayaran Anda telah terverifikasi dan pesanan <strong style="color:#101828;">{{ $booking->booking_code }}</strong> berstatus <strong>terkonfirmasi</strong>. Bukti pembayaran terlampir dalam bentuk PDF.</p>

@if ($isSimulated)
    <p style="margin:0 0 12px;padding:10px;background:#FFFAEB;border:1px solid #FEDF89;color:#B54708;border-radius:8px;">Pembayaran ini <strong>simulasi (mode demo)</strong>. Tidak ada uang sungguhan yang diproses dan dokumen ini bukan bukti transaksi keuangan yang sah.</p>
@endif

<h2 style="font-size:15px;color:#101828;margin:20px 0 8px;">Ringkasan pembayaran</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
    @foreach ($summary as [$label, $value])
        <tr>
            <td style="padding:5px 0;width:40%;color:#667085;border-bottom:1px solid #F2F4F7;">{{ $label }}</td>
            <td style="padding:5px 0;color:#101828;border-bottom:1px solid #F2F4F7;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<h2 style="font-size:15px;color:#101828;margin:20px 0 8px;">Ringkasan pesanan</h2>
<p style="margin:0 0 6px;"><strong style="color:#101828;">{{ $s['title'] }}</strong><br>{{ $s['subtitle'] }}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
    @foreach ($lines as [$label, $value])
        <tr>
            <td style="padding:5px 0;width:30%;color:#667085;vertical-align:top;border-bottom:1px solid #F2F4F7;">{{ $label }}</td>
            <td style="padding:5px 0;color:#101828;border-bottom:1px solid #F2F4F7;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

<h2 style="font-size:15px;color:#101828;margin:20px 0 8px;">Rincian harga</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
    <tr><td style="padding:5px 0;">Subtotal (harga awal)</td><td align="right" style="padding:5px 0;">{{ $idr($pr['base']) }}</td></tr>
    @forelse ($pr['discounts'] as $a)
        <tr>
            <td style="padding:5px 0;color:#067647;">Diskon: {{ $a['name'] }}@if (! empty($a['code'])) (kode {{ $a['code'] }})@endif</td>
            <td align="right" style="padding:5px 0;color:#067647;">-{{ $idr($a['amount']) }}</td>
        </tr>
    @empty
        <tr><td style="padding:5px 0;">Diskon</td><td align="right" style="padding:5px 0;">-</td></tr>
    @endforelse
    <tr><td style="padding:5px 0;">Pajak ({{ $pr['tax_percent'] }}%)</td><td align="right" style="padding:5px 0;">{{ $idr($pr['tax']) }}</td></tr>
    <tr><td style="padding:5px 0;">Biaya metode pembayaran</td><td align="right" style="padding:5px 0;">{{ $pr['fee'] > 0 ? $idr($pr['fee']) : 'Gratis' }}</td></tr>
    <tr>
        <td style="padding:10px 0 0;border-top:2px solid #155EEF;font-size:16px;font-weight:bold;color:#101828;">Total dibayar</td>
        <td align="right" style="padding:10px 0 0;border-top:2px solid #155EEF;font-size:16px;font-weight:bold;color:#155EEF;">{{ $idr($pay['amount']) }}</td>
    </tr>
</table>

<p style="margin:20px 0 8px;">
    <a href="{{ $url }}" style="display:inline-block;background:#155EEF;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">Lihat pesanan</a>
</p>
<p style="margin:0;font-size:12px;color:#667085;">Tautan di atas membutuhkan login ke akun Anda. {{ ucfirst($docName) }} dikirim dalam email terpisah.</p>
@endsection