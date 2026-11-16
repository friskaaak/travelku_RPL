<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $t) {
            $t->id();
            $t->string('hold_code', 20)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('service_type', 10); // hotel | flight | train
            $t->string('status', 12)->default('Active'); // Active|Expired|Consumed|Released
            $t->json('details');            // item terpilih, tanggal, tamu/penumpang
            $t->unsignedSmallInteger('quantity')->default(1);

            // Snapshot harga (dihitung server)
            $t->unsignedBigInteger('base_amount');
            $t->unsignedBigInteger('discount_amount')->default(0);
            $t->unsignedBigInteger('tax_amount')->default(0);
            $t->unsignedBigInteger('total_amount'); // belum termasuk biaya metode pembayaran
            $t->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $t->string('promo_code', 30)->nullable();
            $t->json('pricing_snapshot')->nullable();

            $t->dateTime('expires_at');
            $t->dateTime('consumed_at')->nullable();
            $t->dateTime('released_at')->nullable();
            $t->string('release_reason')->nullable();
            $t->timestamps();

            $t->index(['status', 'expires_at']);
            $t->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};