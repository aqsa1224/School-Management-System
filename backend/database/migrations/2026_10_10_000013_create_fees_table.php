<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ek "fee" = ek voucher (jo student ko dena hai)
    public function up(): void
    {
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();    // auto generate hoga (Fees module me)
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->date('for_month')->nullable();     // kis mahine ki fee (mahine ki 1 tareekh)
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 10)->default('unpaid'); // unpaid / partial / paid
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
