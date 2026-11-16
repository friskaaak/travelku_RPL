@extends('layouts.app')
@section('title', 'Checkout')

@section('content')
@php
    $d = $hold->details;
    $snap = $hold->pricing_snapshot ?? [];
    $type = $hold->service_type;
    $remaining = max(0, $hold->expires_at->timestamp - now()->timestamp);
    $user = auth()->user();
@endphp

<div class="container py-4">
    <nav aria-label="Jejak halaman" class="small mb-3">
        <a href="{{ route('home') }}">Beranda</a> /
        <a href="{{ route('holds.show', $hold->hold_code) }}">Penahanan</a> / Checkout
    </nav>

    <h1 class="h4 mb-3">Checkout</h1>

    @if ($isDemo)
        <div class="tk-demo-note mb-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ config('payment.demo_labels.banner') }}</div>
    @endif

    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ $type === 'hotel' ? 'Kamar' : 'Kursi' }} Anda ditahan. Setelah Anda menekan Buat pesanan, penahanan diperpanjang sampai batas pembayaran.</span>
        <strong class="fs-5"><span data-countdown data-reload data-remaining="{{ $remaining }}">--:--</span></strong>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali isian Anda:</strong>
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.store', $hold->hold_code) }}" id="checkoutForm" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">

                <section class="tk-card p-4 mb-3" aria-labelledby="sec-contact">
                    <h2 id="sec-contact" class="h5">Data pemesan</h2>
                    <div class="row">
                        <div class="col-md-6"><x-field name="contact_name" label="Nama pemesan" :value="$user->name" maxlength="100" autocomplete="name" /></div>
                        <div class="col-md-6"><x-field name="contact_phone" label="Nomor telepon" type="tel" :value="$user->phone" autocomplete="tel" /></div>
                    </div>
                    <p class="small mb-0"><i class="bi bi-envelope me-1" aria-hidden="true"></i>E-ticket dan receipt akan dikirim ke email akun Anda: <strong>{{ $user->email }}</strong>.</p>
                </section>

                <section class="tk-card p-4 mb-3" aria-labelledby="sec-pax">
                    <h2 id="sec-pax" class="h5">{{ $type === 'hotel' ? 'Data tamu' : 'Data penumpang' }}</h2>
                    @foreach ($slots as $i => $slot)
                        <div class="border rounded-3 p-3 mb-3">
                            <div class="fw-semibold text-dark mb-2">{{ $slot['label'] }}</div>
                            <div class="row g-2">
                                <div class="col-md-{{ $slot['show_type'] ? 5 : 12 }}">
                                    <label for="pax_name_{{ $i }}" class="form-label">Nama sesuai identitas <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input type="text" id="pax_name_{{ $i }}" name="passengers[{{ $i }}][name]" maxlength="100" required
                                           value="{{ old('passengers.'.$i.'.name', $i === 0 ? $user->name : '') }}"
                                           class="form-control @error('passengers.'.$i.'.name') is-invalid @enderror">
                                    @error('passengers.'.$i.'.name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                @if ($slot['show_type'])
                                    <div class="col-md-3">
                                        <label for="pax_type_{{ $i }}" class="form-label">Jenis</label>
                                        <select id="pax_type_{{ $i }}" name="passengers[{{ $i }}][type]" class="form-select">
                                            <option value="adult" @selected(old('passengers.'.$i.'.type', 'adult') === 'adult')>Dewasa</option>
                                            <option value="child" @selected(old('passengers.'.$i.'.type') === 'child')>Anak</option>
                                        </select>
                                    </div>
                                @else
                                    <input type="hidden" name="passengers[{{ $i }}][type]" value="adult">
                                @endif
                                @if ($slot['show_id'])
                                    <div class="col-md-4">
                                        <label for="pax_id_{{ $i }}" class="form-label">No. identitas <span class="fw-normal">(opsional)</span></label>
                                        <input type="text" id="pax_id_{{ $i }}" name="passengers[{{ $i }}][id_number]" maxlength="30" autocomplete="off"
                                               value="{{ old('passengers.'.$i.'.id_number') }}"
                                               class="form-control @error('passengers.'.$i.'.id_number') is-invalid @enderror">
                                        @error('passengers.'.$i.'.id_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="tk-card p-4 mb-3" aria-labelledby="sec-method">
                    <h2 id="sec-method" class="h5">Metode pembayaran</h2>
                    @error('payment_method')<div class="alert alert-danger py-2" role="alert">{{ $message }}</div>@enderror

                    @foreach ($categories as $key => $cat)
                        <fieldset class="mb-3">
                            <legend class="form-label d-flex align-items-center gap-2 mb-1">
                                <i class="bi {{ $cat['icon'] }}" aria-hidden="true"></i>{{ $cat['label'] }}
                            </legend>
                            <p class="small mb-2">{{ $cat['note'] }}</p>
                            <div class="row g-2">
                                @foreach ($cat['methods'] as $code => $m)
                                    <div class="col-md-6">
                                        <label class="tk-method">
                                            <input class="form-check-input" type="radio" name="payment_method" value="{{ $code }}"
                                                   data-fee="{{ $m['fee'] }}" required @checked(old('payment_method') === $code)>
                                            <span class="fw-semibold text-dark">{{ $m['label'] }}</span>
                                            <span class="small ms-auto">{{ $m['fee'] > 0 ? '+'.\App\Support\Money::idr($m['fee']) : 'Gratis' }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <p class="small mb-0">Biaya dihitung ulang oleh server saat pesanan dibuat. @if ($isDemo)Mode demo: tidak ada uang sungguhan yang diproses dan tidak ada data kartu yang diminta.@endif</p>
                </section>

                <section class="tk-card p-4" aria-labelledby="sec-cancel">
                    <h2 id="sec-cancel" class="h5">Ketentuan pembatalan</h2>
                    @if ($type === 'hotel')
                        @if ($d['free_cancellation'])<span class="badge mb-2" style="background:#ECFDF3;color:#067647">Pembatalan gratis</span>@endif
                        <p>{{ $d['cancellation_policy'] }}</p>
                    @else
                        <p>{{ config('travel.cancellation.'.$type) }}</p>
                    @endif
                    <div class="form-check">
                        <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))>
                        <label class="form-check-label" for="terms">Saya telah membaca dan menyetujui ketentuan pembatalan di atas.</label>
                        @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            <aside class="col-lg-5">
                <div class="tk-card p-4 position-sticky" style="top:80px">
                    <x-order-summary :type="$type" :details="$d" :base="$hold->base_amount" :applied="$snap['applied'] ?? []"
                                     :tax-percent="$snap['tax_percent'] ?? 0" :tax="$hold->tax_amount" :total="$hold->total_amount" :dynamic="true" />
                    <button type="submit" class="btn btn-primary w-100 mt-3">Buat pesanan dan lanjut bayar</button>
                    <p class="small mt-2 mb-0">Dengan menekan tombol ini, tagihan berstatus Pending dibuat. Pesanan baru dikonfirmasi setelah pembayaran terverifikasi.</p>
                </div>
            </aside>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/checkout.js') }}"></script>
@endpush