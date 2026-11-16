@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="container py-5">
    <div class="tk-empty">
        <i class="bi bi-tools fs-1 text-secondary"></i>
        <h1 class="h4 mt-2">{{ $title }}</h1>
        <p class="mb-3">Halaman ini dibangun pada <strong>{{ $phase }}</strong>.</p>
        <a href="{{ route('home') }}" class="btn btn-primary">Kembali ke beranda</a>
    </div>
</div>
@endsection