<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('label', 40)->nullable();          // badge, contoh "Hemat 10%"
            $t->text('description')->nullable();
            $t->text('terms')->nullable();                // syarat dan ketentuan
            $t->string('code', 30)->nullable()->unique(); // null = diskon otomatis
            $t->boolean('is_automatic')->default(false);
            $t->string('type', 12);                       // percentage | fixed
            $t->unsignedInteger('value');                 // persen (0-100) atau rupiah
            $t->json('services');                         // ["hotel","flight","train"]
            $t->json('scope_filter')->nullable();         // contoh {"flight_scope":"international"}
            $t->unsignedBigInteger('min_purchase')->default(0);
            $t->unsignedBigInteger('max_discount')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->unsignedInteger('per_user_limit')->nullable();
            $t->boolean('is_stackable')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->text('description');
            $t->string('banner_image')->nullable();
            $t->string('badge', 40)->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->json('services');
            $t->unsignedBigInteger('min_purchase')->default(0);
            $t->string('promo_code', 30)->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();

            $t->index(['is_published', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_discount', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $t->unique(['campaign_id', 'discount_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_discount');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('discounts');
    }
};