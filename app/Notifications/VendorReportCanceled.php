<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VendorReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReportCanceled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public VendorReport $report,
        public User $admin,
        public string $reason
    ) {}

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
            ->subject("[Đã hủy] {$this->report->code} - {$this->report->title}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Phiếu lựa chọn nhà cung cấp đã bị hủy.")
            ->line("**Mã phiếu:** {$this->report->code}")
            ->line("**Tiêu đề:** {$this->report->title}")
            ->line("**Người hủy:** {$this->admin->name}")
            ->line("**Lý do hủy:**")
            ->line($this->reason)
            ->action('Xem chi tiết phiếu', url("/vendor-reports/{$this->report->id}"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'report_code' => $this->report->code,
            'report_title' => $this->report->title,
            'canceled_by' => $this->admin->name,
            'canceled_reason' => $this->reason,
        ];
    }
}
