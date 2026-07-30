<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SaleService
{
    /**
     * Apply stock and accounting for sale
     */
    public function applyStockAndAccounting(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            if (!in_array($sale->status, ['confirmed', 'paid'])) {
                return;
            }

            $this->updateStock($sale);
            $this->createStockMovements($sale);
            $this->postAccounting($sale);
        });
    }

    /**
     * Reverse stock and accounting for sale
     */
    public function reverseStockAndAccounting(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            $this->reverseStock($sale);
            $this->deleteStockMovements($sale);
            $this->deleteJournalEntries($sale);
        });
    }

    /**
     * Update product stock (decrease for sale)
     */
    private function updateStock(Sale $sale)
    {
        foreach ($sale->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                if ($product->current_stock < $item->quantity) {
                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->current_stock}, Required: {$item->quantity}");
                }
                $product->current_stock = floatval($product->current_stock) - floatval($item->quantity);
                $product->save();
            }
        }
    }

    /**
     * Reverse product stock (increase back)
     */
    private function reverseStock(Sale $sale)
    {
        foreach ($sale->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->current_stock = floatval($product->current_stock) + floatval($item->quantity);
                $product->save();
            }
        }
    }

    /**
     * Create stock movement records
     */
    private function createStockMovements(Sale $sale)
    {
        foreach ($sale->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $oldStock = floatval($product->current_stock) + floatval($item->quantity);
                
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'stock_before' => $oldStock,
                    'stock_after' => $product->current_stock,
                    'notes' => "Sale #{$sale->invoice_no} - Customer: {$sale->customer->name}"
                ]);
            }
        }
    }

    /**
     * Delete stock movements
     */
    private function deleteStockMovements(Sale $sale)
    {
        StockMovement::where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->delete();
    }

    /**
     * Post accounting entries
     */
    private function postAccounting(Sale $sale)
    {
        $inventoryAccount = Account::where('code', '1030')->first();
        $revenueAccount = Account::where('code', '4010')->first();
        $receivableAccount = Account::where('code', '1040')->first();
        $cashAccount = Account::where('code', '1010')->first();

        if (!$inventoryAccount || !$revenueAccount) {
            return;
        }

        $entries = [];

        // DEBIT: Cash OR Customer Receivable
        if ($sale->payment_term == 'cash') {
            if ($cashAccount) {
                $entries[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $sale->total_amount,
                    'description' => "Cash Sale #{$sale->invoice_no}"
                ];
            }
        } else {
            if ($receivableAccount) {
                $entries[] = [
                    'account_id' => $receivableAccount->id,
                    'type' => 'debit',
                    'amount' => $sale->total_amount,
                    'description' => "Credit Sale #{$sale->invoice_no} - Customer: {$sale->customer->name}"
                ];
            }
        }

        // CREDIT: Sales Revenue
        $entries[] = [
            'account_id' => $revenueAccount->id,
            'type' => 'credit',
            'amount' => $sale->total_amount,
            'description' => "Sale Revenue #{$sale->invoice_no}"
        ];

        // COGS
        $cogsAmount = $sale->items->sum(function($item) {
            $product = Product::find($item->product_id);
            return floatval($item->quantity) * floatval($product->purchase_price ?? 0);
        });

        if ($cogsAmount > 0) {
            $cogsAccount = Account::where('code', '5010')->first();
            if ($cogsAccount) {
                $entries[] = [
                    'account_id' => $cogsAccount->id,
                    'type' => 'debit',
                    'amount' => $cogsAmount,
                    'description' => "COGS for Sale #{$sale->invoice_no}"
                ];

                $entries[] = [
                    'account_id' => $inventoryAccount->id,
                    'type' => 'credit',
                    'amount' => $cogsAmount,
                    'description' => "Inventory reduction for Sale #{$sale->invoice_no}"
                ];
            }
        }

        foreach ($entries as $entry) {
            if ($entry['account_id']) {
                JournalEntry::create([
                    'account_id' => $entry['account_id'],
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'description' => $entry['description'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        }
    }

    /**
     * Delete journal entries
     */
    private function deleteJournalEntries(Sale $sale)
    {
        JournalEntry::where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->delete();
    }

    /**
     * Full update with items change
     */
    public function syncItemsAndUpdate(Sale $sale, array $newItemsData)
    {
        DB::transaction(function () use ($sale, $newItemsData) {
            // Reverse old stock and accounting
            $this->reverseStockAndAccounting($sale);

            // Delete old items
            $sale->items()->delete();

            // Create new items
            foreach ($newItemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            // Recalculate totals
            $sale->calculateTotals();
            $sale->save();

            // Apply new stock and accounting if status is active
            if (in_array($sale->status, ['confirmed', 'paid'])) {
                $this->applyStockAndAccounting($sale);
            }
        });
    }

    /**
     * Record payment for credit sale
     */
    public function recordPayment(Sale $sale, $amount)
    {
        DB::transaction(function () use ($sale, $amount) {
            $cashAccount = Account::where('code', '1010')->first();
            $receivableAccount = Account::where('code', '1040')->first();

            if ($cashAccount && $receivableAccount) {
                JournalEntry::create([
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Payment received for Sale #{$sale->invoice_no}",
                    'reference_type' => 'sale_payment',
                    'reference_id' => $sale->id,
                    'entry_date' => now()->toDateString(),
                ]);

                JournalEntry::create([
                    'account_id' => $receivableAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Payment received for Sale #{$sale->invoice_no}",
                    'reference_type' => 'sale_payment',
                    'reference_id' => $sale->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        });
    }
}