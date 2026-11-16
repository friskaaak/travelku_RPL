@extends('layouts.app')
@section('title', 'Atur Ulang Kata Sandi')

@section('content')
<div class="tk-card tk-auth">
    <h1 class="h4 mb-3">Atur ulang kata sandi</h1>
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" />
        <x-field name="password" label="Kata sandi baru" type="password" autocomplete="new-password" help="Minimal 8 karakter, mengandung huruf dan angka." />
        <x-field name="password_confirmation" label="Ulangi kata sandi baru" type="password" autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">Simpan kata sandi</button>
    </form>
</div>
@endsection