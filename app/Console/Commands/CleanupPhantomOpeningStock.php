<?php

namespace App\Console\Commands;

use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for a batch of products whose 'opening' stock_movements
 * row was inserted (apparently via a manual script, not
 * Product::postOpeningStock()) AFTER real purchase/sale movements already
 * existed for that product - with stock_before wrongly left at 0, as if it
 * were that product's very first-ever movement. Confirmed live on 8
 * products (IDs 6, 9, 10-15): the row added no real information - summing
 * every OTHER movement for the product already equals its current
 * current_stock exactly, so the 'opening' row was a pure duplicate/no-op
 * that only exists to confuse the chronological history (making it look
 * like stock reset to zero partway through).
 *
 * Only ever deletes an 'opening' row when, after removing it, the sum of
 * that product's remaining movements still equals current_stock exactly -
 * so this can never silently change what the product's stock history adds
 * up to. If a product's 'opening' row is NOT redundant this way (removing
 * it would break the reconciliation), it's left alone and reported as
 * "skipped" for manual review instead.
 *
 * Defaults to a dry run; pass --apply to actually delete.
 */
class CleanupPhantomOpeningStock extends Command
{
    protected $signature = 'inventory:cleanup-phantom-opening {--apply : Actually delete the confirmed-redundant rows (default is a dry run)}';

    protected $description = 'Remove out-of-order opening stock_movements rows that are confirmed redundant (product reconciles without them)';

    public function handle()
    {
        $apply = $this->option('apply');

        $candidates = StockMovement::where('reference_type', 'opening')
            ->get()
            ->groupBy('product_id');

        $rows = [];
        $toDelete = [];

        foreach ($candidates as $productId => $openingMovements) {
            // A product can only legitimately have one real opening entry;
            // treat every 'opening' row for this product as a candidate.
            foreach ($openingMovements as $opening) {
                $product = $opening->product;
                if (!$product) {
                    continue;
                }

                $sumWithoutThisOpening = (float) StockMovement::where('product_id', $productId)
                    ->where('id', '!=', $opening->id)
                    ->get()
                    ->sum(fn ($m) => $m->type === 'in' ? (float) $m->quantity : -(float) $m->quantity);

                $matches = abs($sumWithoutThisOpening - (float) $product->current_stock) < 0.01;

                $rows[] = [
                    $opening->id,
                    $productId,
                    $product->name,
                    $opening->created_at,
                    number_format((float) $product->current_stock, 2),
                    $matches ? 'REDUNDANT - safe to delete' : 'NOT redundant - left alone',
                ];

                if ($matches) {
                    $toDelete[] = $opening->id;
                }
            }
        }

        if (empty($rows)) {
            $this->info('No opening stock movements found at all.');
            return self::SUCCESS;
        }

        $this->table(['Movement ID', 'Product ID', 'Product', 'Opening dated', 'current_stock', 'Verdict'], $rows);

        if (empty($toDelete)) {
            $this->info('Nothing confirmed-redundant to delete.');
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->warn(count($toDelete) . ' row(s) would be deleted. Re-run with --apply to commit.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($toDelete) {
            StockMovement::whereIn('id', $toDelete)->delete();
        });

        $this->info('Deleted ' . count($toDelete) . ' confirmed-redundant opening row(s). current_stock was not touched for any product.');

        return self::SUCCESS;
    }
}
