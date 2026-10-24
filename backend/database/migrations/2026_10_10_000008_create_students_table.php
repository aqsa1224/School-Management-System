<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_no')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('gender', 10);                    // male / female / other
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('guardian_name');                 // Parent module nahi hai, isliye yahin
            $table->string('guardian_phone', 30);
            $table->string('photo')->nullable();

            // Class aur Section ab asal links hain (text nahi)
            // restrictOnDelete = jis class/section me students hon wo delete nahi hogi
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();

            $table->date('admission_date')->nullable();
            $table->string('status', 20)->default('active'); // active / left / passed_out
            $table->timestamps();

            $table->index(['school_class_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
