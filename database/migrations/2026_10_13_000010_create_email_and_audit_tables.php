<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mail_type', 10);   // ticket | receipt
            $t->string('recipient');
            $t->string('status', 10)->default('queued'); // queued|sending|sent|failed
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('dedupe_key', 80)->unique(); // cegah catatan/email ganda
            $t->dateTime('last_attempt_at')->nullable();
            $t->dateTime('sent_at')->nullable();   // = diterima server SMTP, bukan "sudah dibaca"
            $t->dateTime('failed_at')->nullable();
            $t->text('error_summary')->nullable();
            $t->timestamps();

            $t->index('status');
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 60)->index();
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('metadata')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();

            $t->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('email_deliveries');
    }
};