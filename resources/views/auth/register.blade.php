@extends('layouts.app')
@section('title', 'Daftar')

@section('content')
<div class="tk-card tk-auth">
    <h1 class="h4 mb-1">Buat akun</h1>
    <p class="small mb-4">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <x-field name="name" label="Nama lengkap" autocomplete="name" />
        <x-field name="email" label="Email" type="email" autocomplete="email" help="E-tiket dan receipt akan dikirim ke email ini." />
        <x-field name="phone" label="Nomor telepon" type="tel" :required="false" autocomplete="tel" />
        <x-field name="password" label="Kata sandi" type="password" autocomplete="new-password" help="Minimal 8 karakter, mengandung huruf dan angka." />
        <x-field name="password_confirmation" label="Ulangi kata sandi" type="password" autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">Daftar</button>
    </form>
</div>
@endsection