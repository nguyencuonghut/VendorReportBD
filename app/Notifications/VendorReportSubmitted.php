<?php

namespace App\Notifications;

use App\Models\VendorReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReportSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public VendorReport $report
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
            ->subject("[Phiếu mới] {$this->report->code} - {$this->report->title}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Có phiếu lựa chọn nhà cung cấp mới cần bạn phê duyệt.")
            ->line("**Mã phiếu:** {$this->report->code}")
            ->line("**Tiêu đề:** {$this->report->title}")
            ->line("**Người tạo:** {$this->report->creator->name}")
            ->action('Xem chi tiết phiếu', url("/vendor-reports/{$this->report->id}"))
            ->line('Vui lòng xem xét và phê duyệt phiếu này.');
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
        ];
    }
}
