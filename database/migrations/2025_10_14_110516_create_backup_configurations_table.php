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
        Schema::create('backup_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên cấu hình backup');
            $table->boolean('is_active')->default(true)->comment('Trạng thái hoạt động');
            $table->json('schedule')->comment('Cấu hình lịch trình (cron expression)');
            $table->json('backup_options')->comment('Tùy chọn backup (database, files, etc.)');
            $table->boolean('google_drive_enabled')->default(false)->comment('Upload lên Google Drive');
            $table->json('google_drive_config')->nullable()->comment('Cấu hình Google Drive');
            $table->boolean('email_notification')->default(true)->comment('Gửi email thông báo');
            $table->json('notification_emails')->comment('Danh sách email nhận thông báo');
            $table->integer('retention_days')->default(30)->comment('Số ngày giữ lại backup');
            $table->timestamp('last_run_at')->nullable()->comment('Lần chạy cuối cùng');
            $table->timestamp('next_run_at')->nullable()->comment('Lần chạy tiếp theo');
            $table->foreignId('created_by')->constrained('users')->comment('Người tạo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_configurations');
    }
};
