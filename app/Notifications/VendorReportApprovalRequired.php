<?php

namespace App\Notifications;

use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReportApprovalRequired extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public VendorReport $report,
        public VendorReportApprovalStep $step
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
        $previousApprover = $this->step->step_order > 1
            ? $this->report->approvalSteps()->where('step_order', $this->step->step_order - 1)->first()?->actedByUser?->name
            : $this->report->creator->name;

        return (new MailMessage)
            ->subject("[Cần phê duyệt] {$this->report->code} - {$this->report->title}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Phiếu lựa chọn nhà cung cấp đã được phê duyệt và chuyển đến bạn.")
            ->line("**Mã phiếu:** {$this->report->code}")
            ->line("**Tiêu đề:** {$this->report->title}")
            ->line("**Người tạo:** {$this->report->creator->name}")
            ->line("**Loại quy trình:** {$this->report->getWorkflowTypeLabel()}")
            ->line("**Bước hiện tại:** Bước {$this->step->step_order} - {$this->step->role_label}")
            ->line("**Người duyệt trước:** {$previousApprover}")
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
            'step_order' => $this->step->step_order,
        ];
    }
}
