@extends('layouts.app')
@section('title', 'Masuk')

@section('content')
<div class="container py-5">
    <div class="row g-4 justify-content-center">
        <div class="col-lg-5 d-none d-lg-block">
            <div class="tk-auth-promo">
                <span class="tk-eyebrow"><i class="bi bi-stars" aria-hidden="true"></i>Selamat datang kembali</span>
                <h1 class="h2 mt-3">Lanjutkan perjalanan Anda.</h1>
                <p>Masuk untuk memesan, melihat pesanan, dan mengunduh e-tiket Anda.</p>
                <ul>
                    <li><i class="bi bi-shield-check" aria-hidden="true"></i><span>Kamar dan kursi ditahan selama Anda checkout.</span></li>
                    <li><i class="bi bi-receipt" aria-hidden="true"></i><span>Bukti bayar tampil langsung setelah pembayaran terverifikasi.</span></li>
                    <li><i class="bi bi-ticket-perforated" aria-hidden="true"></i><span>E-tiket dan voucher tersimpan di akun Anda.</span></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5 col-md-8">
            <div class="tk-card tk-auth-card">
                <h2 class="h4 mb-1">Masuk</h2>
                <p class="small mb-4">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf
                    <x-field name="email" label="Email" type="email" autocomplete="email" autofocus />
                    <x-field name="password" label="Kata sandi" type="password" autocomplete="current-password" />
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Ingat saya</label></div>
                        <a href="{{ route('password.request') }}" class="small">Lupa kata sandi?</a>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Masuk</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection