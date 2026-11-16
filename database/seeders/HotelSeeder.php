<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Setiap tipe punya 5 kamar, 4 tipe = 20 kamar per hotel.
     * [tipe, lantai, kapasitas, tempat tidur, persen harga terhadap Standard]
     */
    private const TYPES = [
        ['Standard', 1, 2, '1 Double atau 2 Twin', 100],
        ['Superior', 2, 2, '1 Queen', 125],
        ['Deluxe', 3, 3, '1 King + sofa bed', 160],
        ['Family', 4, 4, '2 Queen', 200],
    ];

    public function run(): void
    {
        $data = $this->hotels();

        foreach ($data as $h) {
            $policy = $h['free_cancel']
                ? "Pembatalan gratis hingga {$h['cancel_hours']} jam sebelum waktu check-in. Pembatalan setelah batas waktu itu, atau tidak datang (no-show), dikenai biaya sebesar tarif satu malam."
                : 'Tarif ini tidak dapat dibatalkan tanpa biaya. Pembatalan dikenai biaya sebesar tarif satu malam, dan tidak datang (no-show) dikenai biaya penuh.';

            $hotel = Hotel::firstOrCreate(['slug' => $h['slug']], [
                'name' => $h['name'],
                'city' => $h['city'],
                'address' => $h['address'],
                'description' => $h['description'],
                'star_rating' => $h['star'],
                'rating' => $h['rating'],
                'review_count' => $h['reviews'],
                'latitude' => $h['lat'],
                'longitude' => $h['lng'],
                'images' => $this->gallery($h['from'], $h['to']),
                'amenities' => $h['amenities'],
                'check_in_time' => '14:00:00',
                'check_out_time' => '12:00:00',
                'free_cancellation' => $h['free_cancel'],
                'cancellation_hours' => $h['cancel_hours'],
                'cancellation_policy' => $policy,
                'is_published' => true,
            ]);

            foreach (self::TYPES as [$type, $floor, $capacity, $bed, $pct]) {
                // Integer murni: harga dibulatkan ke ribuan terdekat tanpa float.
                $raw = intdiv($h['base'] * $pct, 100);
                $price = intdiv($raw + 500, 1000) * 1000;

                for ($i = 1; $i <= 5; $i++) {
                    Room::firstOrCreate(
                        ['hotel_id' => $hotel->id, 'room_number' => (string) ($floor * 100 + $i)],
                        [
                            'room_type' => $type,
                            'capacity' => $capacity,
                            'bed_type' => $bed,
                            'base_price' => $price,
                            'status' => 'active',
                        ]
                    );
                }
            }
        }

        $this->verify($data);
    }

    /** Pastikan tiap hotel awal memiliki tepat 20 kamar bawaan seeder. */
    private function verify(array $data): void
    {
        $numbers = [];
        foreach (self::TYPES as [, $floor]) {
            for ($i = 1; $i <= 5; $i++) {
                $numbers[] = (string) ($floor * 100 + $i);
            }
        }

        foreach ($data as $h) {
            $count = Room::whereIn('room_number', $numbers)
                ->whereHas('hotel', fn ($q) => $q->where('slug', $h['slug']))
                ->count();

            if ($count !== 20) {
                throw new \RuntimeException("Hotel {$h['slug']} memiliki {$count} kamar bawaan, seharusnya 20.");
            }
        }

        $this->command?->info('Terverifikasi: '.count($data).' hotel awal, masing-masing 20 kamar.');
    }

    /** Ubin galeri. Untuk foto asli, ganti item dengan ['label'=>..., 'src'=>'images/hotels/nama.jpg']. */
    private function gallery(string $from, string $to): array
    {
        return [
            ['label' => 'Lobi', 'icon' => 'bi-building', 'from' => $from, 'to' => $to],
            ['label' => 'Kamar Deluxe', 'icon' => 'bi-door-open', 'from' => $to, 'to' => $from],
            ['label' => 'Kolam renang', 'icon' => 'bi-water', 'from' => $from, 'to' => '#101828'],
            ['label' => 'Restoran', 'icon' => 'bi-cup-hot', 'from' => '#101828', 'to' => $to],
        ];
    }

    private function hotels(): array
    {
        return [
            [
                'slug' => 'hotel-tawang-heritage-semarang', 'name' => 'Hotel Tawang Heritage Semarang', 'city' => 'Semarang',
                'address' => 'Jl. Letjen Suprapto No. 18, Kota Lama, Semarang',
                'description' => 'Hotel bergaya kolonial modern di kawasan Kota Lama. Berjalan kaki ke Gereja Blenduk dan Stasiun Tawang. Kamar luas dengan sentuhan interior kayu jati, restoran menyajikan masakan khas Semarang.',
                'star' => 4, 'rating' => 4.5, 'reviews' => 1284, 'lat' => -6.9681, 'lng' => 110.4283, 'base' => 420000,
                'amenities' => ['Wi-Fi gratis', 'Sarapan', 'AC', 'Restoran', 'Parkir gratis', 'Kolam renang'],
                'free_cancel' => true, 'cancel_hours' => 48, 'from' => '#155EEF', 'to' => '#53B1FD',
            ],
            [
                'slug' => 'menteng-grand-residence-jakarta', 'name' => 'Menteng Grand Residence Jakarta', 'city' => 'Jakarta',
                'address' => 'Jl. HOS Cokroaminoto No. 42, Menteng, Jakarta Pusat',
                'description' => 'Hotel bintang lima di kawasan Menteng yang tenang namun dekat ke pusat bisnis. Tersedia spa, pusat kebugaran, dan kolam renang atap dengan pemandangan kota.',
                'star' => 5, 'rating' => 4.6, 'reviews' => 2310, 'lat' => -6.1963, 'lng' => 106.8310, 'base' => 750000,
                'amenities' => ['Wi-Fi gratis', 'Sarapan', 'AC', 'Restoran', 'Kolam renang', 'Spa', 'Pusat kebugaran', 'Antar-jemput bandara'],
                'free_cancel' => true, 'cancel_hours' => 24, 'from' => '#7A5AF8', 'to' => '#B692F6',
            ],
            [
                'slug' => 'sanur-bayu-beach-resort', 'name' => 'Sanur Bayu Beach Resort', 'city' => 'Bali',
                'address' => 'Jl. Danau Tamblingan No. 77, Sanur, Denpasar, Bali',
                'description' => 'Resor tepi pantai Sanur dengan taman tropis dan kolam renang luas. Cocok untuk keluarga, dengan area bermain anak dan akses langsung ke pantai berpasir.',
                'star' => 5, 'rating' => 4.7, 'reviews' => 3105, 'lat' => -8.6905, 'lng' => 115.2630, 'base' => 980000,
                'amenities' => ['Wi-Fi gratis', 'Sarapan', 'AC', 'Restoran', 'Kolam renang', 'Spa', 'Parkir gratis', 'Antar-jemput bandara'],
                'free_cancel' => false, 'cancel_hours' => 72, 'from' => '#067647', 'to' => '#47CD89',
            ],
            [
                'slug' => 'dago-pakar-hill-hotel', 'name' => 'Dago Pakar Hill Hotel', 'city' => 'Bandung',
                'address' => 'Jl. Dago Pakar Timur No. 9, Dago Atas, Bandung',
                'description' => 'Hotel di perbukitan Dago dengan udara sejuk dan pemandangan kota Bandung dari balkon. Dekat ke taman hutan raya dan kafe-kafe Dago.',
                'star' => 4, 'rating' => 4.4, 'reviews' => 987, 'lat' => -6.8560, 'lng' => 107.6310, 'base' => 550000,
                'amenities' => ['Wi-Fi gratis', 'Sarapan', 'AC', 'Restoran', 'Parkir gratis', 'Pusat kebugaran'],
                'free_cancel' => true, 'cancel_hours' => 24, 'from' => '#B54708', 'to' => '#FDB022',
            ],
            [
                'slug' => 'malioboro-kraton-boutique-hotel', 'name' => 'Malioboro Kraton Boutique Hotel', 'city' => 'Yogyakarta',
                'address' => 'Jl. Pangeran Mangkubumi No. 31, Gedongtengen, Yogyakarta',
                'description' => 'Hotel butik dengan nuansa budaya Jawa, beberapa menit dari Tugu dan Malioboro. Kamar berhias batik, sarapan menyajikan menu tradisional Yogyakarta.',
                'star' => 4, 'rating' => 4.5, 'reviews' => 1654, 'lat' => -7.7829, 'lng' => 110.3670, 'base' => 480000,
                'amenities' => ['Wi-Fi gratis', 'Sarapan', 'AC', 'Restoran', 'Kolam renang', 'Antar-jemput bandara'],
                'free_cancel' => true, 'cancel_hours' => 48, 'from' => '#1247B8', 'to' => '#7CD4FD',
            ],
        ];
    }
}