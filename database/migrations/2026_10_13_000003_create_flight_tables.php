<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $t) {
            $t->id();
            $t->string('code', 3)->unique();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('airports', function (Blueprint $t) {
            $t->id();
            $t->string('iata', 3)->unique();
            $t->string('name');
            $t->string('city', 60);
            $t->string('country', 60);
            $t->timestamps();
        });

        Schema::create('flights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('airline_id')->constrained()->restrictOnDelete();
            $t->string('flight_number', 10)->unique();
            $t->foreignId('origin_airport_id')->constrained('airports')->restrictOnDelete();
            $t->foreignId('destination_airport_id')->constrained('airports')->restrictOnDelete();
            $t->time('departure_time');
            $t->unsignedSmallInteger('duration_minutes');
            $t->boolean('is_direct')->default(true);
            $t->string('connection_note')->nullable();
            $t->boolean('is_international')->default(false);
            $t->unsignedSmallInteger('baggage_kg')->default(20);
            $t->unsignedSmallInteger('cabin_baggage_kg')->default(7);
            $t->unsignedInteger('economy_price');
            $t->unsignedInteger('business_price')->nullable(); // null = tidak ada kelas bisnis
            $t->boolean('is_demo')->default(true);
            $t->boolean('is_published')->default(true);
            $t->timestamps();

            $t->index(['origin_airport_id', 'destination_airport_id']);
        });

        Schema::create('flight_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $t->date('service_date');
            $t->dateTime('departure_at');
            $t->dateTime('arrival_at');
            $t->unsignedInteger('economy_price');
            $t->unsignedInteger('business_price')->nullable();
            $t->string('status', 20)->default('scheduled'); // scheduled|closed|cancelled
            $t->timestamps();

            $t->unique(['flight_id', 'service_date']);
            $t->index('departure_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_schedules');
        Schema::dropIfExists('flights');
        Schema::dropIfExists('airports');
        Schema::dropIfExists('airlines');
    }
};