@extends('emails.layout')
@section('subject', ucfirst($docName).' '.$booking->booking_code)

@section('body')
@php
    $isHotel = $booking->service_type === 'hotel';
@endphp

<p style="margin:0 0 12px;">Halo <strong style="color:#101828;">{{ $s['customer']['name'] }}</strong>,</p>
<p style="margin:0 0 12px;">
    @if ($isHotel)
        Voucher hotel untuk pesanan <strong style="color:#101828;">{{ $booking->booking_code }}</strong> terlampir dalam bentuk PDF. Tunjukkan voucher ini saat check-in.
    @else
        E-ticket untuk pesanan <strong style="color:#101828;">{{ $booking->booking_code }}</strong> terlampir dalam bentuk PDF ({{ $tickets->count() }} tiket, satu per penumpang).
    @endif
</p>

@if ($isSimulated)
    <p style="margin:0 0 12px;padding:10px;background:#FFFAEB;border:1px solid #FEDF89;color:#B54708;border-radius:8px;">Dokumen ini berasal dari pembayaran <strong>simulasi</strong>. Ini tiket <strong>demonstrasi</strong>, bukan tiket resmi, dan tidak dapat dipakai untuk bepergian.</p>
@endif

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

<h2 style="font-size:15px;color:#101828;margin:20px 0 8px;">{{ $isHotel ? 'Voucher' : 'Tiket' }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
    @foreach ($tickets as $t)
        <tr>
            <td style="padding:5px 0;border-bottom:1px solid #F2F4F7;color:#101828;">
                {{ $t->ticket_number }}
                @if ($t->ticket_type !== 'voucher')
                    <br><span style="color:#667085;">{{ $t->snapshot['passenger']['name'] ?? '' }}, kursi {{ $t->snapshot['seat']['number'] ?? '-' }}</span>
                @endif
            </td>
        </tr>
    @endforeach
</table>

<p style="margin:20px 0 8px;">
    <a href="{{ $url }}" style="display:inline-block;background:#155EEF;color:#FFFFFF;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">Lihat pesanan dan {{ $docName }}</a>
</p>
<p style="margin:0;font-size:12px;color:#667085;">Tautan di atas membutuhkan login ke akun Anda. Bukti pembayaran dikirim dalam email terpisah.</p>
@endsection