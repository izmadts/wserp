<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_no',
        'title',
        'description',
        'category_id',
        'amount',
        'expense_date',
        'payment_method',
        'reference_no',
        'status',
        'receipt',
        'created_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->expense_no)) {
                $expense->expense_no = 'EXP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });

        // After expense is created, post accounting entry
        static::created(function ($expense) {
            if ($expense->status == 'approved' || $expense->status == 'paid') {
                $expense->postAccounting();
            }
        });

        static::updated(function ($expense) {
            // If status changed to approved/paid, post accounting
            if ($expense->isDirty('status') && in_array($expense->status, ['approved', 'paid'])) {
                $expense->postAccounting();
            }

            // If status changed to cancelled, reverse accounting
            if ($expense->isDirty('status') && $expense->status == 'cancelled') {
                $expense->reverseAccounting();
            }
        });

        // Before deleting, reverse accounting
        static::deleting(function ($expense) {
            $expense->reverseAccounting();
        });
    }

    /**
     * Post accounting entry for expense
     */
    public function postAccounting()
    {
        $expenseAccount = Account::where('code', '5030')->first(); // General Expenses
        $cashAccount = Account::where('code', '1010')->first(); // Cash Account
        $bankAccount = Account::where('code', '1020')->first(); // Bank Account

        if (!$expenseAccount || !$cashAccount) {
            return;
        }

        $entries = [];

        // Debit: Expense Account
        $entries[] = [
            'account_id' => $expenseAccount->id,
            'type' => 'debit',
            'amount' => $this->amount,
            'description' => "Expense #{$this->expense_no} - {$this->title}"
        ];

        // Credit: Cash or Bank
        if ($this->payment_method == 'cash') {
            if ($cashAccount) {
                $entries[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'credit',
                    'amount' => $this->amount,
                    'description' => "Expense Payment #{$this->expense_no}"
                ];
            }
        } else {
            if ($bankAccount) {
                $entries[] = [
                    'account_id' => $bankAccount->id,
                    'type' => 'credit',
                    'amount' => $this->amount,
                    'description' => "Expense Payment #{$this->expense_no}"
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
                    'reference_type' => 'expense',
                    'reference_id' => $this->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        }
    }

    /**
     * Reverse accounting entry
     */
    public function reverseAccounting()
    {
        JournalEntry::where('reference_type', 'expense')
            ->where('reference_id', $this->id)
            ->delete();
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('expense_date', [$from, $to]);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-blue-100 text-blue-800',
            'paid' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getFormattedAmountAttribute()
    {
        return 'Rs. ' . number_format($this->amount, 2);
    }

    public function getPaymentMethodLabelAttribute()
    {
        $labels = [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit_card' => 'Credit Card'
        ];
        return $labels[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
