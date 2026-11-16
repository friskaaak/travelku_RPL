@extends('layouts.app')
@section('title', 'Verifikasi Tiket')

@section('content')
<div class="container py-5" style="max-width:560px">
    <div class="tk-card p-4 text-center">
        @if (! $ticket)
            <i class="bi bi-question-circle-fill" style="font-size:3rem;color:#B42318" aria-hidden="true"></i>
            <h1 class="h4 mt-2">Kode tidak dikenal</h1>
            <p class="mb-0">Kode verifikasi ini tidak ditemukan di sistem.</p>
        @else
            @php
                $s = $ticket->snapshot;
                $type = $ticket->ticket_type;
                $label = ['voucher' => 'Voucher hotel', 'flight' => 'E-ticket pesawat', 'train' => 'E-ticket kereta'][$type] ?? 'Tiket';
                $title = $type === 'voucher' ? ($s['hotel']['title'] ?? '-') : ($type === 'flight'
                    ? ($s['trip']['airline'].' '.$s['trip']['flight_number'])
                    : ($s['trip']['train_name'].' ('.$s['trip']['train_number'].')'));
                $date = $type === 'voucher'
                    ? \Carbon\Carbon::parse($s['hotel']['check_in'])->translatedFormat('d F Y')
                    : \Carbon\Carbon::parse($s['trip']['departure_at'])->translatedFormat('d F Y, H:i').' WIB';
                $name = $type === 'voucher'
                    ? \App\Support\Mask::name($s['guests'][0]['name'] ?? '-')
                    : \App\Support\Mask::name($s['passenger']['name']);
            @endphp

            @if ($valid)
                <i class="bi bi-check-circle-fill" style="font-size:3rem;color:#067647" aria-hidden="true"></i>
                <h1 class="h4 mt-2" style="color:#067647">Tiket valid</h1>
            @else
                <i class="bi bi-x-circle-fill" style="font-size:3rem;color:#B42318" aria-hidden="true"></i>
                <h1 class="h4 mt-2" style="color:#B42318">Tiket tidak berlaku</h1>
                <p>Pesanan terkait tidak lagi terkonfirmasi.</p>
            @endif

            <dl class="row text-start mt-3 mb-0">
                <dt class="col-5">Nomor tiket</dt><dd class="col-7">{{ $ticket->ticket_number }}</dd>
                <dt class="col-5">Jenis</dt><dd class="col-7">{{ $label }}</dd>
                <dt class="col-5">Produk</dt><dd class="col-7">{{ $title }}</dd>
                <dt class="col-5">Tanggal</dt><dd class="col-7">{{ $date }}</dd>
                <dt class="col-5">Nama</dt><dd class="col-7">{{ $name }}</dd>
            </dl>
        @endif
    </div>
    <p class="small mt-3 text-center">Halaman verifikasi demonstrasi. Bukan sistem check-in resmi maskapai, kereta, atau hotel.</p>
</div>
@endsection