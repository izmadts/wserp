<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time correction for a bug in SaleService::postAccounting(): a
 * 'cash' payment_term sale used to always debit Cash for the full
 * total_amount the moment it was posted, even when the actual amount
 * collected at that point (paid_amount) was less - and any further
 * "Add Payment" against it never posted to the ledger at all, because
 * postPaymentAccounting() unconditionally skipped non-credit sales.
 *
 * This finds every 'cash' sale that shows the bug's signature (more
 * than one payment ever recorded against it, or still sitting
 * 'partial' today) and reconstructs its 'sale' + 'sale_payment' journal
 * entries from scratch: Receivable debited for the full total at
 * posting time, then each real approved SalePayment replayed in date
 * order as its own Dr Cash/Cr Receivable entry - exactly what the
 * fixed postAccounting()/postPaymentAccounting() now do for new sales.
 * COGS/inventory legs are left untouched.
 *
 * Defaults to a dry run; pass --apply to actually commit.
 */
class FixCashSaleLedgerGaps extends Command
{
    protected $signature = 'ledger:fix-cash-sale-gaps {--apply : Actually write the correction (default is a dry run)} {--ids= : Comma-separated sale IDs to restrict to, instead of auto-detecting}';

    protected $description = 'Correct cash-term sales whose ledger never reflected payments collected after the sale was posted';

    public function handle(SaleService $saleService)
    {
        if ($idsOpt = $this->option('ids')) {
            $ids = array_map('intval', explode(',', $idsOpt));
            $sales = Sale::whereIn('id', $ids)->get();
        } else {
            $multiPayment = Sale::where('payment_term', 'cash')
                ->withCount('payments')
                ->having('payments_count', '>', 1)
                ->get();

            $stillPartial = Sale::where('payment_term', 'cash')
                ->where('status', 'partial')
                ->get();

            $sales = $multiPayment->merge($stillPartial)->unique('id')->sortBy('id')->values();
        }

        if ($sales->isEmpty()) {
            $this->info('No affected sales found.');
            return self::SUCCESS;
        }

        $apply = $this->option('apply');
        $this->info($apply ? 'APPLYING correction to ' . $sales->count() . ' sale(s)...' : 'DRY RUN - ' . $sales->count() . ' sale(s) would be corrected (pass --apply to commit):');

        $rows = [];

        foreach ($sales as $sale) {
            $beforeCash = JournalEntry::whereIn('reference_type', ['sale', 'sale_payment'])
                ->where('reference_id', $sale->id)
                ->where('account_id', 1)
                ->where('type', 'debit')
                ->sum('amount');

            if ($apply) {
                DB::transaction(function () use ($sale, $saleService) {
                    JournalEntry::where('reference_type', 'sale')->where('reference_id', $sale->id)->delete();
                    JournalEntry::where('reference_type', 'sale_payment')->where('reference_id', $sale->id)->delete();

                    $realPaid = $sale->paid_amount;
                    $sale->paid_amount = 0;
                    $saleService->repostAccountingOnly($sale);
                    $sale->paid_amount = $realPaid;

                    $saleService->repostAllPaymentAccounting($sale);
                });
            }

            $afterCash = $apply
                ? JournalEntry::whereIn('reference_type', ['sale', 'sale_payment'])->where('reference_id', $sale->id)->where('account_id', 1)->where('type', 'debit')->sum('amount')
                : null;

            $rows[] = [
                $sale->id,
                $sale->invoice_no,
                $sale->status,
                number_format($sale->paid_amount, 2),
                number_format($beforeCash, 2),
                $apply ? number_format($afterCash, 2) : '(dry run)',
            ];
        }

        $this->table(['Sale ID', 'Invoice', 'Status', 'paid_amount (source of truth)', 'Cash posted BEFORE', 'Cash posted AFTER'], $rows);

        if (!$apply) {
            $this->warn('Nothing was written. Re-run with --apply to commit.');
        } else {
            $this->info('Done. "Cash posted AFTER" should now equal paid_amount for every row above.');
        }

        return self::SUCCESS;
    }
}
