<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of ledger:fix-cash-sale-gaps for the purchase side: a 'cash'
 * payment_term purchase used to always credit Cash for the full
 * total_amount the moment it was posted, even when the actual amount
 * paid at that point (paid_amount) was less - and any further payment
 * against it never posted to the ledger, because
 * postPaymentAccounting()/repostAllPaymentAccounting() unconditionally
 * skipped non-credit purchases.
 *
 * This finds every 'cash' purchase showing the bug's signature (more
 * than one payment ever recorded against it, or still 'partial' today)
 * and reconstructs its 'purchase' + 'purchase_payment' journal entries
 * from scratch: Payable credited for the full total at posting time,
 * then each real payment replayed in date order as its own Dr
 * Payable/Cr Cash entry - exactly what the fixed
 * postAccounting()/postPaymentAccounting() now do for new purchases.
 * 'purchase_return' entries are never touched - they already correctly
 * debit Payable, which this reconstruction makes a real balance to
 * draw down from again.
 *
 * Defaults to a dry run; pass --apply to actually commit.
 */
class FixCashPurchaseLedgerGaps extends Command
{
    protected $signature = 'ledger:fix-cash-purchase-gaps {--apply : Actually write the correction (default is a dry run)} {--ids= : Comma-separated purchase IDs to restrict to, instead of auto-detecting}';

    protected $description = 'Correct cash-term purchases whose ledger never reflected payments made after the purchase was posted';

    public function handle(PurchaseService $purchaseService)
    {
        if ($idsOpt = $this->option('ids')) {
            $ids = array_map('intval', explode(',', $idsOpt));
            $purchases = Purchase::whereIn('id', $ids)->get();
        } else {
            $multiPayment = Purchase::where('payment_term', 'cash')
                ->withCount('payments')
                ->having('payments_count', '>', 1)
                ->get();

            $stillPartial = Purchase::where('payment_term', 'cash')
                ->where('status', 'partial')
                ->get();

            $purchases = $multiPayment->merge($stillPartial)->unique('id')->sortBy('id')->values();
        }

        if ($purchases->isEmpty()) {
            $this->info('No affected purchases found.');
            return self::SUCCESS;
        }

        $apply = $this->option('apply');
        $this->info($apply ? 'APPLYING correction to ' . $purchases->count() . ' purchase(s)...' : 'DRY RUN - ' . $purchases->count() . ' purchase(s) would be corrected (pass --apply to commit):');

        $rows = [];

        foreach ($purchases as $purchase) {
            $beforeCash = JournalEntry::whereIn('reference_type', ['purchase', 'purchase_payment'])
                ->where('reference_id', $purchase->id)
                ->where('account_id', 1)
                ->where('type', 'credit')
                ->sum('amount');

            if ($apply) {
                DB::transaction(function () use ($purchase, $purchaseService) {
                    JournalEntry::where('reference_type', 'purchase')->where('reference_id', $purchase->id)->delete();
                    JournalEntry::where('reference_type', 'purchase_payment')->where('reference_id', $purchase->id)->delete();

                    $realPaid = $purchase->paid_amount;
                    $purchase->paid_amount = 0;
                    $purchaseService->repostAccountingOnly($purchase);
                    $purchase->paid_amount = $realPaid;

                    $purchaseService->repostAllPaymentAccounting($purchase);
                });
            }

            $afterCash = $apply
                ? JournalEntry::whereIn('reference_type', ['purchase', 'purchase_payment'])->where('reference_id', $purchase->id)->where('account_id', 1)->where('type', 'credit')->sum('amount')
                : null;

            $rows[] = [
                $purchase->id,
                $purchase->invoice_no,
                $purchase->status,
                number_format($purchase->paid_amount, 2),
                number_format($beforeCash, 2),
                $apply ? number_format($afterCash, 2) : '(dry run)',
            ];
        }

        $this->table(['Purchase ID', 'Invoice', 'Status', 'paid_amount (source of truth)', 'Cash posted BEFORE', 'Cash posted AFTER'], $rows);

        if (!$apply) {
            $this->warn('Nothing was written. Re-run with --apply to commit.');
        } else {
            $this->info('Done. "Cash posted AFTER" should now equal paid_amount for every row above.');
        }

        return self::SUCCESS;
    }
}
