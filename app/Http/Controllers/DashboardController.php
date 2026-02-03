<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Http\Resources\VendorReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Display dashboard page
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get initial data
        $metrics = $this->dashboardService->getMetrics($user);
        $pendingActions = $this->dashboardService->getPendingActions($user);
        $quickActions = $this->dashboardService->getQuickActions($user);
        $activities = $this->dashboardService->getActivities($user, limit: 10);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'pendingActions' => $pendingActions,
            'quickActions' => $quickActions,
            'activities' => $activities,
            'userRoles' => $user->getRoleNames()->toArray(),
            'isDeptHead' => $this->dashboardService->isDeptHead($user),
        ]);
    }

    /**
     * Get metrics data (API endpoint)
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $metrics = $this->dashboardService->getMetrics($user);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get pending actions (API endpoint)
     */
    public function getPendingActions(Request $request): JsonResponse
    {
        $user = $request->user();
        $actions = $this->dashboardService->getPendingActions($user);

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    /**
     * Get chart data (API endpoint)
     */
    public function getChartData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:status,workflow,trend,department',
            'period' => 'nullable|in:week,month,quarter,year',
        ]);

        $user = $request->user();
        $chartData = $this->dashboardService->getChartData(
            $user,
            $validated['type'],
            $validated['period'] ?? 'month'
        );

        return response()->json([
            'success' => true,
            'data' => $chartData,
        ]);
    }

    /**
     * Get activities (API endpoint)
     */
    public function getActivities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'event' => 'nullable|string|in:created,submitted,approved,rejected,cancelled',
        ]);

        $user = $request->user();
        $activities = $this->dashboardService->getActivities(
            $user,
            $validated['limit'] ?? 15,
            $validated['event'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }
}
