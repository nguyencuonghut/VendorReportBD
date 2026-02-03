<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Models\User;
use App\Models\Department;
use Carbon\Carbon;

class VendorReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting VendorReport seeder...');

        // Get all necessary data
        $users = User::with('department')->get();
        $departments = Department::with('headUser')->get();

        if ($users->isEmpty() || $departments->isEmpty()) {
            $this->command->error('❌ No users or departments found. Please run UserSeeder and DepartmentSeeder first.');
            return;
        }

        // Group users by roles
        // Only get requesters from Phòng Thu Mua (department with head)
        $thuMuaDept = $departments->firstWhere('code', 'TM');

        if (!$thuMuaDept || !$thuMuaDept->head_user_id) {
            $this->command->error('❌ Phòng Thu Mua does not have a department head. Cannot create vendor reports.');
            return;
        }

        $requesters = $users->filter(fn($u) =>
            $u->hasRole('requester') &&
            $u->department_id === $thuMuaDept->id
        );

        $purchasingAdmins = $users->filter(fn($u) => $u->hasRole('purchasing_admin'));

        // Get department heads from departments table (head_user_id)
        $deptHeads = $departments->filter(fn($d) => $d->head_user_id !== null)
            ->pluck('headUser')->filter();

        $internalControllers = $users->filter(fn($u) => $u->hasRole('internal_control'));
        $nationalPurchasers = $users->filter(fn($u) => $u->hasRole('national_purchasing'));
        $techBoards = $users->filter(fn($u) => $u->hasRole('tech_board'));
        $bods = $users->filter(fn($u) => $u->hasRole('bod'));

        if ($requesters->isEmpty()) {
            $this->command->error('❌ No requesters found in Phòng Thu Mua. Cannot create vendor reports.');
            return;
        }

        $this->command->info("✓ Found {$requesters->count()} requesters in Phòng Thu Mua");

        // Workflow types with their distribution percentages
        $workflowTypes = [
            'NORMAL' => 50,      // 50%
            'SPECIAL_1' => 15,   // 15% - Qua 2 BOD
            'SPECIAL_2' => 15,   // 15% - Qua Khối Mua Hàng
            'SPECIAL_3' => 10,   // 10% - Qua Ban Kỹ thuật
            'URGENT' => 10,      // 10% - Khẩn cấp
        ];

        // Status distribution
        $statusDistribution = [
            'DRAFT' => 5,           // 5%
            'SUBMITTED' => 5,       // 5%
            'IN_APPROVAL' => 15,    // 15%
            'APPROVED' => 65,       // 65%
            'REJECTED' => 8,        // 8%
            'CANCELED' => 2,        // 2%
        ];

        $totalReports = 500;
        $startDate = Carbon::now()->subMonths(7);
        $endDate = Carbon::now();

        $this->command->info("📊 Creating {$totalReports} vendor reports from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        // Global sequence counter (starting from 1)
        $globalSequence = 1;

        for ($i = 0; $i < $totalReports; $i++) {
            // Random date within last 7 months
            $createdAt = Carbon::instance(fake()->dateTimeBetween($startDate, $endDate));

            // Select workflow type based on distribution
            $workflowType = $this->selectByDistribution($workflowTypes);

            // Select status based on distribution
            $status = $this->selectByDistribution($statusDistribution);

            // Select random requester as creator
            $creator = $requesters->random();
            $department = $creator->department;

            // Select purchasing admin (optional)
            $purchasingAdmin = $purchasingAdmins->isNotEmpty() ? $purchasingAdmins->random() : null;

            // Generate code: YYYY/DEPT/SEQ
            $year = $createdAt->year;
            $deptCode = $department ? $department->code : 'UNKNOWN';
            $code = sprintf('%d/%s/%04d', $year, $deptCode, $globalSequence);
            $globalSequence++;

            // Create vendor report
            $report = VendorReport::create([
                'code' => $code,
                'title' => $this->generateTitle(),
                'workflow_type' => $workflowType,
                'purchasing_admin_id' => $purchasingAdmin?->id,
                'created_by' => $creator->id,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Set timestamps based on status
            $this->setReportTimestamps($report, $createdAt, $status);

            // Create approval steps based on workflow type and status
            $this->createApprovalSteps(
                $report,
                $workflowType,
                $status,
                $createdAt,
                $department,
                $internalControllers,
                $nationalPurchasers,
                $techBoards,
                $bods
            );

            if (($i + 1) % 50 === 0) {
                $current = $i + 1;
                $this->command->info("✅ Created {$current}/{$totalReports} reports...");
            }
        }

        $this->command->info("✨ Successfully created {$totalReports} vendor reports!");
        $this->command->info("📈 Status breakdown:");
        foreach ($statusDistribution as $status => $percent) {
            $count = VendorReport::where('status', $status)->count();
            $this->command->info("   - {$status}: {$count} reports ({$percent}%)");
        }
    }

    /**
     * Select item based on percentage distribution
     */
    private function selectByDistribution(array $distribution): string
    {
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($distribution as $item => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return array_key_first($distribution);
    }

    /**
     * Generate random report title
     */
    private function generateTitle(): string
    {
        $subjects = [
            'Nguyên liệu thức ăn chăn nuôi',
            'Thiết bị sản xuất',
            'Vật tư phụ tùng',
            'Hóa chất xử lý nước',
            'Đậu nành nhập khẩu',
            'Bột cá Peru',
            'Ngô thức ăn gia súc',
            'Vitamin và khoáng chất',
            'Men tiêu hóa',
            'Thuốc thú y',
            'Bao bì đóng gói',
            'Phụ gia thức ăn',
            'Acid amin công nghiệp',
            'Chất chống oxy hóa',
            'Enzyme tiêu hóa',
        ];

        $actions = [
            'cung cấp',
            'sản xuất và cung cấp',
            'nhập khẩu',
            'phân phối',
            'sản xuất theo đơn đặt hàng',
        ];

        $periods = [
            'năm ' . date('Y'),
            'quý ' . fake()->numberBetween(1, 4) . '/' . date('Y'),
            'tháng ' . fake()->numberBetween(1, 12) . '/' . date('Y'),
            '6 tháng đầu năm ' . date('Y'),
            'năm ' . (date('Y') + 1),
        ];

        $subject = fake()->randomElement($subjects);
        $action = fake()->randomElement($actions);
        $period = fake()->randomElement($periods);

        return "Chọn nhà cung cấp {$action} {$subject} {$period}";
    }

    /**
     * Set report timestamps based on status
     */
    private function setReportTimestamps(VendorReport $report, Carbon $createdAt, string $status): void
    {
        switch ($status) {
            case 'DRAFT':
                // Draft - no timestamps
                break;

            case 'SUBMITTED':
                // Just submitted, no other timestamps
                $report->submitted_at = $createdAt->copy()->addHours(fake()->numberBetween(1, 24));
                break;

            case 'IN_APPROVAL':
                // Submitted and in approval process
                $report->submitted_at = $createdAt->copy()->addHours(fake()->numberBetween(1, 12));
                break;

            case 'APPROVED':
                // Full cycle completed
                $submittedAt = $createdAt->copy()->addHours(fake()->numberBetween(1, 12));
                $approvedAt = $submittedAt->copy()->addDays(fake()->numberBetween(1, 15));

                $report->submitted_at = $submittedAt;
                $report->approved_at = $approvedAt;
                break;

            case 'REJECTED':
                // Submitted and rejected
                $submittedAt = $createdAt->copy()->addHours(fake()->numberBetween(1, 12));
                $rejectedAt = $submittedAt->copy()->addDays(fake()->numberBetween(1, 7));

                $report->submitted_at = $submittedAt;
                $report->rejected_at = $rejectedAt;
                $report->rejected_note = fake()->randomElement([
                    'Hồ sơ không đầy đủ, cần bổ sung thêm tài liệu',
                    'Giá đề xuất chưa phù hợp với ngân sách',
                    'Nhà cung cấp không đáp ứng đủ tiêu chuẩn kỹ thuật',
                    'Cần đánh giá lại năng lực nhà cung cấp',
                    'Thời gian giao hàng không phù hợp với kế hoạch sản xuất',
                ]);
                break;

            case 'CANCELED':
                // Submitted and then canceled
                $submittedAt = $createdAt->copy()->addHours(fake()->numberBetween(1, 12));
                $canceledAt = $submittedAt->copy()->addDays(fake()->numberBetween(1, 5));

                $report->submitted_at = $submittedAt;
                $report->canceled_at = $canceledAt;
                $report->canceled_reason = fake()->randomElement([
                    'Thay đổi kế hoạch mua hàng',
                    'Ngân sách không được phê duyệt',
                    'Đã tìm được nhà cung cấp phù hợp hơn',
                    'Yêu cầu từ Ban Giám Đốc',
                    'Tạm hoãn dự án',
                ]);
                break;
        }

        $report->save();
    }

    /**
     * Create approval steps based on workflow type
     */
    private function createApprovalSteps(
        VendorReport $report,
        string $workflowType,
        string $status,
        Carbon $baseDate,
        $department,
        $internalControllers,
        $nationalPurchasers,
        $techBoards,
        $bods
    ): void {
        // Skip creating steps for DRAFT
        if ($status === 'DRAFT') {
            return;
        }

        $steps = [];
        $currentDate = $baseDate->copy();

        // Get department head for this specific department
        $deptHead = $department && $department->head_user_id
            ? User::find($department->head_user_id)
            : null;

        // Define workflow steps based on type
        switch ($workflowType) {
            case 'NORMAL':
                $steps = [
                    ['key' => 'DEPT_HEAD', 'role' => 'dept_head', 'user' => $deptHead],
                    ['key' => 'INTERNAL_CONTROL', 'role' => 'internal_control', 'users' => $internalControllers],
                    ['key' => 'BOD', 'role' => 'bod', 'users' => $bods],
                ];
                break;

            case 'SPECIAL_1': // Qua 2 BOD
                $steps = [
                    ['key' => 'DEPT_HEAD', 'role' => 'dept_head', 'user' => $deptHead],
                    ['key' => 'INTERNAL_CONTROL', 'role' => 'internal_control', 'users' => $internalControllers],
                    ['key' => 'BOD_1', 'role' => 'bod', 'users' => $bods],
                    ['key' => 'BOD_2', 'role' => 'bod', 'users' => $bods],
                ];
                break;

            case 'SPECIAL_2': // Qua Khối Mua Hàng
                $steps = [
                    ['key' => 'DEPT_HEAD', 'role' => 'dept_head', 'user' => $deptHead],
                    ['key' => 'NATIONAL_PURCHASING', 'role' => 'national_purchasing', 'users' => $nationalPurchasers],
                    ['key' => 'INTERNAL_CONTROL', 'role' => 'internal_control', 'users' => $internalControllers],
                    ['key' => 'BOD', 'role' => 'bod', 'users' => $bods],
                ];
                break;

            case 'SPECIAL_3': // Qua Ban Kỹ thuật
                $steps = [
                    ['key' => 'DEPT_HEAD', 'role' => 'dept_head', 'user' => $deptHead],
                    ['key' => 'TECH_BOARD', 'role' => 'tech_board', 'users' => $techBoards],
                    ['key' => 'INTERNAL_CONTROL', 'role' => 'internal_control', 'users' => $internalControllers],
                    ['key' => 'BOD', 'role' => 'bod', 'users' => $bods],
                ];
                break;

            case 'URGENT': // Khẩn cấp - chỉ BOD
                $steps = [
                    ['key' => 'BOD', 'role' => 'bod', 'users' => $bods],
                ];
                break;
        }

        // Create steps
        $createdSteps = [];
        foreach ($steps as $index => $stepConfig) {
            $stepOrder = $index + 1;

            // Determine step status based on report status
            $stepStatus = $this->determineStepStatus($status, $stepOrder, count($steps));

            // Get assignee: either specific user or random from collection
            if (isset($stepConfig['user'])) {
                $assignee = $stepConfig['user'];
            } elseif (isset($stepConfig['users']) && $stepConfig['users']->isNotEmpty()) {
                $assignee = $stepConfig['users']->random();
            } else {
                $assignee = null;
            }

            if (!$assignee) {
                continue; // Skip if no user available for this role
            }

            $step = VendorReportApprovalStep::create([
                'report_id' => $report->id,
                'step_key' => $stepConfig['key'],
                'step_order' => $stepOrder,
                'status' => $stepStatus,
                'assignee_user_id' => $assignee->id,
                'assignee_role' => $stepConfig['role'],
                'created_at' => $currentDate,
                'updated_at' => $currentDate,
            ]);

            // Set acted_at and acted_by for approved/rejected steps
            if (in_array($stepStatus, ['APPROVED', 'REJECTED'])) {
                $actedDate = $currentDate->copy()->addDays(fake()->numberBetween(1, 3));
                $step->acted_at = $actedDate;
                $step->acted_by = $assignee->id;

                if ($stepStatus === 'REJECTED') {
                    $step->note = 'Không đồng ý với đề xuất này';
                }

                $step->save();
                $currentDate = $actedDate;
            }

            $createdSteps[] = $step;
        }

        // Update report's current_step_id
        if (!empty($createdSteps)) {
            if ($status === 'IN_APPROVAL') {
                // Find first pending step
                $pendingStep = collect($createdSteps)->first(fn($s) => $s->status === 'PENDING');
                if ($pendingStep) {
                    $report->current_step_id = $pendingStep->id;
                    $report->save();
                }
            } elseif ($status === 'APPROVED') {
                // Last step should be current
                $report->current_step_id = $createdSteps[count($createdSteps) - 1]->id;
                $report->save();
            } elseif ($status === 'REJECTED') {
                // Find rejected step
                $rejectedStep = collect($createdSteps)->first(fn($s) => $s->status === 'REJECTED');
                if ($rejectedStep) {
                    $report->current_step_id = $rejectedStep->id;
                    $report->save();
                }
            } elseif ($status === 'SUBMITTED') {
                // First step is current
                $report->current_step_id = $createdSteps[0]->id;
                $report->save();
            }
        }
    }

    /**
     * Determine step status based on report status and position
     */
    private function determineStepStatus(string $reportStatus, int $stepOrder, int $totalSteps): string
    {
        switch ($reportStatus) {
            case 'SUBMITTED':
                // All steps pending for just submitted reports
                return 'PENDING';

            case 'IN_APPROVAL':
                // Random progress through approval chain
                $completedSteps = fake()->numberBetween(0, $totalSteps - 1);
                if ($stepOrder <= $completedSteps) {
                    return 'APPROVED';
                } elseif ($stepOrder === $completedSteps + 1) {
                    return 'PENDING';
                } else {
                    return 'PENDING';
                }

            case 'APPROVED':
                // All steps approved
                return 'APPROVED';

            case 'REJECTED':
                // Some steps approved, one rejected
                $rejectedAt = fake()->numberBetween(1, $totalSteps);
                if ($stepOrder < $rejectedAt) {
                    return 'APPROVED';
                } elseif ($stepOrder === $rejectedAt) {
                    return 'REJECTED';
                } else {
                    return 'PENDING';
                }

            case 'CANCELED':
                // Some steps may be approved before cancellation
                $completedBeforeCancel = fake()->numberBetween(0, $totalSteps - 1);
                if ($stepOrder <= $completedBeforeCancel) {
                    return 'APPROVED';
                } else {
                    return 'PENDING';
                }

            default:
                return 'PENDING';
        }
    }
}
