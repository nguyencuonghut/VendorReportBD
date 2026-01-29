<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ActivityLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the activity logs.
     */
    public function index(Request $request)
    {
        // Only Super Admin can view activity logs
        $this->authorize('viewAny', Activity::class);

        $query = Activity::with('causer', 'subject');

        // Filter by causer (user)
        if ($request->has('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        // Filter by subject type
        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $activities = $query->latest()->paginate(20);

        return inertia('ActivityLogIndex', [
            'activities' => $activities,
            'filters' => $request->only(['causer_id', 'subject_type', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Display the specified activity log.
     */
    public function show(Activity $activity)
    {
        $this->authorize('view', $activity);

        $activity->load('causer', 'subject');

        return inertia('ActivityLogShow', [
            'activity' => $activity,
        ]);
    }

    /**
     * Remove the specified activity log from storage.
     */
    public function destroy(Activity $activity)
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return redirect()->route('activity-logs.index')->with([
            'message' => 'activityLog.deleteSuccess',
            'type' => 'success'
        ]);
    }

    /**
     * Remove all activity logs.
     */
    public function clear()
    {
        $this->authorize('deleteAny', Activity::class);

        Activity::truncate();

        return redirect()->route('activity-logs.index')->with([
            'message' => 'activityLog.clearSuccess',
            'type' => 'success'
        ]);
    }
}
