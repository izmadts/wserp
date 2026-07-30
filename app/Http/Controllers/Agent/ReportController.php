<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\AgentCommissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $agent = Auth::user();

        $totalCustomers = Customer::where('created_by_agent_id', $agent->id)->count();
        $activeCustomers = Customer::where('created_by_agent_id', $agent->id)->where('is_active', true)->count();

        $totalSales = Sale::where('agent_id', $agent->id)->sum('total_amount');
        $totalCommission = AgentCommissionLog::where('agent_id', $agent->id)->sum('amount');

        $monthlySales = Sale::where('agent_id', $agent->id)
            ->whereMonth('sale_date', date('m'))
            ->whereYear('sale_date', date('Y'))
            ->sum('total_amount');

        $monthlyCommission = AgentCommissionLog::where('agent_id', $agent->id)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->sum('amount');

        return view('agent.reports.index', compact(
            'totalCustomers',
            'activeCustomers',
            'totalSales',
            'totalCommission',
            'monthlySales',
            'monthlyCommission'
        ));
    }

    public function sales(Request $request)
    {
        $agent = Auth::user();

        $query = Sale::where('agent_id', $agent->id)->with('customer');

        if ($request->from_date) {
            $query->whereDate('sale_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('sale_date', '<=', $request->to_date);
        }

        if ($request->status && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        $sales = $query->orderBy('sale_date', 'desc')->paginate(20);

        $summary = [
            'total_amount' => $query->sum('total_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'total_due' => $query->sum('due_amount'),
            'count' => $query->count(),
        ];

        return view('agent.reports.sales', compact('sales', 'summary'));
    }

    public function commission(Request $request)
    {
        $agent = Auth::user();

        $query = AgentCommissionLog::where('agent_id', $agent->id)->with('sale');

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->type && $request->type != 'all') {
            $query->where('reference_type', $request->type);
        }

        $commissions = $query->orderBy('created_at', 'desc')->paginate(20);

        $summary = [
            'total_earned' => $query->sum('amount'),
            'total_paid' => $query->where('is_paid', true)->sum('amount'),
            'total_due' => $query->where('is_paid', false)->sum('amount'),
            'count' => $query->count(),
        ];

        return view('agent.reports.commission', compact('commissions', 'summary'));
    }

    public function target(Request $request)
    {
        $agent = Auth::user();

        // Get current month's sales
        $currentMonthSales = Sale::where('agent_id', $agent->id)
            ->whereMonth('sale_date', date('m'))
            ->whereYear('sale_date', date('Y'))
            ->sum('total_amount');

        $targetAmount = $agent->sales_target[date('Y')][date('m')] ?? 0;
        $achievement = $targetAmount > 0 ? ($currentMonthSales / $targetAmount) * 100 : 0;

        // Calculate bonus
        $bonus = 0;
        if ($achievement >= 150) {
            $bonus = 20000;
        } elseif ($achievement >= 120) {
            $bonus = 10000;
        } elseif ($achievement >= 100) {
            $bonus = 5000;
        }

        // Get monthly breakdown
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthSales = Sale::where('agent_id', $agent->id)
                ->whereMonth('sale_date', $i)
                ->whereYear('sale_date', date('Y'))
                ->sum('total_amount');

            $monthTarget = $agent->sales_target[date('Y')][$i] ?? 0;
            $monthAchievement = $monthTarget > 0 ? ($monthSales / $monthTarget) * 100 : 0;

            $monthlyData[] = [
                'month' => date('F', mktime(0, 0, 0, $i, 1)),
                'target' => $monthTarget,
                'achieved' => $monthSales,
                'achievement' => $monthAchievement,
                'bonus' => $this->calculateBonus($monthAchievement),
            ];
        }

        return view('agent.reports.target', compact(
            'currentMonthSales',
            'targetAmount',
            'achievement',
            'bonus',
            'monthlyData'
        ));
    }

    private function calculateBonus($achievement)
    {
        if ($achievement >= 150) return 20000;
        if ($achievement >= 120) return 10000;
        if ($achievement >= 100) return 5000;
        return 0;
    }
}