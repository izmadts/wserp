<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoldenClubController extends Controller
{
    public function dashboard()
    {
        $agentId = Auth::id();

        $customers = Customer::where('created_by_agent_id', $agentId);

        $stats = [
            'total_customers' => (clone $customers)->count(),
            'verified' => (clone $customers)->where('otp_verified', true)->count(),
            'pending_verification' => (clone $customers)->where('otp_verified', false)->count(),
            'silver' => (clone $customers)->where('membership_level', 'silver')->count(),
            'gold' => (clone $customers)->where('membership_level', 'gold')->count(),
            'platinum' => (clone $customers)->where('membership_level', 'platinum')->count(),
            'total_points_issued' => (clone $customers)->sum('loyalty_points'),
            'total_lucky_draw_entries' => (clone $customers)->sum('lucky_draw_entries'),
        ];

        return view('agent.golden-club.dashboard', compact('stats'));
    }

    public function customers()
    {
        $customers = Customer::where('created_by_agent_id', Auth::id())
            ->orderByDesc('lifetime_purchase')
            ->paginate(30);

        return view('agent.golden-club.customers', compact('customers'));
    }

    public function rewards()
    {
        $rewards = Reward::active()->orderBy('points_required')->get();

        $customers = Customer::where('created_by_agent_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'loyalty_points']);

        return view('agent.golden-club.rewards', compact('rewards', 'customers'));
    }

    /**
     * No customer self-service portal exists yet, so redemption requests
     * are entered by the agent on the customer's behalf (they're the one
     * physically in front of the customer). Creates a pending
     * RedeemedReward for admin to approve/fulfill - doesn't hand over the
     * physical item itself.
     */
    public function redeemReward(Request $request, Reward $reward)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $customer = Customer::where('id', $validated['customer_id'])
            ->where('created_by_agent_id', Auth::id())
            ->firstOrFail();

        try {
            $redeemedReward = $reward->redeem($customer->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $redeemedReward->status === 'delivered'
            ? "Redeemed for {$customer->name} successfully!"
            : "Redemption request submitted for {$customer->name} - pending admin approval.";

        return back()->with('success', $message);
    }
}
