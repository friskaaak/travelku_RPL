<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu kamar fisik pada satu malam.
        // Unique (room_id, night) = penjaga utama anti double-booking.
        Schema::create('room_nights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->cascadeOnDelete();
            $t->date('night');
            $t->foreignId('hold_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 12); // held | confirmed
            $t->dateTime('expires_at')->nullable(); // diisi saat held
            $t->timestamps();

            $t->unique(['room_id', 'night']);
            $t->index(['status', 'expires_at']);
        });

        Schema::create('flight_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flight_schedule_id')->constrained()->cascadeOnDelete();
            $t->string('seat_number', 5);
            $t->string('cabin_class', 10); // Economy | Business
            $t->unsignedInteger('price');
            $t->string('status', 12)->default('available'); // available|held|confirmed
            $t->foreignId('hold_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('held_until')->nullable();
            $t->timestamps();

            $t->unique(['flight_schedule_id', 'seat_number']);
            $t->index(['flight_schedule_id', 'status']);
        });

        Schema::create('train_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('train_schedule_id')->constrained()->cascadeOnDelete();
            $t->string('seat_number', 10);
            $t->string('seat_class', 20);
            $t->unsignedInteger('price');
            $t->string('status', 12)->default('available');
            $t->foreignId('hold_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('held_until')->nullable();
            $t->timestamps();

            $t->unique(['train_schedule_id', 'seat_number']);
            $t->index(['train_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('train_seats');
        Schema::dropIfExists('flight_seats');
        Schema::dropIfExists('room_nights');
    }
};