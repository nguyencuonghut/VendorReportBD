<?php

namespace App\Services;

use App\Models\VendorReport;
use Illuminate\Support\Facades\DB;

class VendorReportSubmissionService
{
    public function __construct(
        private VendorReportCodeGenerator $codeGenerator,
        private VendorReportWorkflowBuilder $workflowBuilder
    ) {}

    /**
     * Submit phiếu: Generate code + Build workflow steps
     *
     * @param VendorReport $report
     * @return VendorReport
     * @throws \Exception
     */
    public function submit(VendorReport $report): VendorReport
    {
        if ($report->status !== 'DRAFT') {
            throw new \Exception('Chỉ có thể gửi phiếu ở trạng thái Nháp');
        }

        // Validate: Người tạo phải có department
        $creator = $report->creator()->with('department')->first();

        if (!$creator || !$creator->department_id) {
            throw new \Exception('Người tạo phiếu phải thuộc một phòng ban');
        }

        $department = $creator->department;

        if (!$department->is_active) {
            throw new \Exception("Phòng {$department->name} đang không hoạt động");
        }

        if (!$department->head_user_id) {
            throw new \Exception("Phòng {$department->name} chưa có Trưởng phòng");
        }

        return DB::transaction(function () use ($report, $creator) {
            // 1. Generate code nếu chưa có
            if (!$report->code) {
                $code = $this->codeGenerator->generate($creator->department_id);
                $report->code = $code;
            }

            // 2. Update status
            $report->status = 'IN_APPROVAL';
            $report->submitted_at = now();
            $report->save();

            // 3. Build workflow steps
            $this->workflowBuilder->buildSteps($report);

            // 4. Reload để lấy current_step_id đã được set
            $report->refresh();

            // 5. Log activity
            activity()
                ->performedOn($report)
                ->causedBy(auth()->user())
                ->withProperties(['code' => $report->code, 'workflow_type' => $report->workflow_type])
                ->log('report_submitted');

            return $report;
        });
    }
}
