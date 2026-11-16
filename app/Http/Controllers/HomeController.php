<?php

namespace App\Http\Controllers;

use App\Models\Campaign;

class HomeController extends Controller
{
    public function index()
    {
        // Hanya kampanye yang aktif menurut waktu server.
        $campaigns = Campaign::activeNow()->orderBy('ends_at')->get();
        $upcoming = Campaign::upcoming()->orderBy('starts_at')->limit(2)->get();

        // Daftar kota sementara; diganti data dari database pada Fase 4.
        $hotelCities = ['Semarang', 'Jakarta', 'Bali', 'Bandung', 'Yogyakarta'];
        $flightCities = [
            'Semarang (SRG)', 'Jakarta (CGK)', 'Bali (DPS)',
            'Tokyo (HND)', 'Seoul (ICN)', 'Sydney (SYD)',
        ];
        $trainCities = ['Semarang', 'Tegal', 'Bandung', 'Jakarta'];

        $destinations = [
            ['Bali', 'Pantai, pura, dan senja', 'bi-sun', 'linear-gradient(135deg,#155EEF,#53B1FD)'],
            ['Yogyakarta', 'Budaya dan kuliner', 'bi-bank2', 'linear-gradient(135deg,#7A5AF8,#B692F6)'],
            ['Bandung', 'Udara sejuk dan belanja', 'bi-cloud-sun', 'linear-gradient(135deg,#067647,#47CD89)'],
            ['Jakarta', 'Pusat bisnis dan hiburan', 'bi-buildings', 'linear-gradient(135deg,#B54708,#FDB022)'],
            ['Semarang', 'Kota lama dan Lawang Sewu', 'bi-building', 'linear-gradient(135deg,#1247B8,#7CD4FD)'],
        ];

        return view('home', compact(
            'campaigns', 'upcoming', 'hotelCities', 'flightCities', 'trainCities', 'destinations'
        ));
    }
}