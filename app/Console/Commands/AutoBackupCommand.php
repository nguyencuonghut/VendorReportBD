<?php

namespace App\Console\Commands;

use App\Services\AutoBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run-scheduled {--config-id= : ID của configuration cụ thể để chạy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chạy backup tự động theo lịch trình đã cấu hình';

    private AutoBackupService $backupService;

    public function __construct(AutoBackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Bắt đầu kiểm tra backup tự động...');
        Log::info('Auto backup command started');

        try {
            $configId = $this->option('config-id');

            if ($configId) {
                // Chạy backup cho configuration cụ thể
                $config = \App\Models\BackupConfiguration::find($configId);
                if (!$config) {
                    $this->error("❌ Không tìm thấy backup configuration với ID: {$configId}");
                    return Command::FAILURE;
                }

                if (!$config->is_active) {
                    $this->warn("⚠️  Backup configuration '{$config->name}' đã bị vô hiệu hóa");
                    return Command::SUCCESS;
                }

                $this->info("📦 Đang chạy backup cho: {$config->name}");
                $log = $this->backupService->executeBackup($config);

                if ($log->status === 'success') {
                    $this->info("✅ Backup '{$config->name}' hoàn thành thành công");
                    $this->line("   📁 File: {$log->file_name}");
                    $this->line("   📏 Kích thước: {$log->formatted_file_size}");
                    $this->line("   ⏱️  Thời gian: {$log->formatted_duration}");
                    if ($log->google_drive_file_id) {
                        $this->line("   ☁️  Google Drive: Đã upload");
                    }
                } else {
                    $this->error("❌ Backup '{$config->name}' thất bại: {$log->error_message}");
                    return Command::FAILURE;
                }
            } else {
                // Chạy tất cả backup theo lịch trình
                $scheduledConfigs = $this->backupService->getScheduledConfigurations();

                if ($scheduledConfigs->isEmpty()) {
                    $this->info('✨ Không có backup nào cần chạy lúc này');
                    return Command::SUCCESS;
                }

                $this->info("📋 Tìm thấy {$scheduledConfigs->count()} backup cần chạy:");

                foreach ($scheduledConfigs as $config) {
                    $this->line("   • {$config->name}");
                }

                $successCount = 0;
                $failCount = 0;

                foreach ($scheduledConfigs as $config) {
                    $this->info("📦 Đang chạy backup: {$config->name}");

                    $log = $this->backupService->executeBackup($config);

                    if ($log->status === 'success') {
                        $this->info("  ✅ Thành công - {$log->formatted_file_size} - {$log->formatted_duration}");
                        $successCount++;
                    } else {
                        $this->error("  ❌ Thất bại: {$log->error_message}");
                        $failCount++;
                    }
                }

                $this->info("🎉 Hoàn thành! Thành công: {$successCount}, Thất bại: {$failCount}");
            }

            Log::info('Auto backup command completed successfully', [
                'config_id' => $configId,
                'success_count' => $successCount ?? 1,
                'fail_count' => $failCount ?? 0
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Lỗi trong quá trình backup: ' . $e->getMessage());
            Log::error('Auto backup command failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
