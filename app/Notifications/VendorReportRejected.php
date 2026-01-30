<?php

namespace App\Notifications;

use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReportRejected extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public VendorReport $report,
        public VendorReportApprovalStep $step,
        public string $rejectionNote
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
            ->subject("[Từ chối] {$this->report->code} - {$this->report->title}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Phiếu lựa chọn nhà cung cấp đã bị từ chối.")
            ->line("**Mã phiếu:** {$this->report->code}")
            ->line("**Tiêu đề:** {$this->report->title}")
            ->line("**Người từ chối:** {$this->step->actedByUser->name}")
            ->line("**Bước bị từ chối:** Bước {$this->step->step_order} - {$this->step->role_label}")
            ->line("**Lý do từ chối:**")
            ->line($this->rejectionNote)
            ->action('Xem chi tiết phiếu', url("/vendor-reports/{$this->report->id}"))
            ->line('Bạn có thể tạo phiếu mới từ phiếu bị từ chối này để chỉnh sửa và nộp lại.');
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
            'rejection_note' => $this->rejectionNote,
            'step_order' => $this->step->step_order,
        ];
    }
}
