@extends('layouts.app')
@section('title', 'Masuk')

@section('content')
<div class="tk-card tk-auth">
    <h1 class="h4 mb-1">Masuk</h1>
    <p class="small mb-4">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" autofocus />
        <x-field name="password" label="Kata sandi" type="password" autocomplete="current-password" />

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Lupa kata sandi?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>
</div>
@endsection