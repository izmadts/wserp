<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction for a purchase whose payment_term was edited to the
 * wrong value after it already had real payments posted against it under
 * the correct one - confirmed live on Purchase #2 (PO-260815-00002): it
 * was genuinely paid off over 5 installments (classic credit behavior),
 * but got switched from 'credit' to 'cash' partway through. That edit
 * made postAccounting() revert its base ledger entry to credit Cash in
 * full, orphaning the 5 already-posted payment entries against a Payable
 * leg that no longer existed - and, separately, made it vanish entirely
 * from the Supplier Ledger report, which filters strictly on
 * `payment_term = 'credit'` rather than reading the ledger directly
 * (ReportController::supplierLedgerData()).
 *
 * This corrects the field back to the given term, then reconstructs the
 * purchase's 'purchase' + 'purchase_payment' journal entries from its real
 * payment history (same approach verified for ledger:fix-cash-purchase-gaps),
 * leaving 'purchase_return' entries untouched. Defaults to a dry run.
 */
class CorrectPurchasePaymentTerm extends Command
{
    protected $signature = 'ledger:correct-purchase-term {purchase_id} {--to=credit : The correct payment_term (cash or credit)} {--apply : Actually write the correction (default is a dry run)}';

    protected $description = 'Fix a purchase whose payment_term was wrongly edited after real payments were posted against it';

    public function handle(PurchaseService $purchaseService)
    {
        $purchase = Purchase::find((int) $this->argument('purchase_id'));
        if (!$purchase) {
            $this->error('Purchase not found.');
            return self::FAILURE;
        }

        $to = $this->option('to');
        if (!in_array($to, ['cash', 'credit'])) {
            $this->error("--to must be 'cash' or 'credit'.");
            return self::FAILURE;
        }

        $apply = $this->option('apply');

        $this->info("Purchase #{$purchase->id} ({$purchase->invoice_no}): payment_term is currently '{$purchase->payment_term}', paid_amount Rs. " . number_format($purchase->paid_amount, 2) . ", from " . $purchase->payments()->count() . ' payment(s).');

        if ($purchase->payment_term === $to) {
            $this->warn("Already '{$to}' - nothing to do.");
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->warn("DRY RUN - would set payment_term to '{$to}' and reconstruct its ledger entries from real payment history. Re-run with --apply to commit.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($purchase, $to, $purchaseService) {
            $purchase->payment_term = $to;
            $purchase->save();

            JournalEntry::where('reference_type', 'purchase')->where('reference_id', $purchase->id)->delete();
            JournalEntry::where('reference_type', 'purchase_payment')->where('reference_id', $purchase->id)->delete();

            $realPaid = $purchase->paid_amount;
            $purchase->paid_amount = 0;
            $purchaseService->repostAccountingOnly($purchase);
            $purchase->paid_amount = $realPaid;

            $purchaseService->repostAllPaymentAccounting($purchase);
        });

        $purchase->refresh();
        $this->info("Done. payment_term is now '{$purchase->payment_term}', status '{$purchase->status}', paid Rs. " . number_format($purchase->paid_amount, 2) . ', due Rs. ' . number_format($purchase->due_amount, 2) . '.');

        return self::SUCCESS;
    }
}
