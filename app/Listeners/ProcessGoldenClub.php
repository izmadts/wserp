<?php

namespace App\Listeners;

use App\Events\SaleCreated;
use App\Models\GoldenClubSetting;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\Log;
use App\Models\CustomerReferral;
use App\Models\Customer;

class ProcessGoldenClub
{
    public function handle(SaleCreated $event)
    {
        $sale = $event->sale;
        $customer = $sale->customer;

        // Only process if customer is verified and sale is paid
        if (!$customer->is_verified || $sale->status != 'paid') {
            return;
        }

        // membership_level is a dynamic accessor computed live from
        // total_purchase, so this must be captured before total_purchase
        // is updated below - otherwise "before" already reads the new tier.
        $levelBeforeUpgrade = $customer->membership_level;

        // Update customer purchase totals
        $customer->total_purchase += $sale->total_amount;
        $customer->lifetime_purchase += $sale->total_amount;
        $customer->last_purchase = now();
        $customer->save();

        // Add loyalty points
        $points = $customer->calculatePoints($sale->total_amount);
        if ($points > 0) {
            $customer->addLoyaltyPoints($points, $sale->id, "Sale #{$sale->invoice_no}");
            NotificationHelper::send(
                $customer->id,
                'Points Earned!',
                "You earned " . number_format($points, 0) . " points from Sale #{$sale->invoice_no}.",
                'points'
            );
        }

        // Generate lucky draw entries
        $entries = $customer->generateLuckyDrawEntries($sale->total_amount, $sale->id);
        if ($entries > 0) {
            Log::info("Generated {$entries} lucky draw entries for customer {$customer->id}");
            NotificationHelper::send(
                $customer->id,
                'Lucky Draw Entries Earned!',
                "You earned {$entries} lucky draw " . ($entries == 1 ? 'entry' : 'entries') . " from Sale #{$sale->invoice_no}.",
                'lucky_draw'
            );
        }

        // Check membership upgrade
        $customer->checkMembershipUpgrade();
        if ($customer->membership_level !== $levelBeforeUpgrade) {
            NotificationHelper::send(
                $customer->id,
                'Membership Upgraded!',
                "Congratulations! You've been upgraded to " . ucfirst($customer->membership_level) . ' membership.',
                'membership'
            );
        }

        // Check referral rewards
        $this->processReferral($customer, $sale);
    }

    /**
     * Check and process referral rewards
     */
    private function processReferral($customer, $sale)
    {
        if (empty($customer->phone)) {
            return;
        }

        // Check if this customer was referred. The status filter must apply
        // to both phone-match branches - an ungrouped orWhere here let
        // already-converted/rewarded referrals match on the first branch
        // and get reprocessed on every subsequent sale.
        $referral = CustomerReferral::where('status', 'pending')
            ->where(function ($query) use ($customer) {
                $query->where('referred_customer', $customer->phone)
                    ->orWhere('referred_phone', $customer->phone);
            })
            ->first();

        if ($referral) {
            // Mark referral as converted
            $referral->status = 'converted';
            $referral->save();

            // Get referral bonus points
            $referralBonus = GoldenClubSetting::get('referral_bonus', ['points' => 50]);
            $bonusPoints = $referralBonus['points'] ?? 50;

            // Give bonus to referrer
            $referrer = Customer::find($referral->customer_id);
            if ($referrer) {
                $referrer->addLoyaltyPoints(
                    $bonusPoints,
                    $sale->id,
                    "Referral bonus for {$customer->name}"
                );

                NotificationHelper::send(
                    $referrer->id,
                    'Referral Bonus Earned!',
                    "You earned " . number_format($bonusPoints, 0) . " points for referring {$customer->name}, who just made their first purchase!",
                    'referral'
                );
            }
        }
    }
}
