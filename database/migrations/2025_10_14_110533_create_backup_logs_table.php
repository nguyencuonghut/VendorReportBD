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
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_configuration_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration')->nullable()->comment('Thời gian thực hiện (giây)');
            $table->string('file_name')->nullable()->comment('Tên file backup');
            $table->bigInteger('file_size')->nullable()->comment('Kích thước file (bytes)');
            $table->string('google_drive_file_id')->nullable()->comment('ID file trên Google Drive');
            $table->text('error_message')->nullable()->comment('Thông báo lỗi nếu có');
            $table->json('backup_details')->nullable()->comment('Chi tiết quá trình backup');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
