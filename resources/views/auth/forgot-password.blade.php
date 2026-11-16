@extends('layouts.app')
@section('title', 'Lupa Kata Sandi')

@section('content')
<div class="tk-card tk-auth">
    <h1 class="h4 mb-1">Lupa kata sandi</h1>
    <p class="small mb-3">Masukkan email akun Anda. Kami akan membuat tautan untuk mengatur ulang kata sandi.</p>

    @if (app()->environment('local') && config('mail.default') === 'log')
        <div class="tk-demo-note mb-3">
            <i class="bi bi-info-circle me-1"></i>Mode lokal: email <strong>tidak benar-benar terkirim</strong>. Tautan reset ditulis ke <code>storage/logs/laravel.log</code>.
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" />
        <button type="submit" class="btn btn-primary w-100">Buat tautan reset</button>
    </form>
    <p class="small mt-3 mb-0 text-center"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
</div>
@endsection