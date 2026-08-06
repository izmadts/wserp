<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\GoldenClubSetting;
use App\Models\LoyaltyTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'golden-club:expire-points';

    protected $description = 'Expire loyalty point batches past their per-earn expiry date (FIFO), and warn customers whose points are expiring soon';

    public function handle()
    {
        $settings = GoldenClubSetting::get('points_expiry', ['enabled' => false]);

        if (empty($settings['enabled'])) {
            $this->info('Points expiry is disabled in Settings > Golden Club - nothing to do.');
            return self::SUCCESS;
        }

        $warningDays = (int) ($settings['warning_days'] ?? 30);

        $expiredRows = $this->expireDueBatches();
        $warnedRows = $this->warnUpcomingExpiries($warningDays);

        $this->info('Expired points for ' . count($expiredRows) . ' customer(s).');
        if (!empty($expiredRows)) {
            $this->table(['Customer ID', 'Name', 'Points Expired'], $expiredRows);
        }

        $this->info('Sent expiry warnings to ' . count($warnedRows) . ' customer(s).');
        if (!empty($warnedRows)) {
            $this->table(['Customer ID', 'Name', 'Points At Risk', 'Earliest Expiry'], $warnedRows);
        }

        return self::SUCCESS;
    }

    private function expireDueBatches(): array
    {
        $customerIds = LoyaltyTransaction::where('type', 'earn')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->distinct()
            ->pluck('customer_id');

        $rows = [];

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                continue;
            }

            $dueBatches = array_filter(
                $this->replayFifoBatches($customerId),
                fn ($batch) => $batch['expires_at'] && $batch['expires_at']->lte(now()) && $batch['remaining'] > 0.01
            );

            if (empty($dueBatches)) {
                continue;
            }

            $totalExpired = 0.0;

            DB::transaction(function () use ($dueBatches, $customer, &$totalExpired) {
                foreach ($dueBatches as $batch) {
                    LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'points' => -$batch['remaining'],
                        'type' => 'expire',
                        'reference_type' => 'loyalty_transaction',
                        'reference_id' => $batch['id'],
                        'remarks' => 'Points expired (earned ' . $batch['earned_at']->format('M j, Y') . ')',
                    ]);

                    $totalExpired += $batch['remaining'];
                }

                $customer->loyalty_points = max(0, $customer->loyalty_points - $totalExpired);
                $customer->save();
            });

            NotificationHelper::send(
                $customer->id,
                'Points Expired',
                number_format($totalExpired, 0) . ' points expired from your Golden Club balance.',
                'points_expired'
            );

            $rows[] = [$customer->id, $customer->name, number_format($totalExpired, 0)];
        }

        return $rows;
    }

    private function warnUpcomingExpiries(int $warningDays): array
    {
        $windowEnd = now()->addDays($warningDays);

        $customerIds = LoyaltyTransaction::where('type', 'earn')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), $windowEnd])
            ->distinct()
            ->pluck('customer_id');

        $rows = [];

        foreach ($customerIds as $customerId) {
            // At most one warning per customer per week, however many
            // batches of theirs are expiring soon - a customer with several
            // sales a month would otherwise get a separate notification for
            // every single one.
            $warnedRecently = CustomerNotification::where('customer_id', $customerId)
                ->where('type', 'points_expiring_soon')
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if ($warnedRecently) {
                continue;
            }

            $soonBatches = array_filter(
                $this->replayFifoBatches($customerId),
                fn ($batch) => $batch['expires_at'] && $batch['expires_at']->gt(now()) && $batch['expires_at']->lte($windowEnd) && $batch['remaining'] > 0.01
            );

            if (empty($soonBatches)) {
                continue;
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                continue;
            }

            $totalSoon = array_sum(array_column($soonBatches, 'remaining'));
            $earliestExpiry = min(array_map(fn ($b) => $b['expires_at'], $soonBatches));

            NotificationHelper::send(
                $customer->id,
                'Points Expiring Soon',
                number_format($totalSoon, 0) . ' of your points expire by ' . $earliestExpiry->format('M j, Y') . ' - redeem them before then!',
                'points_expiring_soon'
            );

            $rows[] = [$customer->id, $customer->name, number_format($totalSoon, 0), $earliestExpiry->format('Y-m-d')];
        }

        return $rows;
    }

    /**
     * Replays a customer's full loyalty ledger in chronological order to
     * work out how much of each earn batch is still unconsumed - a
     * redemption (or a prior expiry run) always draws down the OLDEST
     * still-open batch first, the same order the customer actually
     * experiences spending points in. Deliberately recomputed fresh on
     * every run rather than maintaining a running "remaining" counter on
     * each row, so this bolts on without touching the already-tested
     * Customer::redeemPoints() hot path at all.
     *
     * Plain arrays throughout (not a Collection) - mutating a Collection's
     * items via foreach-by-reference is not reliably safe in PHP, and
     * getting this arithmetic wrong would corrupt real point balances.
     *
     * @return array<int, array{id:int, remaining:float, expires_at:?\Carbon\Carbon, earned_at:\Carbon\Carbon}>
     */
    private function replayFifoBatches(int $customerId): array
    {
        $transactions = LoyaltyTransaction::where('customer_id', $customerId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'points', 'type', 'expires_at', 'created_at']);

        $batches = [];

        foreach ($transactions as $txn) {
            $points = (float) $txn->points;

            if ($points > 0) {
                $batches[] = [
                    'id' => $txn->id,
                    'remaining' => $points,
                    'expires_at' => $txn->type === 'earn' ? $txn->expires_at : null,
                    'earned_at' => $txn->created_at,
                ];
                continue;
            }

            $toConsume = abs($points);
            foreach ($batches as $i => $batch) {
                if ($toConsume <= 0.01) {
                    break;
                }
                if ($batch['remaining'] <= 0.01) {
                    continue;
                }
                $draw = min($batch['remaining'], $toConsume);
                $batches[$i]['remaining'] -= $draw;
                $toConsume -= $draw;
            }
        }

        return $batches;
    }
}
