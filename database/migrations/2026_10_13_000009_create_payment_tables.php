<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('payment_reference', 30)->unique();
            $t->string('invoice_number', 30)->unique(); // nomor tagihan
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();

            $t->string('method_code', 30);      // bca_va, gopay, qris, card, alfamart, ...
            $t->string('method_category', 20);  // bank_transfer|ewallet|qris|card|store
            $t->string('provider', 20)->default('demo'); // demo|midtrans|xendit
            $t->string('provider_reference')->nullable()->index();
            $t->string('payment_code', 60)->nullable();  // no. VA / kode gerai / referensi dompet
            $t->text('qr_payload')->nullable();          // isi QR (simulasi pada mode demo)
            $t->json('instructions')->nullable();

            $t->char('currency', 3)->default('IDR');
            $t->unsignedBigInteger('amount');            // total tagihan termasuk biaya
            $t->unsignedBigInteger('fee_amount')->default(0);
            $t->string('status', 30)->default('Pending')->index();
            $t->boolean('is_simulated')->default(false);
            $t->string('idempotency_key', 80)->nullable()->unique();

            $t->dateTime('expires_at');
            $t->dateTime('verified_at')->nullable();
            $t->dateTime('paid_at')->nullable();
            $t->dateTime('failed_at')->nullable();
            $t->string('failure_reason')->nullable();
            $t->timestamps();

            $t->index(['booking_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->string('event_type', 40);
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30)->nullable();
            $t->string('source', 20); // system|webhook|demo_simulation|admin
            $t->string('provider_event_id')->nullable()->unique(); // cegah callback ganda
            $t->json('payload')->nullable();
            $t->timestamps();
        });

        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->string('receipt_number', 30)->unique();
            $t->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $t->json('snapshot'); // salinan tak berubah dari data transaksi
            $t->dateTime('issued_at');
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_number', 30)->unique();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('booking_passenger_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ticket_type', 10); // voucher | flight | train
            $t->string('verification_code', 40)->unique(); // isi QR / referensi verifikasi
            $t->json('snapshot');
            $t->dateTime('issued_at');
            $t->timestamps();

            $t->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};