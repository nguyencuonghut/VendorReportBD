<?php

namespace App\Services;

use App\Models\VendorReport;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VendorReportActivityService
{
    /**
     * Log activity khi tạo phiếu mới
     */
    public function logCreated(VendorReport $report, array $uploadedFiles = []): void
    {
        $properties = [
            'title' => $report->title,
            'workflow_type' => $report->workflow_type,
            'workflow_type_label' => $report->workflow_type_label,
            'purchasing_admin_id' => $report->purchasing_admin_id,
            'purchasing_admin_name' => $report->purchasingAdmin?->name,
        ];

        if (!empty($uploadedFiles)) {
            $properties['files_uploaded'] = $uploadedFiles;
        }

        activity()
            ->performedOn($report)
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->log('created');
    }

    /**
     * Log activity khi cập nhật phiếu
     */
    public function logUpdated(VendorReport $report, array $changes = [], array $deletedFiles = [], array $uploadedFiles = []): void
    {
        $properties = [
            'changes' => $changes,
        ];

        // Ghi lại các thay đổi cụ thể
        if (isset($changes['title'])) {
            $properties['title_old'] = $changes['title']['old'] ?? null;
            $properties['title_new'] = $changes['title']['new'] ?? null;
        }

        if (isset($changes['workflow_type'])) {
            $properties['workflow_type_old'] = $changes['workflow_type']['old'] ?? null;
            $properties['workflow_type_new'] = $changes['workflow_type']['new'] ?? null;
            $properties['workflow_type_label_old'] = VendorReport::getWorkflowTypesWithLabels()[$changes['workflow_type']['old']] ?? null;
            $properties['workflow_type_label_new'] = VendorReport::getWorkflowTypesWithLabels()[$changes['workflow_type']['new']] ?? null;
        }

        if (isset($changes['purchasing_admin_id'])) {
            $oldAdmin = $changes['purchasing_admin_id']['old'] ? User::find($changes['purchasing_admin_id']['old']) : null;
            $newAdmin = $changes['purchasing_admin_id']['new'] ? User::find($changes['purchasing_admin_id']['new']) : null;

            $properties['purchasing_admin_old'] = $oldAdmin?->name;
            $properties['purchasing_admin_new'] = $newAdmin?->name;
        }

        if (!empty($deletedFiles)) {
            $properties['files_deleted'] = $deletedFiles;
        }

        if (!empty($uploadedFiles)) {
            $properties['files_uploaded'] = $uploadedFiles;
        }

        activity()
            ->performedOn($report)
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->log('updated');
    }

    /**
     * Helper method để format thông tin file
     */
    public function formatFileInfo(string $fileType, string $fileName, ?int $fileSize = null): array
    {
        $fileTypeLabels = [
            'REPORT_IMAGE' => 'Ảnh báo cáo',
            'QUOTATION' => 'File báo giá',
            'BOQ' => 'File đề nghị/BOQ',
        ];

        $info = [
            'type' => $fileType,
            'type_label' => $fileTypeLabels[$fileType] ?? $fileType,
            'name' => $fileName,
        ];

        if ($fileSize !== null) {
            $info['size'] = $fileSize;
            $info['size_formatted'] = $this->formatFileSize($fileSize);
        }

        return $info;
    }

    /**
     * Log activity khi nộp phiếu
     */
    public function logSubmitted(VendorReport $report): void
    {
        activity()
            ->performedOn($report)
            ->causedBy(Auth::user())
            ->withProperties([
                'code' => $report->code,
                'workflow_type' => $report->workflow_type,
                'workflow_type_label' => $report->workflow_type_label,
                'submitted_at' => $report->submitted_at?->toISOString(),
                'total_steps' => $report->approvalSteps()->count(),
            ])
            ->log('submitted');
    }

    /**
     * Log activity khi phê duyệt
     */
    public function logApproved(VendorReport $report, User $approver, ?string $note = null, ?int $stepOrder = null): void
    {
        activity()
            ->performedOn($report)
            ->causedBy($approver)
            ->withProperties([
                'step_order' => $stepOrder,
                'step_role' => $report->currentStep?->role_key,
                'note' => $note,
                'approved_at' => now()->toISOString(),
            ])
            ->log('approved');
    }

    /**
     * Log activity khi từ chối
     */
    public function logRejected(VendorReport $report, User $rejector, string $note, ?int $stepOrder = null): void
    {
        activity()
            ->performedOn($report)
            ->causedBy($rejector)
            ->withProperties([
                'step_order' => $stepOrder,
                'step_role' => $report->currentStep?->role_key,
                'rejection_note' => $note,
                'rejected_at' => now()->toISOString(),
            ])
            ->log('rejected');
    }

    /**
     * Log activity khi hoàn thành phiếu
     */
    public function logCompleted(VendorReport $report): void
    {
        activity()
            ->performedOn($report)
            ->withProperties([
                'completed_at' => $report->completed_at?->toISOString(),
                'total_steps' => $report->approvalSteps()->count(),
                'approved_steps' => $report->approvalSteps()->where('status', 'APPROVED')->count(),
            ])
            ->log('completed');
    }

    /**
     * Log activity khi sao chép phiếu từ phiếu bị từ chối
     */
    public function logClonedFromRejected(VendorReport $newReport, VendorReport $originalReport): void
    {
        activity()
            ->performedOn($newReport)
            ->causedBy(Auth::user())
            ->withProperties([
                'cloned_from_id' => $originalReport->id,
                'cloned_from_code' => $originalReport->code,
                'cloned_from_title' => $originalReport->title,
                'original_status' => $originalReport->status,
            ])
            ->log('cloned_from_rejected');
    }

    /**
     * Log activity khi xóa phiếu
     */
    public function logDeleted(VendorReport $report): void
    {
        activity()
            ->performedOn($report)
            ->causedBy(Auth::user())
            ->withProperties([
                'code' => $report->code,
                'title' => $report->title,
                'status' => $report->status,
                'status_label' => $report->status_label,
            ])
            ->log('deleted');
    }

    /**
     * Get activity labels in Vietnamese
     */
    public static function getActivityLabels(): array
    {
        return [
            'created' => 'Tạo phiếu',
            'updated' => 'Cập nhật phiếu',
            'file_uploaded' => 'Upload file',
            'file_deleted' => 'Xóa file',
            'submitted' => 'Nộp phiếu',
            'approved' => 'Phê duyệt',
            'rejected' => 'Từ chối',
            'completed' => 'Hoàn thành',
            'cloned_from_rejected' => 'Sao chép từ phiếu bị từ chối',
            'deleted' => 'Xóa phiếu',
        ];
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 Bytes';
        }

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Format activity description for display
     */
    public static function formatActivityDescription(object $activity): string
    {
        $label = self::getActivityLabels()[$activity->description] ?? $activity->description;
        $properties = $activity->properties ?? [];

        switch ($activity->description) {
            case 'created':
                $title = $properties['title'] ?? 'N/A';
                $text = "{$label}: {$title}";

                // Thêm thông tin workflow và admin
                if (isset($properties['workflow_type_label'])) {
                    $text .= "\n• Loại quy trình: {$properties['workflow_type_label']}";
                }
                if (isset($properties['purchasing_admin_name'])) {
                    $text .= "\n• Admin mua hàng: {$properties['purchasing_admin_name']}";
                }

                // Thêm thông tin files nếu có
                if (isset($properties['files_uploaded']) && count($properties['files_uploaded']) > 0) {
                    $text .= "\n• Upload files:";
                    foreach ($properties['files_uploaded'] as $file) {
                        $text .= "\n  - {$file['type_label']}: {$file['name']}" . (isset($file['size_formatted']) ? " ({$file['size_formatted']})" : '');
                    }
                }

                return $text;

            case 'updated':
                $parts = [];

                // Field changes
                if (isset($properties['title_old']) && isset($properties['title_new'])) {
                    $parts[] = "• Tiêu đề: \"{$properties['title_old']}\" → \"{$properties['title_new']}\"";
                }
                if (isset($properties['workflow_type_label_old']) && isset($properties['workflow_type_label_new'])) {
                    $parts[] = "• Loại quy trình: {$properties['workflow_type_label_old']} → {$properties['workflow_type_label_new']}";
                }
                if (isset($properties['purchasing_admin_old']) || isset($properties['purchasing_admin_new'])) {
                    $old = $properties['purchasing_admin_old'] ?? 'Không có';
                    $new = $properties['purchasing_admin_new'] ?? 'Không có';
                    $parts[] = "• Admin mua hàng: {$old} → {$new}";
                }

                // Deleted files
                if (isset($properties['files_deleted']) && count($properties['files_deleted']) > 0) {
                    $parts[] = "• Xóa files:";
                    foreach ($properties['files_deleted'] as $file) {
                        $parts[] = "  - {$file['type_label']}: {$file['name']}";
                    }
                }

                // Uploaded files
                if (isset($properties['files_uploaded']) && count($properties['files_uploaded']) > 0) {
                    $parts[] = "• Upload files:";
                    foreach ($properties['files_uploaded'] as $file) {
                        $parts[] = "  - {$file['type_label']}: {$file['name']}" . (isset($file['size_formatted']) ? " ({$file['size_formatted']})" : '');
                    }
                }

                return $label . (count($parts) > 0 ? "\n" . implode("\n", $parts) : '');

            case 'file_uploaded':
                $fileTypeLabel = $properties['file_type_label'] ?? ($properties['file_type'] ?? 'File');
                $fileName = $properties['file_name'] ?? 'N/A';
                $fileSize = $properties['file_size_formatted'] ?? '';
                return "{$label}: {$fileTypeLabel} - {$fileName}" . ($fileSize ? " ({$fileSize})" : '');

            case 'file_deleted':
                $fileTypeLabel = $properties['file_type_label'] ?? ($properties['file_type'] ?? 'File');
                $fileName = $properties['file_name'] ?? 'N/A';
                return "{$label}: {$fileTypeLabel} - {$fileName}";

            case 'submitted':
                $code = $properties['code'] ?? 'N/A';
                return "{$label}: Mã phiếu {$code}";

            case 'approved':
            case 'step_approved':
                $text = $label;
                if (isset($properties['step_order'])) {
                    $text .= " - Bước {$properties['step_order']}";
                }
                if (isset($properties['note']) && !empty($properties['note'])) {
                    $text .= "\n• Ghi chú: {$properties['note']}";
                }
                return $text;

            case 'rejected':
            case 'step_rejected':
                $text = $label;
                if (isset($properties['step_order'])) {
                    $text .= " - Bước {$properties['step_order']}";
                }
                if (isset($properties['rejection_note'])) {
                    $text .= "\n• Lý do: {$properties['rejection_note']}";
                }
                return $text;

            case 'completed':
                $approved = $properties['approved_steps'] ?? '?';
                $total = $properties['total_steps'] ?? '?';
                return "{$label}: Đã hoàn thành {$approved}/{$total} bước phê duyệt";

            case 'cloned_from_rejected':
                $code = $properties['cloned_from_code'] ?? ($properties['cloned_from'] ?? 'N/A');
                return "{$label}: Từ phiếu {$code}";

            case 'deleted':
                $code = $properties['code'] ?? 'N/A';
                $title = $properties['title'] ?? '';
                return "{$label}: {$code}" . ($title ? " - {$title}" : '');

            default:
                return $label;
        }
    }
}
