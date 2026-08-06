<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\LuckyDrawEntry;
use App\Models\LuckyDrawCampaign;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoldenClubController extends Controller
{
    public function dashboard()
    {
        $today = now()->toDateString();

        $stats = [
            'todays_registrations' => Customer::whereDate('created_at', $today)->count(),
            'new_customers' => Customer::where('is_active', true)->where('order_count', '>=', 3)->count(),
            'silver' => Customer::where('membership_level', 'silver')->count(),
            'gold' => Customer::where('membership_level', 'gold')->count(),
            'platinum' => Customer::where('membership_level', 'platinum')->count(),
            'pending_verification' => Customer::where('is_agent_customer', true)->where('otp_verified', false)->count(),
            'todays_orders' => \App\Models\Sale::whereDate('sale_date', $today)->count(),
            'todays_recovery' => \App\Models\SalePayment::whereDate('payment_date', $today)->sum('amount'),
            'points_issued' => LoyaltyTransaction::where('type', 'earn')->sum('points'),
            'lucky_draw_entries' => LuckyDrawEntry::sum('entry_count'),
        ];

        $topCities = Customer::select('city', DB::raw('count(*) as total'))
            ->whereNotNull('city')->where('city', '!=', '')
            ->groupBy('city')->orderByDesc('total')->limit(5)->get();

        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc('total_quantity')->limit(5)->get();

        $topCustomers = Customer::orderByDesc('lifetime_purchase')->limit(5)->get(['id', 'name', 'lifetime_purchase', 'membership_level']);

        $topSalesmen = \App\Models\User::where('role', 'sales_agent')
            ->withCount(['customers as agent_customers_count' => fn ($q) => $q->where('is_agent_customer', true)])
            ->orderByDesc('agent_customers_count')
            ->limit(5)
            ->get(['id', 'name']);

        return view('admin.golden-club.dashboard', compact('stats', 'topCities', 'topProducts', 'topCustomers', 'topSalesmen'));
    }

    public function customers(Request $request)
    {
        $query = Customer::query();

        if ($request->membership_level && $request->membership_level != 'all') {
            $query->where('membership_level', $request->membership_level);
        }

        if ($request->verified == '1') {
            $query->where('otp_verified', true);
        } elseif ($request->verified == '0') {
            $query->where('otp_verified', false);
        }

        $customers = $query->orderByDesc('lifetime_purchase')->paginate(30);

        return view('admin.golden-club.customers.index', compact('customers'));
    }

    public function customerShow(Customer $customer)
    {
        $customer->load(['loyaltyTransactions' => fn ($q) => $q->orderByDesc('created_at')->limit(20), 'luckyDrawEntries.campaign', 'redeemedRewards.reward', 'referrals']);
        $timeline = $customer->getTimeline();

        return view('admin.golden-club.customers.show', compact('customer', 'timeline'));
    }

    public function pendingVerification()
    {
        $customers = Customer::where('is_agent_customer', true)
            ->where('otp_verified', false)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.golden-club.customers.pending', compact('customers'));
    }

    /**
     * Manual OTP verification: no SMS gateway is integrated in this
     * project, so there's no automated OTP-send/check flow yet - admin
     * verifies the customer's registration directly here instead. This is
     * the "Salesman Rule" gate from the policy doc: unverified agent
     * customers earn their agent zero commission until this happens.
     */
    public function verifyCustomer(Customer $customer)
    {
        $customer->update(['otp_verified' => true]);

        NotificationHelper::send(
            $customer->id,
            'Account Verified',
            'Your Golden Club membership has been verified. You can now earn points on every purchase!',
            'registration'
        );

        return back()->with('success', "{$customer->name} verified successfully!");
    }

    public function campaigns()
    {
        $campaigns = LuckyDrawCampaign::withCount('entries')->orderByDesc('created_at')->get();
        return view('admin.golden-club.lucky-draw.campaigns', compact('campaigns'));
    }

    public function campaignCreate()
    {
        return view('admin.golden-club.lucky-draw.campaign-create');
    }

    public function campaignStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'entry_amount' => 'required|numeric|min:1',
            'entry_count' => 'required|integer|min:1',
            'status' => 'required|in:draft,active,completed,cancelled',
        ]);

        LuckyDrawCampaign::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'minimum_purchase' => $validated['minimum_purchase'] ?? 0,
            'entry_formula' => ['amount' => (float) $validated['entry_amount'], 'entries' => (int) $validated['entry_count']],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.golden-club.lucky-draw.campaigns')->with('success', 'Campaign created successfully!');
    }

    public function campaignEdit(LuckyDrawCampaign $campaign)
    {
        return view('admin.golden-club.lucky-draw.campaign-edit', compact('campaign'));
    }

    public function campaignUpdate(Request $request, LuckyDrawCampaign $campaign)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'entry_amount' => 'required|numeric|min:1',
            'entry_count' => 'required|integer|min:1',
            'status' => 'required|in:draft,active,completed,cancelled',
        ]);

        $campaign->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'minimum_purchase' => $validated['minimum_purchase'] ?? 0,
            'entry_formula' => ['amount' => (float) $validated['entry_amount'], 'entries' => (int) $validated['entry_count']],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.golden-club.lucky-draw.campaigns')->with('success', 'Campaign updated successfully!');
    }

    public function winners()
    {
        $winners = LuckyDrawEntry::winners()->with('customer', 'campaign')->orderByDesc('updated_at')->get();
        $campaigns = LuckyDrawCampaign::orderByDesc('created_at')->get();

        return view('admin.golden-club.lucky-draw.winners', compact('winners', 'campaigns'));
    }

    /**
     * Randomly draws one winner from a campaign's not-yet-won entries,
     * weighted by entry_count (a customer with 5 entries is 5x as likely
     * to be picked as one with 1) - matches how a physical raffle with
     * multiple tickets per entrant would work.
     */
    public function drawWinner(LuckyDrawCampaign $campaign)
    {
        $pool = LuckyDrawEntry::where('campaign_id', $campaign->id)
            ->where('is_winner', false)
            ->get();

        $totalTickets = $pool->sum('entry_count');
        if ($totalTickets <= 0) {
            return back()->with('error', 'No eligible entries to draw from!');
        }

        $ticket = random_int(1, (int) $totalTickets);
        $cumulative = 0;
        $winningEntry = null;
        foreach ($pool as $entry) {
            $cumulative += $entry->entry_count;
            if ($ticket <= $cumulative) {
                $winningEntry = $entry;
                break;
            }
        }

        if (!$winningEntry) {
            return back()->with('error', 'Could not determine a winner - please try again.');
        }

        $winningEntry->update(['is_winner' => true]);

        NotificationHelper::send(
            $winningEntry->customer_id,
            'Congratulations! You Won!',
            "You've won the \"{$campaign->title}\" lucky draw! Our team will contact you about your prize.",
            'lucky_draw'
        );

        return back()->with('success', $winningEntry->customer->name . ' has been drawn as the winner!');
    }
}
