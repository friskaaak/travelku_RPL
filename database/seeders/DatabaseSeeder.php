<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan penting: kampanye membutuhkan diskon.
        $this->call([
            UserSeeder::class,
            HotelSeeder::class,
            FlightSeeder::class,
            TrainSeeder::class,
            DiscountSeeder::class,
            CampaignSeeder::class,
        ]);
    }
}