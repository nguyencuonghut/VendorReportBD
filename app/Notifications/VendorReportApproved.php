<?php

namespace App\Notifications;

use App\Models\VendorReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReportApproved extends Notification implements ShouldQueue
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
        $totalSteps = $this->report->approvalSteps()->count();
        $approvedSteps = $this->report->approvalSteps()->where('status', 'APPROVED')->count();

        return (new MailMessage)
            ->subject("[Đã duyệt] {$this->report->code} - {$this->report->title}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Phiếu lựa chọn nhà cung cấp đã được phê duyệt hoàn tất.")
            ->line("**Mã phiếu:** {$this->report->code}")
            ->line("**Tiêu đề:** {$this->report->title}")
            ->line("**Người tạo:** {$this->report->creator->name}")
            ->line("**Loại quy trình:** {$this->report->getWorkflowTypeLabel()}")
            ->line("**Trạng thái:** Đã hoàn thành ({$approvedSteps}/{$totalSteps} bước phê duyệt)")
            ->action('Xem chi tiết phiếu', url("/vendor-reports/{$this->report->id}"))
            ->line('Phiếu đã được tất cả các bên liên quan phê duyệt.');
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
