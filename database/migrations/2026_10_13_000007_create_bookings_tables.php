<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->string('booking_code', 20)->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            // unique: satu hold hanya boleh menghasilkan satu booking (finalisasi idempoten)
            $t->foreignId('hold_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('service_type', 10);
            $t->string('status', 12)->default('Pending'); // Pending|Confirmed|Cancelled|Expired
            $t->string('title');
            $t->string('subtitle')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();

            $t->string('contact_name');
            $t->string('contact_email');
            $t->string('contact_phone', 30)->nullable();

            $t->unsignedBigInteger('base_amount');
            $t->unsignedBigInteger('discount_amount')->default(0);
            $t->unsignedBigInteger('tax_amount')->default(0);
            $t->unsignedBigInteger('fee_amount')->default(0); // biaya metode pembayaran
            $t->unsignedBigInteger('total_amount');
            $t->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $t->string('promo_code', 30)->nullable();
            $t->json('snapshot')->nullable();

            $t->dateTime('confirmed_at')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->string('cancellation_reason')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'status']);
            $t->index('start_date');
        });

        Schema::create('booking_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('item_type', 20); // room | flight_seat | train_seat
            $t->unsignedBigInteger('item_id');
            $t->string('description');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->unsignedBigInteger('total_price');
            $t->json('snapshot')->nullable();
            $t->timestamps();

            $t->index(['item_type', 'item_id']);
        });

        Schema::create('booking_passengers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('full_name');
            $t->string('passenger_type', 10)->default('adult'); // adult | child
            $t->string('id_number', 30)->nullable();
            $t->string('seat_label', 20)->nullable();
            $t->string('room_label', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('discount_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('hold_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('amount');
            $t->string('status', 12)->default('reserved'); // reserved|redeemed|released
            $t->timestamps();

            $t->index(['discount_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('booking_passengers');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};