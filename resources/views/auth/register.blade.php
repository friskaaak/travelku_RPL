@extends('layouts.app')
@section('title', 'Daftar')

@section('content')
<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <div class="col-lg-5 d-none d-lg-block">
            <div class="tk-auth-promo">
                <span class="tk-eyebrow"><i class="bi bi-person-plus" aria-hidden="true"></i>Akun gratis</span>
                <h1 class="h2 mt-3">Mulai pesan dalam satu menit.</h1>
                <p>Satu akun untuk hotel, pesawat, dan kereta.</p>
                <ul>
                    <li><i class="bi bi-tags" aria-hidden="true"></i><span>Harga awal, diskon, dan total ditampilkan jelas.</span></li>
                    <li><i class="bi bi-envelope" aria-hidden="true"></i><span>E-tiket dan receipt dikirim ke email akun Anda.</span></li>
                    <li><i class="bi bi-clock-history" aria-hidden="true"></i><span>Riwayat pesanan tersimpan rapi.</span></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5 col-md-8">
            <div class="tk-card tk-auth-card">
                <h2 class="h4 mb-1">Buat akun</h2>
                <p class="small mb-4">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
                <form method="POST" action="{{ route('register.store') }}" novalidate>
                    @csrf
                    <x-field name="name" label="Nama lengkap" autocomplete="name" />
                    <x-field name="email" label="Email" type="email" autocomplete="email" help="E-tiket dan receipt akan dikirim ke email ini." />
                    <x-field name="phone" label="Nomor telepon" type="tel" :required="false" autocomplete="tel" />
                    <x-field name="password" label="Kata sandi" type="password" autocomplete="new-password" help="Minimal 8 karakter, mengandung huruf dan angka." />
                    <x-field name="password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" />
                    <button type="submit" class="btn btn-primary btn-lg w-100">Daftar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection