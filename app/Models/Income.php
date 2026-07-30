<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Income extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'income_no',
        'title',
        'description',
        'category_id',
        'amount',
        'income_date',
        'payment_method',
        'reference_no',
        'source',
        'receipt',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'income_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($income) {
            if (empty($income->income_no)) {
                $income->income_no = 'INC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });

        static::created(function ($income) {
            $income->postAccounting();
        });

        static::deleting(function ($income) {
            $income->reverseAccounting();
        });
    }

    /**
     * Post accounting entry for income
     */
    public function postAccounting()
    {
        $incomeAccount = Account::where('code', '4010')->first(); // Sales Revenue / Income
        $cashAccount = Account::where('code', '1010')->first(); // Cash Account
        $bankAccount = Account::where('code', '1020')->first(); // Bank Account

        if (!$incomeAccount || !$cashAccount) {
            return;
        }

        $entries = [];

        // Debit: Cash or Bank
        if ($this->payment_method == 'cash') {
            if ($cashAccount) {
                $entries[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $this->amount,
                    'description' => "Income #{$this->income_no} - {$this->title}"
                ];
            }
        } else {
            if ($bankAccount) {
                $entries[] = [
                    'account_id' => $bankAccount->id,
                    'type' => 'debit',
                    'amount' => $this->amount,
                    'description' => "Income #{$this->income_no} - {$this->title}"
                ];
            }
        }

        // Credit: Income Account
        $entries[] = [
            'account_id' => $incomeAccount->id,
            'type' => 'credit',
            'amount' => $this->amount,
            'description' => "Income #{$this->income_no} - {$this->title}"
        ];

        foreach ($entries as $entry) {
            if ($entry['account_id']) {
                JournalEntry::create([
                    'account_id' => $entry['account_id'],
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'description' => $entry['description'],
                    'reference_type' => 'income',
                    'reference_id' => $this->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        }
    }

    public function reverseAccounting()
    {
        JournalEntry::where('reference_type', 'income')
            ->where('reference_id', $this->id)
            ->delete();
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('income_date', [$from, $to]);
    }

    // Accessors
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

    public function getSourceLabelAttribute()
    {
        $labels = [
            'sale' => 'Sale',
            'investment' => 'Investment',
            'loan' => 'Loan',
            'other' => 'Other'
        ];
        return $labels[$this->source] ?? ucfirst($this->source);
    }
}
