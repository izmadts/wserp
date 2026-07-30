<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AccountingTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseReturn extends Model
{
    use SoftDeletes, AccountingTrait;

    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'return_date',
        'invoice_no',
        'reason',
        'sub_total',
        'discount',
        'tax',
        'total_amount',
        // NOTE: was missing before, so the controller's validated
        // 'refund_method' was being silently dropped on every insert.
        // Make sure your purchase_returns migration has this column.
        'refund_method',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'return_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($return) {
            if (empty($return->invoice_no)) {
                $return->invoice_no = 'PR-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });

        // NOTE: intentionally NOT calling updateStockAndAccounts() from a
        // `created` event here. That event fires immediately after the
        // PurchaseReturn row itself is inserted - BEFORE its items are
        // created by the controller - so $this->items would be empty and
        // stock movements would silently never get created (the same
        // ordering trap the original Purchase model had). Instead,
        // PurchaseReturnController calls updateStockAndAccounts() itself,
        // once items are guaranteed to exist.

        // Deleting is safe as a model event since items already exist
        // by the time a return is deleted.
        // NOTE: this event did not exist before - deleting a purchase
        // return silently left stock and journal entries wrong forever.
        static::deleting(function ($return) {
            $return->reverseStockAndAccounts();
        });
    }

    /**
     * Decrease stock for returned items and post the offsetting accounting
     * entry. IDEMPOTENT: no-op if stock movements already exist for this
     * return, so it's safe even if called more than once.
     */
    public function updateStockAndAccounts()
    {
        $alreadyApplied = StockMovement::where('reference_type', 'purchase_return')
            ->where('reference_id', $this->id)
            ->exists();

        if ($alreadyApplied) {
            Log::info('Stock/accounting already applied for purchase return, skipping', [
                'return_id' => $this->id,
            ]);
            return;
        }

        DB::transaction(function () {
            foreach ($this->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $stockBefore = $product->current_stock;
                $product->current_stock -= $item->quantity;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'reference_type' => 'purchase_return',
                    'reference_id' => $this->id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->current_stock,
                    'notes' => "Purchase Return #{$this->invoice_no}"
                ]);
            }

            $this->postReturnAccounting();
        });
    }

    /**
     * Restore stock and remove the journal entries created for this return.
     * IDEMPOTENT: no-op if no stock movements exist.
     */
    public function reverseStockAndAccounts()
    {
        $hasMovements = StockMovement::where('reference_type', 'purchase_return')
            ->where('reference_id', $this->id)
            ->exists();

        if (!$hasMovements) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items as $item) {
                $product = $item->product;
                if ($product) {
                    $product->current_stock += $item->quantity;
                    $product->save();
                }
            }

            StockMovement::where('reference_type', 'purchase_return')
                ->where('reference_id', $this->id)
                ->delete();

            JournalEntry::where('reference_type', 'purchase_return')
                ->where('reference_id', $this->id)
                ->delete();
        });
    }

    /**
     * Debit the offset account (Payable if the refund reduces what we owe
     * the supplier, Cash if the supplier refunded us directly) and credit
     * Inventory. Both directions use the same debit/credit pattern since a
     * Payable decrease and a Cash increase are both debits in our books.
     */
    public function postReturnAccounting()
    {
        $inventoryAccount = Account::where('code', '1030')->first();
        $payableAccount = Account::where('code', '2010')->first();
        $cashAccount = Account::where('code', '1010')->first();

        $useCash = in_array($this->refund_method, ['cash', 'bank_transfer', 'cheque']);
        $offsetAccount = $useCash ? $cashAccount : $payableAccount;

        // NOTE: previously there was no null-check here at all - a missing
        // account code would throw a fatal error calling ->id on null.
        if (!$inventoryAccount || !$offsetAccount) {
            Log::warning('Accounts not found for purchase return accounting', [
                'return_id' => $this->id,
                'refund_method' => $this->refund_method,
            ]);
            return;
        }

        $entries = [
            [
                'account_id' => $offsetAccount->id,
                'type' => 'debit',
                'amount' => $this->total_amount
            ],
            [
                'account_id' => $inventoryAccount->id,
                'type' => 'credit',
                'amount' => $this->total_amount
            ]
        ];

        $this->postDoubleEntry(
            "Purchase Return #{$this->invoice_no}",
            $entries,
            'purchase_return',
            $this->id
        );
    }

    // Relationships
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
