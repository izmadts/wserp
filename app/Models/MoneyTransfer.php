<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MoneyTransfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transfer_no',
        'from_account_id',
        'to_account_id',
        'amount',
        'transfer_date',
        'status',
        'description',
        'reference_no',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->transfer_no)) {
                $transfer->transfer_no = 'TRF-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });

        static::created(function ($transfer) {
            if ($transfer->status == 'completed') {
                $transfer->postAccounting();
            }
        });

        static::updated(function ($transfer) {
            if ($transfer->isDirty('status') && $transfer->status == 'completed') {
                $transfer->postAccounting();
            }
        });

        static::deleting(function ($transfer) {
            $transfer->reverseAccounting();
        });
    }

    /**
     * Post accounting entry for money transfer
     */
    public function postAccounting()
    {
        $fromAccount = Account::find($this->from_account_id);
        $toAccount = Account::find($this->to_account_id);

        if (!$fromAccount || !$toAccount) {
            return;
        }

        $entries = [];

        // Credit: From Account
        $entries[] = [
            'account_id' => $this->from_account_id,
            'type' => 'credit',
            'amount' => $this->amount,
            'description' => "Transfer #{$this->transfer_no} - From {$fromAccount->name}"
        ];

        // Debit: To Account
        $entries[] = [
            'account_id' => $this->to_account_id,
            'type' => 'debit',
            'amount' => $this->amount,
            'description' => "Transfer #{$this->transfer_no} - To {$toAccount->name}"
        ];

        foreach ($entries as $entry) {
            JournalEntry::create([
                'account_id' => $entry['account_id'],
                'type' => $entry['type'],
                'amount' => $entry['amount'],
                'description' => $entry['description'],
                'reference_type' => 'money_transfer',
                'reference_id' => $this->id,
                'entry_date' => now()->toDateString(),
            ]);
        }
    }

    public function reverseAccounting()
    {
        JournalEntry::where('reference_type', 'money_transfer')
            ->where('reference_id', $this->id)
            ->delete();
    }

    // Relationships
    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getFormattedAmountAttribute()
    {
        return 'Rs. ' . number_format($this->amount, 2);
    }
}
