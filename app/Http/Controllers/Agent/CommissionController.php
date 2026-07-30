<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionLog;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    public function index()
    {
        $agent = Auth::user();

        // Get all commission logs
        $commissions = AgentCommissionLog::where('agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Summary stats
        $totalEarned = AgentCommissionLog::where('agent_id', $agent->id)->sum('amount');
        $totalPaid = AgentCommissionLog::where('agent_id', $agent->id)->where('is_paid', true)->sum('amount');
        $totalDue = $totalEarned - $totalPaid;

        // Current month summary
        $monthEarned = AgentCommissionLog::where('agent_id', $agent->id)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->sum('amount');

        // Breakdown by type
        $saleCommissions = AgentCommissionLog::where('agent_id', $agent->id)
            ->where('reference_type', 'sale')
            ->sum('amount');

        $bonusCommissions = AgentCommissionLog::where('agent_id', $agent->id)
            ->where('reference_type', 'new_customer_bonus')
            ->orWhere('reference_type', 'target_bonus')
            ->orWhere('reference_type', 'recovery_bonus')
            ->sum('amount');

        return view('agent.commissions.index', compact(
            'commissions',
            'totalEarned',
            'totalPaid',
            'totalDue',
            'monthEarned',
            'saleCommissions',
            'bonusCommissions'
        ));
    }

    public function details(Request $request)
    {
        $agent = Auth::user();

        $query = AgentCommissionLog::where('agent_id', $agent->id);

        if ($request->type && $request->type != 'all') {
            $query->where('reference_type', $request->type);
        }

        if ($request->status && $request->status != 'all') {
            $query->where('is_paid', $request->status == 'paid');
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $commissions = $query->orderBy('created_at', 'desc')->paginate(30);

        $types = [
            'sale' => 'Sale Commission',
            'new_customer_bonus' => 'New Customer Bonus',
            'target_bonus' => 'Target Bonus',
            'recovery_bonus' => 'Recovery Bonus'
        ];

        return view('agent.commissions.details', compact('commissions', 'types'));
    }
}