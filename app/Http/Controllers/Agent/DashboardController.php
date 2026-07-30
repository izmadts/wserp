<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\AgentCommissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $agent = Auth::user();
        
        if (!$agent->isSalesAgent()) {
            abort(403, 'Unauthorized access.');
        }

        // Get agent's customers
        $customers = Customer::where('created_by_agent_id', $agent->id)->get();
        
        // Get agent's sales
        $sales = Sale::where('agent_id', $agent->id)->get();
        
        // Current month stats
        $currentMonthSales = Sale::where('agent_id', $agent->id)
            ->whereMonth('sale_date', date('m'))
            ->whereYear('sale_date', date('Y'))
            ->get();
        
        $currentMonthTotal = $currentMonthSales->sum('total_amount');
        $currentMonthCommission = $currentMonthSales->sum('commission_amount');
        $currentMonthPaid = $currentMonthSales->sum('paid_amount');
        
        // Total stats
        $totalSales = $sales->sum('total_amount');
        $totalCommission = $sales->sum('commission_amount');
        $totalPaid = $sales->sum('paid_amount');
        $totalDue = $sales->sum('due_amount');
        
        // Recovery rate
        $recoveryRate = $totalSales > 0 ? ($totalPaid / $totalSales) * 100 : 0;
        
        // Commission logs
        $commissionLogs = AgentCommissionLog::where('agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // New customers this month
        $newCustomers = Customer::where('created_by_agent_id', $agent->id)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->count();
        
        return view('agent.dashboard', compact(
            'agent',
            'customers',
            'sales',
            'currentMonthTotal',
            'currentMonthCommission',
            'currentMonthPaid',
            'totalSales',
            'totalCommission',
            'totalPaid',
            'totalDue',
            'recoveryRate',
            'commissionLogs',
            'newCustomers'
        ));
    }
}