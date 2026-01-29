<?php

namespace App\Notifications;

use App\Models\BackupConfiguration;
use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $configName;
    protected int $configId;
    protected BackupLog $log;

    /**
     * Create a new notification instance.
     */
    public function __construct(BackupConfiguration $config, BackupLog $log)
    {
        $this->configName = $config->name;
        $this->configId = $config->id;
        $this->log = $log;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ Backup thành công - ' . $this->configName)
            ->greeting('Xin chào!')
            ->line('Backup tự động đã hoàn thành thành công.')
            ->line('**Thông tin backup:**')
            ->line('• Tên cấu hình: ' . $this->configName)
            ->line('• Thời gian bắt đầu: ' . $this->log->started_at->format('d/m/Y H:i:s'))
            ->line('• Thời gian hoàn thành: ' . $this->log->completed_at->format('d/m/Y H:i:s'))
            ->line('• Thời gian thực hiện: ' . $this->log->formatted_duration)
            ->line('• Kích thước file: ' . $this->log->formatted_file_size)
            ->lineIf($this->log->google_drive_file_id, '• Đã upload lên Google Drive: ✅')
            ->line('Backup đã được thực hiện thành công và dữ liệu của bạn được bảo vệ an toàn.')
            ->salutation('Trân trọng, ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'config_id' => $this->configId,
            'config_name' => $this->configName,
            'log_id' => $this->log->id,
            'status' => 'success',
            'started_at' => $this->log->started_at,
            'completed_at' => $this->log->completed_at,
            'duration' => $this->log->duration,
            'file_size' => $this->log->file_size,
            'google_drive_uploaded' => $this->log->google_drive_file_id !== null
        ];
    }
}
