<?php

namespace App\Notifications;

use App\Models\BackupConfiguration;
use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailed extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $configName;
    protected int $configId;
    protected BackupLog $log;
    protected string $errorMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(BackupConfiguration $config, BackupLog $log, string $errorMessage = '')
    {
        $this->configName = $config->name;
        $this->configId = $config->id;
        $this->log = $log;
        $this->errorMessage = $errorMessage;
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
            ->subject('❌ Backup thất bại - ' . $this->configName)
            ->greeting('Xin chào!')
            ->line('Backup tự động đã gặp lỗi và không thể hoàn thành.')
            ->line('**Thông tin backup:**')
            ->line('• Tên cấu hình: ' . $this->configName)
            ->line('• Thời gian bắt đầu: ' . $this->log->started_at->format('d/m/Y H:i:s'))
            ->lineIf($this->log->completed_at, '• Thời gian kết thúc: ' . $this->log->completed_at->format('d/m/Y H:i:s'))
            ->lineIf($this->log->duration, '• Thời gian thực hiện: ' . $this->log->formatted_duration)
            ->line('**Chi tiết lỗi:**')
            ->line($this->errorMessage ?: $this->log->error_message ?: 'Không có thông tin chi tiết về lỗi.')
            ->line('Vui lòng kiểm tra lại cấu hình backup hoặc liên hệ với quản trị viên hệ thống.')
            ->action('Xem cấu hình Backup', route('backup.configurations'))
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
            'status' => 'failed',
            'started_at' => $this->log->started_at,
            'completed_at' => $this->log->completed_at,
            'duration' => $this->log->duration,
            'error_message' => $this->errorMessage ?: $this->log->error_message
        ];
    }
}
