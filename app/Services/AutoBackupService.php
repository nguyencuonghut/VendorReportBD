<?php

namespace App\Services;

use App\Models\BackupConfiguration;
use App\Models\BackupLog;
use App\Notifications\BackupCompleted;
use App\Notifications\BackupFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use ZipArchive;

class AutoBackupService
{
    private GoogleDriveService $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Thực hiện backup theo configuration
     */
    public function executeBackup(BackupConfiguration $config): BackupLog
    {
        $log = BackupLog::create([
            'backup_configuration_id' => $config->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            Log::info("Starting auto backup", ['config_id' => $config->id, 'log_id' => $log->id]);

            $timestamp = now()->format('Y-m-d_H-i-s');
            Log::info("Timestamp for backup: $timestamp", ['log_id' => $log->id]);
            $backupName = "auto_backup_{$config->id}_{$timestamp}";
            $backupPath = storage_path('app/backups/' . $backupName);
            $zipPath = storage_path('app/backups/' . $backupName . '.zip');

            // Tạo thư mục backup
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $backupDetails = [];

            // 1. Backup Database nếu được cấu hình
            if ($config->backup_options['database'] ?? false) {
                Log::info("Backing up database for auto backup", ['log_id' => $log->id]);
                $this->backupDatabase($backupPath);
                $backupDetails['database'] = true;
            }

            // 2. Backup .env file nếu được cấu hình
            if ($config->backup_options['env_file'] ?? false) {
                Log::info("Backing up .env file for auto backup", ['log_id' => $log->id]);
                $this->backupEnvFile($backupPath);
                $backupDetails['env_file'] = true;
            }

            // 3. Backup uploaded files nếu được cấu hình
            if ($config->backup_options['uploaded_files'] ?? false) {
                Log::info("Backing up uploaded files for auto backup", ['log_id' => $log->id]);
                $this->backupUploadedFiles($backupPath);
                $backupDetails['uploaded_files'] = true;
            }

            // 4. Tạo file zip
            Log::info("Creating zip archive for auto backup", ['log_id' => $log->id]);
            $this->createZip($backupPath, $zipPath);

            $fileSize = filesize($zipPath);
            $backupDetails['file_size'] = $fileSize;
            $backupDetails['zip_created'] = true;

            // 5. Upload lên Google Drive nếu được cấu hình
            $googleDriveFileId = null;
            if ($config->google_drive_enabled && $config->google_drive_config) {
                Log::info("Uploading to Google Drive for auto backup", ['log_id' => $log->id]);

                if ($this->googleDriveService->authenticate($config->google_drive_config)) {
                    $googleDriveFileId = $this->googleDriveService->uploadFile(
                        $zipPath,
                        $backupName . '.zip',
                        $config->google_drive_config['folder_id'] ?? null
                    );
                    $backupDetails['google_drive_uploaded'] = $googleDriveFileId !== null;
                    $backupDetails['google_drive_file_id'] = $googleDriveFileId;
                } else {
                    Log::warning("Failed to authenticate with Google Drive", ['log_id' => $log->id]);
                    $backupDetails['google_drive_uploaded'] = false;
                    $backupDetails['google_drive_error'] = 'Authentication failed';
                }
            }

            // 6. Dọn dẹp files tạm
            $this->deleteDirectory($backupPath);
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            // 7. Cập nhật log
            $completedAt = now();
            $duration = $log->started_at->diffInSeconds($completedAt, false); // false = get absolute value

            $log->update([
                'status' => 'success',
                'completed_at' => $completedAt,
                'duration' => $duration,
                'file_name' => $backupName . '.zip',
                'file_size' => $fileSize,
                'google_drive_file_id' => $googleDriveFileId,
                'backup_details' => $backupDetails
            ]);

            // 8. Cập nhật configuration
            $config->update([
                'last_run_at' => now()
            ]);
            $config->updateNextRunTime();

            // 9. Gửi email thông báo thành công
            if ($config->email_notification) {
                $this->sendSuccessNotification($config, $log);
            }

            // 10. Dọn dẹp backup cũ trên Google Drive
            if ($config->google_drive_enabled && $googleDriveFileId) {
                $this->cleanupOldBackups($config);
            }

            Log::info("Auto backup completed successfully", [
                'config_id' => $config->id,
                'log_id' => $log->id,
                'duration' => $duration,
                'file_size' => $fileSize
            ]);

            return $log;

        } catch (\Exception $e) {
            Log::error("Auto backup failed", [
                'config_id' => $config->id,
                'log_id' => $log->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Cập nhật log với lỗi
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'duration' => $log->started_at->diffInSeconds(now(), false),
                'error_message' => $e->getMessage()
            ]);

            // Dọn dẹp nếu có lỗi
            if (isset($backupPath) && file_exists($backupPath)) {
                $this->deleteDirectory($backupPath);
            }
            if (isset($zipPath) && file_exists($zipPath)) {
                unlink($zipPath);
            }

            // Gửi email thông báo lỗi
            if ($config->email_notification) {
                $this->sendFailureNotification($config, $log, $e->getMessage());
            }

            return $log;
        }
    }

    /**
     * Lấy danh sách config cần chạy backup
     */
    public function getScheduledConfigurations(): \Illuminate\Database\Eloquent\Collection
    {
        return BackupConfiguration::where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();
    }

    /**
     * Backup database
     */
    private function backupDatabase(string $backupPath): void
    {
        $databaseName = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');

        $sqlFile = $backupPath . '/database.sql';

        $passwordOption = !empty($password) ? '--password=' . escapeshellarg($password) : '';
        $command = sprintf(
            'mysqldump --user=%s %s --host=%s --port=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($username),
            $passwordOption,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($databaseName),
            escapeshellarg($sqlFile)
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Database backup failed. Return code: $returnVar");
        }

        if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
            throw new \Exception("Database backup file is empty or not created");
        }
    }

    /**
     * Backup .env file
     */
    private function backupEnvFile(string $backupPath): void
    {
        $envPath = base_path('.env');
        $backupEnvPath = $backupPath . '/.env';

        if (file_exists($envPath)) {
            copy($envPath, $backupEnvPath);
        } else {
            throw new \Exception('.env file not found');
        }
    }

    /**
     * Backup uploaded files
     */
    private function backupUploadedFiles(string $backupPath): void
    {
        $storagePath = storage_path('app/public');
        $uploadsBackupPath = $backupPath . '/uploads';

        if (!file_exists($uploadsBackupPath)) {
            mkdir($uploadsBackupPath, 0755, true);
        }

        // Backup all files in storage/app/public
        $this->copyDirectory($storagePath, $uploadsBackupPath);
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * Create ZIP archive
     */
    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== TRUE) {
            throw new \Exception("Cannot create zip file: $zipPath");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Gửi thông báo thành công
     */
    private function sendSuccessNotification(BackupConfiguration $config, BackupLog $log): void
    {
        try {
            foreach ($config->notification_emails as $email) {
                Notification::route('mail', $email)->notify(new BackupCompleted($config, $log));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send backup success notification: ' . $e->getMessage());
        }
    }

    /**
     * Gửi thông báo lỗi
     */
    private function sendFailureNotification(BackupConfiguration $config, BackupLog $log, string $error): void
    {
        try {
            foreach ($config->notification_emails as $email) {
                Notification::route('mail', $email)->notify(new BackupFailed($config, $log, $error));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send backup failure notification: ' . $e->getMessage());
        }
    }

    /**
     * Dọn dẹp backup cũ trên Google Drive
     */
    private function cleanupOldBackups(BackupConfiguration $config): void
    {
        try {
            if ($config->google_drive_config && isset($config->google_drive_config['folder_id'])) {
                $this->googleDriveService->cleanupOldFiles(
                    $config->google_drive_config['folder_id'],
                    $config->retention_days
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old backups: ' . $e->getMessage());
        }
    }
}
