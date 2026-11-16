<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('city', 60)->index();
            $t->string('address');
            $t->text('description');
            $t->unsignedTinyInteger('star_rating')->default(3);
            $t->decimal('rating', 2, 1)->default(0);
            $t->unsignedInteger('review_count')->default(0);
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->json('images')->nullable();
            $t->json('amenities')->nullable();
            $t->time('check_in_time')->default('14:00:00');
            $t->time('check_out_time')->default('12:00:00');
            $t->boolean('free_cancellation')->default(false);
            $t->unsignedSmallInteger('cancellation_hours')->default(24); // gratis batal sampai X jam sebelum check-in
            $t->text('cancellation_policy')->nullable();
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->string('room_number', 10);
            $t->string('room_type', 30); // Standard, Superior, Deluxe, Family
            $t->unsignedTinyInteger('capacity');
            $t->string('bed_type', 40)->nullable();
            $t->unsignedInteger('base_price'); // per malam, rupiah
            // active = boleh dijual; maintenance = dikeluarkan sementara.
            // BUKAN penanda terisi/kosong. Keterisian dicek di room_nights.
            $t->string('status', 20)->default('active');
            $t->timestamps();

            $t->unique(['hotel_id', 'room_number']);
            $t->index(['hotel_id', 'room_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('hotels');
    }
};