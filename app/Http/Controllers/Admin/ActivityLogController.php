<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filters
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

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);
        
        // Get filter options
        $users = User::orderBy('name')->get();
        $modules = ActivityLog::distinct()->pluck('module');
        $actions = ActivityLog::distinct()->pluck('action');

        // Summary stats
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::whereDate('created_at', today())->count();
        $thisMonthLogs = ActivityLog::whereMonth('created_at', date('m'))->count();
        $uniqueUsers = ActivityLog::distinct('user_id')->count();

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
        $days = $request->days ?? 30;
        ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return back()->with('success', 'Activity logs older than ' . $days . ' days cleared!');
    }

    public function clearAll()
    {
        ActivityLog::truncate();
        return back()->with('success', 'All activity logs cleared!');
    }

    public function export(Request $request)
    {
        // Implementation similar to other exports
        // ... 
    }
}