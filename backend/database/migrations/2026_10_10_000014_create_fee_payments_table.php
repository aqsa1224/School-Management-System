<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ek "payment" = ek receipt (jo paisa asal me wasool hua)
    // Isse partial payment bhi chalti hai
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();    // auto generate hoga (Fees module me)
            $table->foreignId('fee_id')->constrained('fees')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('method', 20)->default('cash'); // cash / bank / online
            $table->string('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('payment_date'); // daily/monthly collection report ke liye
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
