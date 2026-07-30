<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for everything that happens to stock and
 * accounting as a result of a purchase: creating it, editing it,
 * deleting it, and paying it off.
 *
 * All "apply"/"reverse" methods are idempotent - they check whether the
 * relevant StockMovement rows already exist before acting, so it is
 * always safe to call them, even more than once for the same purchase.
 * Nothing else in the app (models, controllers) should create
 * StockMovement or JournalEntry rows for a purchase directly - always
 * go through this service.
 */
class PurchaseService
{
    /**
     * Apply stock + inventory/payable-or-cash accounting for a purchase.
     * IDEMPOTENT: no-op if stock movements already exist for this purchase.
     */
    public function applyStockAndAccounting(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            if (!in_array($purchase->status, ['received', 'paid', 'partial'])) {
                return;
            }

            $alreadyApplied = StockMovement::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->exists();

            if ($alreadyApplied) {
                Log::info('Stock/accounting already applied for purchase, skipping', [
                    'purchase_id' => $purchase->id,
                ]);
                return;
            }

            Log::info('Applying stock and accounting for purchase', [
                'purchase_id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no,
            ]);

            $purchase->load('items', 'supplier');

            $this->updateStock($purchase);
            $this->createStockMovements($purchase);
            $this->postAccounting($purchase);
        });
    }

    /**
     * Reverse stock + inventory/payable-or-cash accounting for a purchase.
     * IDEMPOTENT: no-op if no stock movements exist.
     * Does NOT touch payments - use reversePaymentsAndAccounting() for that.
     */
    public function reverseStockAndAccounting(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $hasMovements = StockMovement::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->exists();

            if (!$hasMovements) {
                Log::info('No stock movements to reverse for purchase, skipping', [
                    'purchase_id' => $purchase->id,
                ]);
                return;
            }

            Log::info('Reversing stock and accounting for purchase', [
                'purchase_id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no,
            ]);

            $purchase->load('items');

            $this->reverseStock($purchase);
            $this->deleteStockMovements($purchase);
            $this->deleteJournalEntries($purchase, 'purchase');
        });
    }

    /**
     * Delete all payments + payment journal entries for a purchase.
     * Only call this when the whole purchase record is being deleted -
     * NOT when just editing items (syncItemsAndUpdate leaves payments alone).
     */
    public function reversePaymentsAndAccounting(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->payments()->exists()) {
                $purchase->payments()->delete();
                Log::info('Payments deleted for purchase', ['purchase_id' => $purchase->id]);
            }

            $this->deleteJournalEntries($purchase, 'purchase_payment');
        });
    }

    /**
     * Record a payment against a purchase: creates the payment row, updates
     * paid_amount/due_amount/status, and (optionally) posts the
     * payable-to-cash journal entry.
     *
     * Set $postAccounting = false for purchases where cash was already
     * credited directly at creation time (pure cash purchases) so the cash
     * outflow isn't posted twice.
     */
    public function recordPayment(Purchase $purchase, $amount, $method = 'cash', $date = null, $referenceNo = null, $notes = null, $postAccounting = true)
    {
        DB::transaction(function () use ($purchase, $amount, $method, $date, $referenceNo, $notes, $postAccounting) {
            $purchase->payments()->create([
                'supplier_id' => $purchase->supplier_id,
                'payment_date' => $date ?? now(),
                'amount' => $amount,
                'payment_method' => $method,
                'reference_no' => $referenceNo,
                'notes' => $notes ?? "Payment for Purchase #{$purchase->invoice_no}",
            ]);

            $purchase->paid_amount = $purchase->paid_amount + $amount;
            $purchase->due_amount = $purchase->total_amount - $purchase->paid_amount;
            $purchase->status = $purchase->due_amount <= 0 ? 'paid' : 'partial';
            $purchase->save();

            if ($postAccounting) {
                $this->postPaymentAccounting($purchase, $amount);
            }

            Log::info('Payment recorded for purchase', [
                'purchase_id' => $purchase->id,
                'amount' => $amount,
                'paid_amount' => $purchase->paid_amount,
                'due_amount' => $purchase->due_amount,
                'status' => $purchase->status,
            ]);
        });
    }

    /**
     * Reverse items + re-apply stock/accounting when a purchase is edited.
     * Payments already made are left untouched. If the edit marks the
     * purchase "paid" and there's still a due amount, it is auto-settled.
     */
    public function syncItemsAndUpdate(Purchase $purchase, array $newItemsData)
    {
        DB::transaction(function () use ($purchase, $newItemsData) {
            Log::info('Syncing items and updating purchase', [
                'purchase_id' => $purchase->id,
                'invoice_no' => $purchase->invoice_no,
            ]);

            $this->reverseStockAndAccounting($purchase);

            $purchase->items()->delete();
            foreach ($newItemsData as $itemData) {
                $purchase->items()->create($itemData);
            }

            $purchase->refresh();
            $purchase->calculateTotals();
            $purchase->save();

            if (in_array($purchase->status, ['received', 'paid', 'partial'])) {
                $this->applyStockAndAccounting($purchase);
            }

            $purchase->refresh();
            if ($purchase->status == 'paid' && $purchase->due_amount > 0) {
                $this->recordPayment(
                    $purchase,
                    $purchase->due_amount,
                    $purchase->payment_term == 'cash' ? 'cash' : 'bank_transfer',
                    now(),
                    null,
                    'Full settlement on purchase update',
                    $purchase->payment_term != 'cash'
                );
            }
        });
    }

    // =============================================
    // INTERNAL HELPERS
    // =============================================

    private function updateStock(Purchase $purchase)
    {
        foreach ($purchase->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->current_stock += $item->quantity;
                $product->save();
                Log::info("Stock increased for product: {$product->name} (+{$item->quantity})");
            }
        }
    }

    private function reverseStock(Purchase $purchase)
    {
        foreach ($purchase->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->current_stock -= $item->quantity;
                $product->save();
                Log::info("Stock reversed for product: {$product->name} (-{$item->quantity})");
            }
        }
    }

    private function createStockMovements(Purchase $purchase)
    {
        foreach ($purchase->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $stockBefore = $product->current_stock - $item->quantity;

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->current_stock,
                    'notes' => "Purchase #{$purchase->invoice_no}" . ($purchase->supplier ? " - Supplier: {$purchase->supplier->name}" : ''),
                ]);
            }
        }
    }

    private function deleteStockMovements(Purchase $purchase)
    {
        StockMovement::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->delete();
    }

    private function postAccounting(Purchase $purchase)
    {
        $inventoryAccount = Account::where('code', '1030')->first();
        $payableAccount = Account::where('code', '2010')->first();
        $cashAccount = Account::where('code', '1010')->first();

        if (!$inventoryAccount || !$payableAccount || !$cashAccount) {
            Log::warning('Accounts not found for purchase accounting', ['purchase_id' => $purchase->id]);
            return;
        }

        $entries = [
            [
                'account_id' => $inventoryAccount->id,
                'type' => 'debit',
                'amount' => $purchase->total_amount,
                'description' => "Purchase #{$purchase->invoice_no} - Stock In",
            ],
        ];

        if ($purchase->payment_term == 'credit') {
            $entries[] = [
                'account_id' => $payableAccount->id,
                'type' => 'credit',
                'amount' => $purchase->total_amount,
                'description' => "Credit Purchase #{$purchase->invoice_no}" . ($purchase->supplier ? " - Supplier: {$purchase->supplier->name}" : ''),
            ];
        } else {
            $entries[] = [
                'account_id' => $cashAccount->id,
                'type' => 'credit',
                'amount' => $purchase->total_amount,
                'description' => "Cash Purchase #{$purchase->invoice_no}",
            ];
        }

        foreach ($entries as $entry) {
            JournalEntry::create([
                'account_id' => $entry['account_id'],
                'type' => $entry['type'],
                'amount' => $entry['amount'],
                'description' => $entry['description'],
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'entry_date' => now()->toDateString(),
            ]);
        }

        Log::info("Accounting entries posted for purchase: {$purchase->invoice_no}");
    }

    private function postPaymentAccounting(Purchase $purchase, $amount)
    {
        $payableAccount = Account::where('code', '2010')->first();
        $cashAccount = Account::where('code', '1010')->first();

        if (!$payableAccount || !$cashAccount) {
            Log::warning('Accounts not found for payment accounting', ['purchase_id' => $purchase->id]);
            return;
        }

        $entries = [
            [
                'account_id' => $payableAccount->id,
                'type' => 'debit',
                'amount' => $amount,
                'description' => "Payment for Purchase #{$purchase->invoice_no}",
            ],
            [
                'account_id' => $cashAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => "Cash Payment for Purchase #{$purchase->invoice_no}",
            ],
        ];

        foreach ($entries as $entry) {
            JournalEntry::create([
                'account_id' => $entry['account_id'],
                'type' => $entry['type'],
                'amount' => $entry['amount'],
                'description' => $entry['description'],
                'reference_type' => 'purchase_payment',
                'reference_id' => $purchase->id,
                'entry_date' => now()->toDateString(),
            ]);
        }
    }

    private function deleteJournalEntries(Purchase $purchase, $referenceType = 'purchase')
    {
        JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $purchase->id)
            ->delete();
    }
}
