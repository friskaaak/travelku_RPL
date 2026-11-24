<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Hotel;

class HomeController extends Controller
{
    public function index()
    {
        // Hanya kampanye yang aktif menurut waktu server.
        $campaigns = Campaign::activeNow()->orderBy('ends_at')->get();
        $upcoming = Campaign::upcoming()->orderBy('starts_at')->limit(2)->get();

        // Rekomendasi: tiga hotel dengan rating dan ulasan tertinggi, lengkap dengan harga kamar termurah.
        $featuredHotels = Hotel::query()
            ->where('is_published', true)
            ->withMin('rooms as min_price', 'base_price')
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->limit(3)
            ->get();

        // Daftar kota formulir pencarian.
        $hotelCities = ['Semarang', 'Jakarta', 'Bali', 'Bandung', 'Yogyakarta'];
        $flightCities = [
            'Semarang (SRG)', 'Jakarta (CGK)', 'Bali (DPS)',
            'Tokyo (HND)', 'Seoul (ICN)', 'Sydney (SYD)',
        ];
        $trainCities = ['Semarang', 'Tegal', 'Bandung', 'Jakarta'];

        // [nama, tagline, ikon, warna lama (tidak dipakai lagi oleh desain baru)]
        $destinations = [
            ['Bali', 'Pantai, pura, dan senja', 'bi-sun', ''],
            ['Yogyakarta', 'Budaya dan kuliner', 'bi-bank2', ''],
            ['Bandung', 'Udara sejuk dan belanja', 'bi-cloud-sun', ''],
            ['Jakarta', 'Pusat bisnis dan hiburan', 'bi-buildings', ''],
            ['Semarang', 'Kota lama dan Lawang Sewu', 'bi-building', ''],
        ];

        return view('home', compact(
            'campaigns', 'upcoming', 'featuredHotels', 'hotelCities', 'flightCities', 'trainCities', 'destinations'
        ));
    }
}