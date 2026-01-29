<?php

namespace App\Services;

use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VendorReportWorkflowBuilder
{
    /**
     * Build runtime approval steps khi submit phiếu
     *
     * @param VendorReport $report
     * @return void
     */
    public function buildSteps(VendorReport $report): void
    {
        $workflowType = $report->workflow_type;
        $config = config("vendor_report_workflows.{$workflowType}");

        if (empty($config)) {
            throw new \Exception("Workflow config not found for type: {$workflowType}");
        }

        // Lấy creator để biết department
        $creator = $report->creator()->with('department')->first();

        if (!$creator || !$creator->department) {
            throw new \Exception('Người tạo phiếu phải thuộc một phòng ban');
        }

        $department = $creator->department;

        // Kiểm tra trưởng phòng
        if (!$department->head_user_id) {
            throw new \Exception("Phòng {$department->name} chưa có Trưởng phòng");
        }

        DB::transaction(function () use ($report, $config, $department) {
            foreach ($config as $index => $stepConfig) {
                $stepOrder = $index + 1;
                $stepKey = $stepConfig['step_key'];
                $requiresSelection = $stepConfig['requires_selection'] ?? false;
                $selectionRole = $stepConfig['selection_role'] ?? null;

                // Xác định assignee cho step
                $assigneeUserId = null;
                $assigneeRole = null;

                if ($stepKey === 'DEPT_HEAD') {
                    // Step 1: Trưởng phòng (snapshot tại thời điểm submit)
                    $assigneeUserId = $department->head_user_id;
                } elseif (in_array($stepKey, ['INTERNAL_CONTROL', 'NATIONAL_PURCHASING', 'TECH_BOARD', 'BOD', 'BOD_1', 'BOD_2'])) {
                    // Các step khác sẽ được assign sau khi bước trước chọn người
                    // Nếu là step đầu tiên sau DEPT_HEAD và có assignee_role
                    $assigneeRole = $this->mapStepKeyToRole($stepKey);
                }

                // Tạo step
                VendorReportApprovalStep::create([
                    'report_id' => $report->id,
                    'step_key' => $stepKey,
                    'step_order' => $stepOrder,
                    'status' => 'PENDING',
                    'assignee_user_id' => $assigneeUserId,
                    'assignee_role' => $assigneeRole,
                    'requires_selection' => $requiresSelection,
                    'selection_role' => $selectionRole,
                ]);
            }

            // Set current_step_id = step đầu tiên
            $firstStep = $report->approvalSteps()->orderBy('step_order')->first();
            $report->update(['current_step_id' => $firstStep->id]);
        });
    }

    /**
     * Map step_key sang role name
     *
     * @param string $stepKey
     * @return string|null
     */
    private function mapStepKeyToRole(string $stepKey): ?string
    {
        return match($stepKey) {
            'INTERNAL_CONTROL' => 'internal_control',
            'NATIONAL_PURCHASING' => 'national_purchasing',
            'TECH_BOARD' => 'tech_board',
            'BOD', 'BOD_1', 'BOD_2' => 'bod',
            default => null,
        };
    }
}
