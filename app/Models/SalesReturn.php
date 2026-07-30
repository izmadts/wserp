<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\AgentCommissionLog;
use App\Models\User;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SalesReturnItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SalesReturn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'return_no',
        'sale_id',
        'customer_id',
        'return_date',
        'reason',
        'sub_total',
        'discount',
        'tax',
        'total_amount',
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
            if (empty($return->return_no)) {
                $return->return_no = 'SR-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });

        // After return is created, reverse stock and accounting
        static::created(function ($return) {
            $return->reverseStockAndAccounting();
        });

        // Before deleting, restore stock
        static::deleting(function ($return) {
            $return->restoreStockAndAccounting();
        });
    }

    /**
     * Reverse stock and accounting for sales return
     */
    public function reverseStockAndAccounting()
    {
        DB::transaction(function () {
            // 1. Increase product stock (returned items)
            $this->updateProductStock();

            // 2. Create stock movement
            $this->createStockMovements();

            // 3. Reverse accounting entries
            $this->reverseAccounting();

            // 4. Reverse agent commission
            $this->reverseCommission();

            // 5. Update customer balance
            $this->updateCustomerBalance();
        });
    }

    /**
     * Restore stock when return is deleted
     */
    public function restoreStockAndAccounting()
    {
        DB::transaction(function () {
            // 1. Decrease product stock (reverse the return)
            foreach ($this->items as $item) {
                $product = $item->product;
                $product->current_stock -= $item->quantity;
                $product->save();
            }

            // 2. Delete stock movements
            StockMovement::where('reference_type', 'sales_return')
                ->where('reference_id', $this->id)
                ->delete();

            // 3. Delete journal entries
            JournalEntry::where('reference_type', 'sales_return')
                ->where('reference_id', $this->id)
                ->delete();
        });
    }

    /**
     * Update product stock (increase for return)
     */
    private function updateProductStock()
    {
        foreach ($this->items as $item) {
            $product = $item->product;
            $product->current_stock += $item->quantity;
            $product->save();
        }
    }

    /**
     * Create stock movement records
     */
    private function createStockMovements()
    {
        foreach ($this->items as $item) {
            $product = $item->product;
            $oldStock = $product->current_stock - $item->quantity;

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'in',
                'reference_type' => 'sales_return',
                'reference_id' => $this->id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'stock_before' => $oldStock,
                'stock_after' => $product->current_stock,
                'notes' => "Sales Return #{$this->return_no} - Customer: {$this->customer->name}"
            ]);
        }
    }

    /**
     * Reverse accounting entries
     */
    private function reverseAccounting()
    {
        $inventoryAccount = Account::where('code', '1030')->first();
        $revenueAccount = Account::where('code', '4010')->first();
        $receivableAccount = Account::where('code', '1040')->first();
        $cashAccount = Account::where('code', '1010')->first();

        if (!$inventoryAccount || !$revenueAccount) {
            return;
        }

        $entries = [];

        // Reverse Sale: Credit Cash/Receivable, Debit Revenue
        if ($this->sale->payment_term == 'cash') {
            if ($cashAccount) {
                $entries[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'credit',
                    'amount' => $this->total_amount,
                    'description' => "Sales Return #{$this->return_no} - Cash Refund"
                ];
            }
        } else {
            if ($receivableAccount) {
                $entries[] = [
                    'account_id' => $receivableAccount->id,
                    'type' => 'credit',
                    'amount' => $this->total_amount,
                    'description' => "Sales Return #{$this->return_no} - Customer Credit"
                ];
            }
        }

        // Debit: Sales Revenue (reverse the revenue)
        $entries[] = [
            'account_id' => $revenueAccount->id,
            'type' => 'debit',
            'amount' => $this->total_amount,
            'description' => "Sales Return #{$this->return_no} - Revenue Reversal"
        ];

        // Reverse COGS: Credit COGS, Debit Inventory
        $cogsAmount = $this->items->sum(function ($item) {
            $product = $item->product;
            return $item->quantity * ($product->purchase_price ?? 0);
        });

        if ($cogsAmount > 0) {
            $cogsAccount = Account::where('code', '5010')->first();
            if ($cogsAccount) {
                // Credit: COGS (reverse the expense)
                $entries[] = [
                    'account_id' => $cogsAccount->id,
                    'type' => 'credit',
                    'amount' => $cogsAmount,
                    'description' => "Sales Return #{$this->return_no} - COGS Reversal"
                ];

                // Debit: Inventory (stock came back)
                $entries[] = [
                    'account_id' => $inventoryAccount->id,
                    'type' => 'debit',
                    'amount' => $cogsAmount,
                    'description' => "Sales Return #{$this->return_no} - Stock Restored"
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
                    'reference_type' => 'sales_return',
                    'reference_id' => $this->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        }
    }

    /**
     * Reverse agent commission
     */
    private function reverseCommission()
    {
        if (!$this->sale->agent_id) {
            return;
        }

        $agent = User::find($this->sale->agent_id);
        if (!$agent) {
            return;
        }

        // Calculate commission on returned amount
        $commissionToReverse = 0;
        if ($agent->commission_type == 'percentage') {
            $commissionToReverse = ($this->total_amount * $agent->commission_rate / 100);
        } else {
            $commissionToReverse = $agent->commission_rate;
        }

        if ($commissionToReverse > 0) {
            // Create negative commission log
            AgentCommissionLog::create([
                'agent_id' => $agent->id,
                'sale_id' => $this->sale_id,
                'reference_type' => 'sales_return',
                'reference_id' => $this->id,
                'amount' => -$commissionToReverse,
                'commission_rate' => $agent->commission_rate,
                'commission_type' => $agent->commission_type,
                'description' => "Sales Return #{$this->return_no} - Commission Reversal",
                'is_paid' => false,
            ]);

            // Update sale commission amount
            $this->sale->commission_amount -= $commissionToReverse;
            $this->sale->save();
        }
    }

    /**
     * Update customer balance
     */
    private function updateCustomerBalance()
    {
        $customer = $this->customer;
        // Balance will be calculated via accessor
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
