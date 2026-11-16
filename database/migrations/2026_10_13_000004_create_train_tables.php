<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('train_stations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 5)->unique();
            $t->string('name');
            $t->string('city', 60)->index();
            $t->timestamps();
        });

        Schema::create('trains', function (Blueprint $t) {
            $t->id();
            $t->string('number', 12)->unique();
            $t->string('name');
            $t->boolean('is_demo')->default(true);
            $t->timestamps();
        });

        Schema::create('train_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('train_id')->constrained()->cascadeOnDelete();
            $t->foreignId('origin_station_id')->constrained('train_stations')->restrictOnDelete();
            $t->foreignId('destination_station_id')->constrained('train_stations')->restrictOnDelete();
            $t->date('service_date');
            $t->dateTime('departure_at');
            $t->dateTime('arrival_at');
            $t->unsignedSmallInteger('duration_minutes');
            $t->string('class', 20); // Ekonomi | Eksekutif
            $t->unsignedInteger('base_fare');
            $t->boolean('discount_eligible')->default(true);
            $t->string('status', 20)->default('scheduled');
            $t->timestamps();

            $t->unique(['train_id', 'service_date']);
            $t->index(['origin_station_id', 'destination_station_id', 'departure_at'], 'train_sched_route_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('train_schedules');
        Schema::dropIfExists('trains');
        Schema::dropIfExists('train_stations');
    }
};