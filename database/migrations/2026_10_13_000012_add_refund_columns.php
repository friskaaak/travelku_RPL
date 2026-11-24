<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->unsignedBigInteger('penalty_amount')->default(0)->after('total_amount');
            $t->unsignedBigInteger('refund_amount')->default(0)->after('penalty_amount');
            $t->string('refund_status', 12)->nullable()->after('refund_amount'); // none|simulated|pending
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->unsignedBigInteger('refunded_amount')->default(0)->after('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['penalty_amount', 'refund_amount', 'refund_status']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('refunded_amount'));
    }
};