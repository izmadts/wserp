<?php

namespace App\Traits;

use App\Models\LoyaltyTransaction;
use App\Models\LuckyDrawEntry;
use App\Models\LuckyDrawCampaign;
use App\Models\RedeemedReward;
use App\Models\CustomerReferral;
use App\Models\GoldenClubSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait GoldenClubTrait
{
    /**
     * Get membership level based on total purchase
     */
    public function getMembershipLevelAttribute($value)
    {
        // Auto-upgrade logic - thresholds read from Settings > Golden Club
        // (admin-configurable) instead of being hardcoded, so the numbers
        // shown on that settings screen actually govern tier assignment.
        $totalPurchase = $this->total_purchase ?? 0;
        [$silverMin, $goldMin, $platinumMin] = self::membershipThresholds();

        if ($totalPurchase >= $platinumMin) {
            return 'platinum';
        } elseif ($totalPurchase >= $goldMin) {
            return 'gold';
        } elseif ($totalPurchase >= $silverMin) {
            return 'silver';
        }
        return $value ?? 'silver';
    }

    /**
     * [silver, gold, platinum] minimum-purchase thresholds, from
     * Settings > Golden Club (falling back to the seeded defaults).
     * Shared by getMembershipLevelAttribute() and checkMembershipUpgrade()
     * so both always agree.
     */
    private static function membershipThresholds(): array
    {
        $silver = GoldenClubSetting::get('membership_silver', ['minimum' => 100000])['minimum'] ?? 100000;
        $gold = GoldenClubSetting::get('membership_gold', ['minimum' => 500000])['minimum'] ?? 500000;
        $platinum = GoldenClubSetting::get('membership_platinum', ['minimum' => 1000000])['minimum'] ?? 1000000;

        return [$silver, $gold, $platinum];
    }

    /**
     * Check if customer is verified
     */
    public function getIsVerifiedAttribute()
    {
        return $this->otp_verified && $this->is_active;
    }

    /**
     * Calculate points based on purchase
     */
    public function calculatePoints($amount)
    {
        // get() already returns a decoded array, not a JSON string -
        // json_decode()'ing it again always failed and silently fell back.
        $pointFormula = GoldenClubSetting::get('point_formula', ['rate' => 100]);
        $rate = $pointFormula['rate'] ?? 100;
        return $rate > 0 ? $amount / $rate : 0;
    }

    /**
     * Months this batch of points should last, per Settings > Golden Club
     * > Points Expiry - a flat default that applies to everyone, with an
     * optional per-tier override (e.g. Platinum keeps points twice as long
     * as Silver, as a loyalty perk). Null means expiry is off entirely -
     * points earned while it's off never expire, even if it's switched on
     * later, since expires_at is only ever set at the moment points are
     * created, never retrofitted onto old rows.
     */
    private static function pointsExpiryMonths(string $tier): ?int
    {
        $settings = GoldenClubSetting::get('points_expiry', ['enabled' => false]);

        if (empty($settings['enabled'])) {
            return null;
        }

        return $settings['tier_months'][$tier] ?? $settings['default_months'] ?? 12;
    }

    /**
     * Add loyalty points
     */
    public function addLoyaltyPoints($points, $saleId = null, $remarks = null)
    {
        DB::transaction(function () use ($points, $saleId, $remarks) {
            $this->loyalty_points += $points;
            $this->save();

            // The tier read here is whatever this customer's membership_level
            // accessor resolves to RIGHT NOW - if this same sale just crossed
            // a tier threshold, total_purchase (and checkMembershipUpgrade,
            // below) has already reflected that by the time this runs, so a
            // newly-Platinum customer's first Platinum sale correctly earns
            // Platinum-length points, not their old tier's.
            $months = self::pointsExpiryMonths($this->membership_level);

            LoyaltyTransaction::create([
                'customer_id' => $this->id,
                'sale_id' => $saleId,
                'points' => $points,
                'type' => 'earn',
                'expires_at' => $months ? now()->addMonths($months) : null,
                'remarks' => $remarks ?? 'Points earned'
            ]);

            // Check for membership upgrade
            $this->checkMembershipUpgrade();
        });
    }

    /**
     * Redeem points for reward
     */
    public function redeemPoints($points, $rewardId, $remarks = null)
    {
        if ($this->loyalty_points < $points) {
            throw new \Exception('Insufficient points!');
        }

        return DB::transaction(function () use ($points, $rewardId, $remarks) {
            $this->loyalty_points -= $points;
            $this->save();

            LoyaltyTransaction::create([
                'customer_id' => $this->id,
                'points' => -$points,
                'type' => 'redeem',
                'remarks' => $remarks ?? 'Points redeemed'
            ]);

            return RedeemedReward::create([
                'customer_id' => $this->id,
                'reward_id' => $rewardId,
                'points_used' => $points,
                'status' => 'pending'
            ]);
        });
    }

    /**
     * Generate lucky draw entries
     */
    public function generateLuckyDrawEntries($amount, $saleId = null)
    {
        $activeCampaign = LuckyDrawCampaign::where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$activeCampaign) {
            return 0;
        }

        // The campaign's own minimum-purchase gate - previously captured on
        // the campaign form and saved, but never actually checked here, so
        // a campaign configured with e.g. "Rs. 50,000 minimum" still handed
        // out entries to any sale, however small.
        if ($activeCampaign->minimum_purchase && $amount < $activeCampaign->minimum_purchase) {
            return 0;
        }

        // entry_formula is cast to array on the model already - decoding it
        // again here always failed and fell through to the 20000 default.
        $formula = $activeCampaign->entry_formula ?? [];
        $entryPerAmount = $formula['amount'] ?? 20000;
        $entriesPerBlock = $formula['entries'] ?? 1;
        $blocks = $entryPerAmount > 0 ? floor($amount / $entryPerAmount) : 0;
        $entryCount = $blocks * $entriesPerBlock;

        if ($entryCount > 0) {
            LuckyDrawEntry::create([
                'customer_id' => $this->id,
                'campaign_id' => $activeCampaign->id,
                'sale_id' => $saleId,
                'purchase_amount' => $amount,
                'entry_count' => $entryCount,
                'is_winner' => false,
            ]);

            $this->lucky_draw_entries += $entryCount;
            $this->save();

            // Update campaign total entries
            $activeCampaign->total_entries += $entryCount;
            $activeCampaign->save();
        }

        return $entryCount;
    }

    /**
     * Check and upgrade membership
     */
    public function checkMembershipUpgrade()
    {
        $totalPurchase = $this->total_purchase ?? 0;

        // membership_level's accessor (getMembershipLevelAttribute, above)
        // always recomputes live from total_purchase/thresholds - comparing
        // it to a freshly-computed $newLevel below was comparing the same
        // formula's output to itself, so this could never detect a change.
        // The displayed level was always correct (it's live), but the
        // stored column - which Admin/Agent GoldenClubController query
        // directly for tier counts and the tier filter - never actually
        // updated, and no "Membership upgraded" log entry was ever created.
        // getRawOriginal() reads the actual stored column, bypassing the
        // accessor, so this now compares what's really in the database.
        $oldLevel = $this->getRawOriginal('membership_level', 'silver');
        [$silverMin, $goldMin, $platinumMin] = self::membershipThresholds();

        $newLevel = 'silver';
        if ($totalPurchase >= $platinumMin) {
            $newLevel = 'platinum';
        } elseif ($totalPurchase >= $goldMin) {
            $newLevel = 'gold';
        } elseif ($totalPurchase >= $silverMin) {
            $newLevel = 'silver';
        }

        if ($oldLevel != $newLevel) {
            $this->membership_level = $newLevel;
            $this->save();

            // Log membership upgrade
            LoyaltyTransaction::create([
                'customer_id' => $this->id,
                'points' => 0,
                'type' => 'bonus',
                'remarks' => "Membership upgraded to {$newLevel}"
            ]);
        }
    }

    /**
     * Get customer timeline
     */
    public function getTimeline()
    {
        $timeline = [];

        // Registration
        $timeline[] = [
            'date' => $this->created_at,
            'event' => 'Registered',
            'icon' => 'fa-user-plus',
            'color' => 'blue'
        ];

        // Verified
        if ($this->otp_verified) {
            $timeline[] = [
                'date' => $this->updated_at,
                'event' => 'Verified',
                'icon' => 'fa-check-circle',
                'color' => 'green'
            ];
        }

        // First Order
        $firstOrder = $this->sales()->orderBy('created_at')->first();
        if ($firstOrder) {
            $timeline[] = [
                'date' => $firstOrder->created_at,
                'event' => 'First Order',
                'icon' => 'fa-shopping-bag',
                'color' => 'blue'
            ];
        }

        // Membership Upgrade
        if ($this->membership_level != 'silver') {
            $timeline[] = [
                'date' => $this->updated_at,
                'event' => ucfirst($this->membership_level) . ' Member',
                'icon' => 'fa-crown',
                'color' => 'gold'
            ];
        }

        // Lucky Draw Winner
        $winner = LuckyDrawEntry::where('customer_id', $this->id)
            ->where('is_winner', true)
            ->first();
        if ($winner) {
            $timeline[] = [
                'date' => $winner->created_at,
                'event' => 'Lucky Draw Winner',
                'icon' => 'fa-trophy',
                'color' => 'yellow'
            ];
        }

        return $timeline;
    }
}
