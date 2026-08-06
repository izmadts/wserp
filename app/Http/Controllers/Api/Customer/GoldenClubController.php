<?php

namespace App\Http\Controllers\Api\Customer;

use App\Models\Reward;
use App\Models\LoyaltyTransaction;
use App\Models\RedeemedReward;
use Illuminate\Http\Request;

class GoldenClubController extends ApiController
{
    /**
     * Standing summary + recent points activity. The same fields already
     * ride along on GET /me under "golden_club" - this exists for a
     * dedicated Golden Club screen that also wants transaction history
     * without pulling the full profile payload.
     */
    public function summary()
    {
        $customer = $this->customer();

        return $this->success([
            'membership_level' => $customer->membership_level,
            'loyalty_points' => (float) $customer->loyalty_points,
            'lucky_draw_entries' => (int) $customer->lucky_draw_entries,
            'total_purchase' => (float) $customer->total_purchase,
            'lifetime_purchase' => (float) $customer->lifetime_purchase,
            'customer_rank' => (int) $customer->customer_rank,
            'recent_points_activity' => LoyaltyTransaction::where('customer_id', $customer->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'points', 'type', 'remarks', 'created_at']),
            'timeline' => $customer->getTimeline(),
        ]);
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
            'can_afford' => (float) $this->customer()->loyalty_points >= (float) $r->points_required,
        ]);

        return $this->success($data);
    }

    /**
     * Self-service redemption - lands as "pending" for admin approval, the
     * same outcome as an agent redeeming on the customer's behalf
     * (Api\Agent\GoldenClubController::redeemReward), EXCEPT
     * lucky_draw_entry rewards - those have no physical fulfillment step,
     * so Reward::redeem() delivers them immediately. No customer_id in the
     * body: it's always the authenticated customer, never someone else's.
     */
    public function redeem(Request $request, Reward $reward)
    {
        $customer = $this->customer();

        try {
            $redeemedReward = $reward->redeem($customer->id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        $message = $redeemedReward->status === 'delivered'
            ? 'Redeemed successfully!'
            : 'Redemption request submitted - pending admin approval.';

        return $this->success(null, $message);
    }

    public function redemptions(Request $request)
    {
        $redemptions = RedeemedReward::where('customer_id', $this->customer()->id)
            ->with('reward:id,title,image,reward_type')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return $this->paginated($redemptions, fn ($r) => [
            'id' => $r->id,
            'reward' => $r->reward ? ['id' => $r->reward->id, 'title' => $r->reward->title, 'image' => $r->reward->image] : null,
            'points_used' => (float) $r->points_used,
            'status' => $r->status,
            'status_label' => $r->status_label,
            'created_at' => optional($r->created_at)->toIso8601String(),
        ]);
    }
}
