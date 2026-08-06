<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\Customer;
use App\Models\Reward;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoldenClubController extends ApiController
{
    public function dashboard()
    {
        $agentId = $this->agent()->id;
        $customers = Customer::where('created_by_agent_id', $agentId);

        return $this->success([
            'total_customers' => (clone $customers)->count(),
            'verified' => (clone $customers)->where('otp_verified', true)->count(),
            'pending_verification' => (clone $customers)->where('otp_verified', false)->count(),
            'silver' => (clone $customers)->where('membership_level', 'silver')->count(),
            'gold' => (clone $customers)->where('membership_level', 'gold')->count(),
            'platinum' => (clone $customers)->where('membership_level', 'platinum')->count(),
            'total_points_issued' => (float) (clone $customers)->sum('loyalty_points'),
            'total_lucky_draw_entries' => (int) (clone $customers)->sum('lucky_draw_entries'),
        ]);
    }

    public function customers(Request $request)
    {
        $customers = Customer::where('created_by_agent_id', $this->agent()->id)
            ->orderByDesc('lifetime_purchase')
            ->paginate($request->input('per_page', 30));

        return $this->paginated($customers, fn ($c) => new CustomerResource($c));
    }

    public function rewards()
    {
        $rewards = Reward::active()->orderBy('points_required')->get();

        $data = $rewards->map(fn ($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'description' => $r->description,
            'image' => $r->image,
            'reward_type' => $r->reward_type,
            'reward_type_label' => $r->reward_type_label,
            'points_required' => (float) $r->points_required,
            'stock' => (int) $r->stock,
            'in_stock' => $r->isInStock(),
        ]);

        return $this->success($data);
    }

    /**
     * No customer self-service portal exists yet, so redemption requests
     * are entered by the agent on the customer's behalf - same flow as the
     * web agent.golden-club.rewards.redeem route. Creates a pending
     * RedeemedReward for admin to approve/fulfill.
     */
    public function redeemReward(Request $request, Reward $reward)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $customer = Customer::where('id', $request->customer_id)
            ->where('created_by_agent_id', $this->agent()->id)
            ->first();

        if (!$customer) {
            return $this->error('Customer not found or not accessible.', 404);
        }

        try {
            $redeemedReward = $reward->redeem($customer->id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        $message = $redeemedReward->status === 'delivered'
            ? "Redeemed for {$customer->name} successfully!"
            : "Redemption request submitted for {$customer->name} - pending admin approval.";

        return $this->success(null, $message);
    }
}
