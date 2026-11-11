<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ActivityLogController extends Controller
{
    private function filteredQuery(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->module && $request->module != 'all') {
            $query->where('module', $request->module);
        }

        if ($request->action && $request->action != 'all') {
            $query->where('action', $request->action);
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $logs = $this->filteredQuery($request)->orderBy('created_at', 'desc')->paginate(50);
        
        // Get filter options
        $users = User::orderBy('name')->get();
        $modules = ActivityLog::distinct()->pluck('module');
        $actions = ActivityLog::distinct()->pluck('action');

        // Summary stats - built from the SAME filtered query as $logs above
        // (not a fresh, unfiltered ActivityLog::), so they actually change
        // when the User/Module/Action/date filters are applied instead of
        // always showing the grand total for every log that's ever existed.
        $totalLogs = $this->filteredQuery($request)->count();
        $todayLogs = $this->filteredQuery($request)->whereDate('created_at', today())->count();
        $thisMonthLogs = $this->filteredQuery($request)->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count();
        $uniqueUsers = $this->filteredQuery($request)->distinct('user_id')->count('user_id');

        return view('admin.activity-logs.index', compact(
            'logs', 'users', 'modules', 'actions',
            'totalLogs', 'todayLogs', 'thisMonthLogs', 'uniqueUsers'
        ));
    }

    public function show(ActivityLog $activityLog)
    {
        $activityLog->load('user');
        return view('admin.activity-logs.show', compact('activityLog'));
    }

    public function clear(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:3650',
        ]);
        $days = $validated['days'] ?? 30;
        ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return back()->with('success', 'Activity logs older than ' . $days . ' days cleared!');
    }

    public function clearAll()
    {
        ActivityLog::truncate();
        return back()->with('success', 'All activity logs cleared!');
    }

    public function export(Request $request, $format = 'csv')
    {
        if ($format !== 'csv') {
            abort(404);
        }

        $logs = $this->filteredQuery($request)->orderBy('created_at', 'desc')->limit(5000)->get();

        $filename = 'activity_logs_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'User', 'Role', 'Module', 'Action', 'Description', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user_name ?? $log->user->name ?? 'System',
                    $log->user_role,
                    $log->module,
                    $log->action,
                    $log->description,
                    $log->ip_address,
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}