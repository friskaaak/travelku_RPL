<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Hotel;

class CampaignController extends Controller
{
    public function index()
    {
        // Hanya yang aktif menurut waktu server. Kampanye berakhir tidak ditampilkan di daftar.
        $active = Campaign::activeNow()->with('discounts')->orderBy('ends_at')->get();
        $upcoming = Campaign::upcoming()->orderBy('starts_at')->get();

        return view('campaigns.index', compact('active', 'upcoming'));
    }

    public function show(Campaign $campaign)
    {
        abort_unless($campaign->is_published, 404);
        $campaign->load('discounts');

        $status = $campaign->status();
        $hotels = collect();

        // Daftar produk hanya untuk kampanye yang SEDANG aktif.
        if ($status === 'active' && in_array('hotel', $campaign->services ?? [], true)) {
            $hotels = Hotel::with('rooms')->where('is_published', true)->orderBy('city')->get();
        }

        return view('campaigns.show', [
            'campaign' => $campaign,
            'status' => $status,
            'discount' => $campaign->discounts->first(),
            'hotels' => $hotels,
        ]);
    }
}