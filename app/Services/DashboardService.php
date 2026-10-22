<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    /**
     * Get metrics based on user role
     */
    public function getMetrics(User $user): array
    {
        $specialistMetrics = match (true) {
            $user->hasRole('admin_system') => $this->getAdminMetrics($user),
            $user->hasRole('purchasing_admin') => $this->getPurchasingAdminMetrics($user),
            $user->hasRole('accountant') => $this->getAccountantMetrics($user),
            default => null,
        };

        $isApprover = VendorReportApprovalStep::assignedTo($user)->exists();
        if ($specialistMetrics !== null) {
            if ($isApprover) {
                $specialistMetrics[] = $this->getPendingApprovalMetric($user);
            }

            return $specialistMetrics;
        }

        // Check if user is dept head
        $isDeptHead = $this->isDeptHead($user);

        if ($isApprover || $isDeptHead) {
            $metrics = $this->getApproverMetrics($user);

            if ($isDeptHead) {
                $metrics = array_merge($metrics, $this->getDeptHeadMetrics($user));
            }

            return $metrics;
        }

        // Default: requester metrics
        return $this->getRequesterMetrics($user);
    }

    private function getAdminMetrics(User $user): array
    {
        return [
            [
                'id' => 'total_reports',
                'title' => 'Tổng số phiếu',
                'value' => VendorReport::count(),
                'icon' => 'pi pi-file',
                'severity' => 'info',
                'onClick' => '/vendor-reports',
            ],
            [
                'id' => 'active_users',
                'title' => 'Người dùng',
                'value' => User::where('is_active', true)->count(),
                'subtitle' => '/ '.User::count().' tổng số',
                'icon' => 'pi pi-users',
                'severity' => 'success',
                'onClick' => '/users',
            ],
            [
                'id' => 'active_departments',
                'title' => 'Phòng ban',
                'value' => Department::where('is_active', true)->count(),
                'icon' => 'pi pi-building',
                'severity' => 'secondary',
                'onClick' => '/departments',
            ],
            [
                'id' => 'stuck_reports',
                'title' => 'Phiếu cần xử lý',
                'value' => VendorReport::where('status', 'IN_APPROVAL')
                    ->where('submitted_at', '<', now()->subDays(7))
                    ->count(),
                'subtitle' => '> 7 ngày chưa duyệt',
                'icon' => 'pi pi-exclamation-triangle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getRequesterMetrics(User $user): array
    {
        return [
            [
                'id' => 'my_total',
                'title' => 'Phiếu của tôi',
                'value' => VendorReport::where('created_by', $user->id)->count(),
                'icon' => 'pi pi-file-edit',
                'severity' => 'info',
            ],
            [
                'id' => 'my_in_approval',
                'title' => 'Đang chờ duyệt',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'IN_APPROVAL')
                    ->count(),
                'icon' => 'pi pi-clock',
                'severity' => 'warn',
            ],
            [
                'id' => 'my_approved',
                'title' => 'Đã duyệt',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'APPROVED')
                    ->count(),
                'icon' => 'pi pi-check-circle',
                'severity' => 'success',
            ],
            [
                'id' => 'my_rejected',
                'title' => 'Bị từ chối',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'REJECTED')
                    ->count(),
                'icon' => 'pi pi-times-circle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getPurchasingAdminMetrics(User $user): array
    {
        return [
            [
                'id' => 'all_reports',
                'title' => 'Tổng phiếu',
                'value' => VendorReport::count(),
                'icon' => 'pi pi-chart-bar',
                'severity' => 'info',
            ],
            [
                'id' => 'in_approval',
                'title' => 'Đang duyệt',
                'value' => VendorReport::where('status', 'IN_APPROVAL')->count(),
                'icon' => 'pi pi-hourglass',
                'severity' => 'warn',
            ],
            [
                'id' => 'supervised',
                'title' => 'Phiếu theo dõi',
                'value' => VendorReport::where('purchasing_admin_id', $user->id)->count(),
                'icon' => 'pi pi-eye',
                'severity' => 'secondary',
            ],
            [
                'id' => 'need_attention',
                'title' => 'Cần chú ý',
                'value' => VendorReport::where('status', 'IN_APPROVAL')
                    ->where('submitted_at', '<', now()->subDays(5))
                    ->count(),
                'subtitle' => '> 5 ngày',
                'icon' => 'pi pi-exclamation-triangle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getAccountantMetrics(User $user): array
    {
        $totalApproved = VendorReport::where('status', 'APPROVED')->count();
        $approvedToday = VendorReport::where('status', 'APPROVED')
            ->whereDate('approved_at', today())
            ->count();
        $approvedThisWeek = VendorReport::where('status', 'APPROVED')
            ->whereBetween('approved_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $approvedThisMonth = VendorReport::where('status', 'APPROVED')
            ->whereMonth('approved_at', now()->month)
            ->whereYear('approved_at', now()->year)
            ->count();

        return [
            [
                'id' => 'all_approved',
                'title' => 'Tổng phiếu đã duyệt',
                'value' => $totalApproved,
                'icon' => 'pi pi-check-circle',
                'severity' => 'success',
            ],
            [
                'id' => 'approved_today',
                'title' => 'Duyệt hôm nay',
                'value' => $approvedToday,
                'icon' => 'pi pi-calendar',
                'severity' => 'info',
            ],
            [
                'id' => 'approved_this_week',
                'title' => 'Duyệt tuần này',
                'value' => $approvedThisWeek,
                'icon' => 'pi pi-calendar-plus',
                'severity' => 'secondary',
            ],
            [
                'id' => 'approved_this_month',
                'title' => 'Duyệt tháng này',
                'value' => $approvedThisMonth,
                'icon' => 'pi pi-chart-bar',
                'severity' => 'warn',
            ],
        ];
    }

    private function getApproverMetrics(User $user): array
    {
        $approvedToday = VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'APPROVED')
            ->whereDate('acted_at', today())
            ->count();

        $totalRejected = VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'REJECTED')
            ->count();

        $avgResponseTime = $this->calculateAvgResponseTime($user);

        return [
            $this->getPendingApprovalMetric($user),
            [
                'id' => 'approved_today',
                'title' => 'Duyệt hôm nay',
                'value' => $approvedToday,
                'icon' => 'pi pi-check',
                'severity' => 'success',
            ],
            [
                'id' => 'total_rejected',
                'title' => 'Đã từ chối',
                'value' => $totalRejected,
                'icon' => 'pi pi-times',
                'severity' => 'danger',
            ],
            [
                'id' => 'avg_response',
                'title' => 'Thời gian phản hồi TB',
                'value' => number_format($avgResponseTime, 1).' ngày',
                'icon' => 'pi pi-stopwatch',
                'severity' => $avgResponseTime < 2 ? 'success' : 'warn',
            ],
        ];
    }

    private function getPendingApprovalMetric(User $user): array
    {
        $count = VendorReportApprovalStep::pendingFor($user)->count();

        return [
            'id' => 'pending_approval',
            'title' => 'Chờ tôi duyệt',
            'value' => $count,
            'icon' => 'pi pi-bell',
            'severity' => $count > 0 ? 'danger' : 'success',
        ];
    }

    private function getDeptHeadMetrics(User $user): array
    {
        $deptId = $user->department_id;

        return [
            [
                'id' => 'dept_reports_total',
                'title' => 'Phiếu của phòng',
                'value' => VendorReport::whereHas('creator', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                })->count(),
                'icon' => 'pi pi-building',
                'severity' => 'secondary',
            ],
        ];
    }

    /**
     * Get pending actions list
     */
    public function getPendingActions(User $user): array
    {
        $approvalActions = $this->getPendingApprovalActions($user);
        $specialistActions = match (true) {
            $user->hasRole('admin_system') => $this->getStuckReports(),
            $user->hasRole('purchasing_admin') => $this->getApprovedReportsForPurchasingAdmin($user),
            $user->hasRole('accountant') => $this->getApprovedReportsForAccountant(),
            default => [],
        };

        return collect($approvalActions)->concat($specialistActions)->unique('id')->values()->all();
    }

    private function getPendingApprovalActions(User $user): array
    {
        $steps = VendorReportApprovalStep::with([
            'report:id,code,title,workflow_type,submitted_at,created_by',
            'report.creator:id,name,department_id',
            'report.creator.department:id,name',
            'assigneeUser:id,name',
        ])->pendingFor($user)->get();

        return $steps->map(function ($step) {
            $report = $step->report;
            $createdAt = $step->created_at;
            $now = now();

            // Calculate detailed pending time
            $totalMinutes = (int) $createdAt->diffInMinutes($now);
            $days = (int) floor($totalMinutes / (24 * 60));
            $hours = (int) floor(($totalMinutes % (24 * 60)) / 60);
            $minutes = $totalMinutes % 60;

            // Format pending time string
            $pendingTimeFormatted = '';
            if ($days > 0) {
                $pendingTimeFormatted .= "{$days} ngày ";
            }
            if ($hours > 0) {
                $pendingTimeFormatted .= "{$hours} giờ ";
            }
            if ($minutes > 0 || $pendingTimeFormatted === '') {
                $pendingTimeFormatted .= "{$minutes} phút";
            }

            return [
                'id' => $report->id,
                'action_type' => 'approval',
                'code' => $report->code,
                'title' => $report->title,
                'workflow_type' => $report->workflow_type,
                'workflow_type_label' => $report->getWorkflowTypeLabel(),
                'current_step_label' => $step->getStepKeyLabel(),
                'assignee_name' => $step->assigneeUser?->name,
                'days_pending' => $createdAt->diffInDays($now),
                'pending_time_formatted' => trim($pendingTimeFormatted),
                'submitted_at' => $report->submitted_at->format('d/m/Y'),
                'submitted_at_timestamp' => $report->submitted_at->timestamp,
                'department_name' => $report->creator?->department?->name,
                'creator_name' => $report->creator?->name,
                'requires_selection' => $step->requires_selection,
            ];
        })
            ->sortByDesc('submitted_at_timestamp') // Sort by submitted time, newest first
            ->values()
            ->all();
    }

    private function getStuckReports(): array
    {
        return VendorReport::with(['creator.department', 'currentStep.assigneeUser'])
            ->where('status', 'IN_APPROVAL')
            ->where('submitted_at', '<', now()->subDays(5))
            ->orderByDesc('submitted_at') // Mới nhất trước
            ->get()
            ->map(function ($report) {
                $submittedAt = $report->submitted_at;
                $now = now();

                // Calculate detailed pending time
                $totalMinutes = $submittedAt->diffInMinutes($now);
                $days = (int) floor($totalMinutes / (24 * 60));
                $hours = (int) floor(($totalMinutes % (24 * 60)) / 60);
                $minutes = (int) ($totalMinutes % 60);

                // Format pending time string
                $pendingTimeFormatted = '';
                if ($days > 0) {
                    $pendingTimeFormatted .= "{$days} ngày ";
                }
                if ($hours > 0) {
                    $pendingTimeFormatted .= "{$hours} giờ ";
                }
                if ($minutes > 0 || $pendingTimeFormatted === '') {
                    $pendingTimeFormatted .= "{$minutes} phút";
                }

                return [
                    'id' => $report->id,
                    'action_type' => 'monitoring',
                    'code' => $report->code,
                    'title' => $report->title,
                    'workflow_type' => $report->workflow_type,
                    'workflow_type_label' => $report->getWorkflowTypeLabel(),
                    'current_step_label' => $report->currentStep?->getStepKeyLabel(),
                    'assignee_name' => $report->currentStep?->assigneeUser?->name,
                    'days_pending' => $submittedAt->diffInDays($now),
                    'pending_time_formatted' => trim($pendingTimeFormatted),
                    'submitted_at' => $submittedAt->format('d/m/Y'),
                    'department_name' => $report->creator?->department?->name,
                ];
            })
            ->all();
    }

    private function getApprovedReportsForPurchasingAdmin(User $user): array
    {
        return VendorReport::visibleTo($user)->with(['creator.department'])
            ->where('status', 'APPROVED')
            ->orderByDesc('approved_at') // Mới duyệt trước
            ->limit(50) // Giới hạn 50 phiếu gần nhất
            ->get()
            ->map(function ($report) {
                $approvedAt = $report->approved_at;
                $now = now();

                // Calculate time since approved
                $totalMinutes = $approvedAt->diffInMinutes($now);
                $days = (int) floor($totalMinutes / (24 * 60));
                $hours = (int) floor(($totalMinutes % (24 * 60)) / 60);
                $minutes = (int) ($totalMinutes % 60);

                // Format time string
                $timeFormatted = '';
                if ($days > 0) {
                    $timeFormatted .= "{$days} ngày ";
                }
                if ($hours > 0) {
                    $timeFormatted .= "{$hours} giờ ";
                }
                if ($minutes > 0 || $timeFormatted === '') {
                    $timeFormatted .= "{$minutes} phút";
                }

                return [
                    'id' => $report->id,
                    'action_type' => 'approved',
                    'code' => $report->code,
                    'title' => $report->title,
                    'workflow_type' => $report->workflow_type,
                    'workflow_type_label' => $report->getWorkflowTypeLabel(),
                    'current_step_label' => 'Đã hoàn tất',
                    'assignee_name' => null,
                    'days_pending' => 0,
                    'pending_time_formatted' => "Đã duyệt {$timeFormatted} trước",
                    'submitted_at' => $report->submitted_at->format('d/m/Y'),
                    'submitted_at_timestamp' => $report->submitted_at->timestamp,
                    'department_name' => $report->creator?->department?->name,
                    'creator_name' => $report->creator?->name,
                    'requires_selection' => false,
                ];
            })
            ->all();
    }

    private function getApprovedReportsForAccountant(): array
    {
        return VendorReport::with(['creator.department'])
            ->where('status', 'APPROVED')
            ->orderByDesc('approved_at') // Mới duyệt trước
            ->limit(100) // Kế toán xem nhiều hơn (100 phiếu)
            ->get()
            ->map(function ($report) {
                $approvedAt = $report->approved_at;
                $now = now();

                // Calculate time since approved
                $totalMinutes = (int) $approvedAt->diffInMinutes($now);
                $days = (int) floor($totalMinutes / (24 * 60));
                $hours = (int) floor(($totalMinutes % (24 * 60)) / 60);
                $minutes = $totalMinutes % 60;

                // Format time string
                $timeFormatted = '';
                if ($days > 0) {
                    $timeFormatted .= "{$days} ngày ";
                }
                if ($hours > 0) {
                    $timeFormatted .= "{$hours} giờ ";
                }
                if ($minutes > 0 || $timeFormatted === '') {
                    $timeFormatted .= "{$minutes} phút";
                }

                return [
                    'id' => $report->id,
                    'action_type' => 'approved',
                    'code' => $report->code,
                    'title' => $report->title,
                    'workflow_type' => $report->workflow_type,
                    'workflow_type_label' => $report->getWorkflowTypeLabel(),
                    'current_step_label' => 'Đã hoàn tất',
                    'assignee_name' => null,
                    'days_pending' => 0,
                    'pending_time_formatted' => "Đã duyệt {$timeFormatted} trước",
                    'submitted_at' => $report->submitted_at->format('d/m/Y'),
                    'submitted_at_timestamp' => $report->submitted_at->timestamp,
                    'department_name' => $report->creator?->department?->name,
                    'creator_name' => $report->creator?->name,
                    'requires_selection' => false,
                ];
            })
            ->all();
    }

    /**
     * Get quick actions based on user permissions
     */
    public function getQuickActions(User $user): array
    {
        $actions = [];

        // Common: Create report
        $actions[] = [
            'id' => 'create_report',
            'label' => 'Tạo phiếu mới',
            'icon' => 'pi pi-plus',
            'route' => '/vendor-reports/create',
            'severity' => 'success',
        ];

        // Admin actions
        if ($user->hasRole('admin_system')) {
            $actions = array_merge($actions, [
                [
                    'id' => 'manage_users',
                    'label' => 'Quản lý users',
                    'icon' => 'pi pi-users',
                    'route' => '/users',
                    'severity' => 'secondary',
                ],
                [
                    'id' => 'manage_departments',
                    'label' => 'Quản lý phòng ban',
                    'icon' => 'pi pi-building',
                    'route' => '/departments',
                    'severity' => 'secondary',
                ],
                [
                    'id' => 'activity_logs',
                    'label' => 'Nhật ký hệ thống',
                    'icon' => 'pi pi-history',
                    'route' => '/activity-logs',
                    'severity' => 'secondary',
                ],
            ]);
        }

        // My reports
        $actions[] = [
            'id' => 'my_reports',
            'label' => 'Phiếu của tôi',
            'icon' => 'pi pi-file',
            'route' => '/vendor-reports?created_by='.$user->id,
            'severity' => 'secondary',
        ];

        // Dept head actions
        if ($this->isDeptHead($user)) {
            $actions[] = [
                'id' => 'dept_reports',
                'label' => 'Phiếu của phòng',
                'icon' => 'pi pi-building',
                'route' => '/vendor-reports?department='.$user->department_id,
                'severity' => 'secondary',
            ];
        }

        return $actions;
    }

    /**
     * Get chart data
     */
    public function getChartData(User $user, string $type, string $period = 'month'): array
    {
        return match ($type) {
            'status' => $this->getStatusChartData($user),
            'workflow' => $this->getWorkflowChartData($user),
            'trend' => $this->getTrendChartData($user, $period),
            'department' => $this->getDepartmentChartData($user),
            default => [],
        };
    }

    private function getStatusChartData(User $user): array
    {
        $query = VendorReport::select('status', DB::raw('count(*) as count'))
            ->groupBy('status');

        // Filter by user permissions
        if (! $user->canViewAllReports()) {
            $query->where('created_by', $user->id);
        }

        $data = $query->get();

        // Status labels mapping
        $statusLabels = [
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Đã gửi',
            'IN_APPROVAL' => 'Đang duyệt',
            'APPROVED' => 'Đã duyệt',
            'REJECTED' => 'Từ chối',
            'CANCELED' => 'Hủy',
        ];

        // Status colors mapping (matching VendorReport::getStatusColor())
        $statusColors = [
            'DRAFT' => '#3b82f6',      // info - blue
            'SUBMITTED' => '#06b6d4',  // primary - cyan
            'IN_APPROVAL' => '#f59e0b', // warn - amber
            'APPROVED' => '#22c55e',    // success - green
            'REJECTED' => '#ef4444',    // danger - red
            'CANCELED' => '#64748b',    // secondary - slate
        ];

        // Build labels and colors in the same order as data
        $labels = [];
        $colors = [];
        foreach ($data as $item) {
            $labels[] = $statusLabels[$item->status] ?? $item->status;
            $colors[] = $statusColors[$item->status] ?? '#64748b';
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => $colors,
            ]],
        ];
    }

    private function getWorkflowChartData(User $user): array
    {
        $query = VendorReport::select('workflow_type', DB::raw('count(*) as count'))
            ->groupBy('workflow_type');

        // Filter by user permissions
        if (! $user->canViewAllReports()) {
            $query->where('created_by', $user->id);
        }

        $data = $query->get();

        return [
            'labels' => $data->pluck('workflow_type')->toArray(),
            'datasets' => [[
                'label' => 'Số lượng phiếu',
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => ['#3b82f6', '#f59e0b', '#8b5cf6', '#64748b', '#ef4444'],
            ]],
        ];
    }

    private function getTrendChartData(User $user, string $period): array
    {
        $months = 6;
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $year = $sqlite ? "CAST(strftime('%Y', created_at) AS INTEGER)" : 'YEAR(created_at)';
        $month = $sqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';

        // Build query with groupBy to reduce queries from 12 to 1
        $query = VendorReport::select(
            DB::raw("{$year} as year"),
            DB::raw("{$month} as month"),
            'status',
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', $startDate)
            ->whereIn('status', ['APPROVED', 'REJECTED'])
            ->groupBy('year', 'month', 'status');

        // Filter by user permissions
        if (! $user->canViewAllReports()) {
            $query->where('created_by', $user->id);
        }

        $rawData = $query->get();

        // Build arrays for chart
        $labels = [];
        $approvedData = [];
        $rejectedData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M');

            $approved = $rawData->first(function ($item) use ($date) {
                return $item->year == $date->year
                    && $item->month == $date->month
                    && $item->status === 'APPROVED';
            });

            $rejected = $rawData->first(function ($item) use ($date) {
                return $item->year == $date->year
                    && $item->month == $date->month
                    && $item->status === 'REJECTED';
            });

            $approvedData[] = $approved ? $approved->count : 0;
            $rejectedData[] = $rejected ? $rejected->count : 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Đã duyệt',
                    'data' => $approvedData,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Từ chối',
                    'data' => $rejectedData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    private function getDepartmentChartData(User $user): array
    {
        $data = VendorReport::select('departments.name', DB::raw('count(*) as count'))
            ->join('users', 'vendor_reports.created_by', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->pluck('name')->toArray(),
            'datasets' => [[
                'label' => 'Số phiếu',
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => '#3b82f6',
            ]],
        ];
    }

    /**
     * Get recent activities
     */
    public function getActivities(User $user, int $limit = 15, ?string $eventFilter = null): array
    {
        $query = Activity::with('causer:id,name', 'subject:id,code')
            ->where('subject_type', (new VendorReport)->getMorphClass())
            ->orderByDesc('created_at')
            ->limit($limit);

        $query->whereIn('subject_id', VendorReport::visibleTo($user)->select('vendor_reports.id'));

        if ($eventFilter) {
            $allowedEvents = ['created', 'submitted', 'approved', 'rejected', 'cancelled'];
            if (in_array($eventFilter, $allowedEvents)) {
                $query->where(function ($events) use ($eventFilter) {
                    $events->where('event', $eventFilter)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('event')
                            ->where('description', $eventFilter));
                });
            }
        }

        return $query->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'event' => $activity->event ?? $activity->description,
                'description' => $activity->description,
                'causer_name' => $activity->causer?->name ?? 'System',
                'report_code' => $activity->subject?->code ?? 'N/A',
                'report_id' => $activity->subject_id,
                'created_at' => $activity->created_at->toISOString(),
            ];
        })->all();
    }

    /**
     * Check if user is department head
     */
    public function isDeptHead(User $user): bool
    {
        return $user->department
            && $user->department->head_user_id === $user->id;
    }

    // Helper methods

    private function calculateAvgResponseTime(User $user): float
    {
        $hours = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST((julianday(acted_at) - julianday(created_at)) * 24 AS INTEGER)'
            : 'TIMESTAMPDIFF(HOUR, created_at, acted_at)';

        $avgHours = VendorReportApprovalStep::where('acted_by', $user->id)
            ->whereNotNull('acted_at')
            ->selectRaw("AVG({$hours}) as avg_hours")
            ->value('avg_hours');

        return $avgHours ? ($avgHours / 24) : 0;
    }
}
