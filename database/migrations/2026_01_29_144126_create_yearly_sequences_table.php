<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('yearly_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique(); // GLOBAL: mỗi năm chỉ có 1 record
            $table->integer('current_seq')->default(0); // Sequence chung cho TẤT CẢ phòng ban
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yearly_sequences');
    }
};
