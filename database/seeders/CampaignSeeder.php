<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Discount;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $all = ['hotel', 'flight', 'train'];

        // Tanggal adalah data demonstrasi. Ubah lewat panel admin pada Fase 10.
        $campaigns = [
            ['promo-liburan-sekolah', 'Promo Liburan Sekolah', 'Hemat 15%', 'LIBURAN15', '2026-10-01 00:00:00', '2026-12-31 23:59:59', 500000,
                'Libur panjang bersama keluarga lebih hemat. Pakai kode promo untuk hotel, pesawat, dan kereta.'],
            ['promo-natal', 'Promo Natal', 'Natal', 'NATAL2026', '2026-12-01 00:00:00', '2026-12-26 23:59:59', 400000,
                'Rayakan Natal di kota tujuan Anda dengan potongan harga untuk semua layanan.'],
            ['promo-tahun-baru', 'Promo Tahun Baru', 'Tahun Baru', 'TAHUNBARU2027', '2026-12-20 00:00:00', '2027-01-05 23:59:59', 400000,
                'Sambut tahun baru dengan perjalanan yang lebih hemat.'],
            ['promo-ramadan', 'Promo Ramadan', 'Ramadan', 'RAMADAN2027', '2027-02-01 00:00:00', '2027-03-09 23:59:59', 400000,
                'Perjalanan nyaman selama bulan Ramadan dengan harga lebih ringan.'],
            ['promo-lebaran', 'Promo Idul Fitri / Lebaran', 'Lebaran', 'LEBARAN2027', '2027-03-01 00:00:00', '2027-03-20 23:59:59', 400000,
                'Mudik dan liburan Lebaran lebih hemat untuk hotel, pesawat, dan kereta.'],
            ['promo-kemerdekaan', 'Promo Hari Kemerdekaan', 'Merdeka', 'MERDEKA2026', '2026-08-10 00:00:00', '2026-08-20 23:59:59', 400000,
                'Promo Agustusan. Periode ini sudah berakhir dan tidak dapat digunakan lagi.'],
        ];

        foreach ($campaigns as [$slug, $name, $badge, $code, $start, $end, $min, $desc]) {
            $campaign = Campaign::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $desc,
                'badge' => $badge,
                'starts_at' => $start,
                'ends_at' => $end,
                'services' => $all,
                'min_purchase' => $min,
                'promo_code' => $code,
                'is_published' => true,
            ]);

            $discount = Discount::where('code', $code)->first();
            if ($discount) {
                $campaign->discounts()->syncWithoutDetaching([$discount->id]);
            }
        }

        $this->command?->info('Kampanye contoh siap: '.count($campaigns).' data.');
    }
}