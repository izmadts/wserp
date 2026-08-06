<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'email', 'phone', 'mobile', 'address',
        'city', 'state', 'country', 'postal_code', 'cnic', 'ntn', 'strn',
        'opening_balance', 'credit_limit', 'credit_days', 'notes', 'is_active'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($supplier) {
            if (empty($supplier->code)) {
                // date()-to-the-second with no random component collided
                // whenever two suppliers were created within the same
                // second (e.g. a quick bulk-add) - matches the
                // date+random-suffix pattern Product/Customer/Expense
                // already use for exactly this reason.
                $supplier->code = 'SUP-' . date('dmy') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    // Relationships
    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchasePayments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Only credit purchases create a payable balance - cash purchases are
    // settled at the moment they're recorded and must not count as "owed".
    public function getTotalPurchasesAttribute()
    {
        return $this->purchases()
            ->whereIn('status', ['received', 'partial', 'paid'])
            ->where('payment_term', 'credit')
            ->sum('total_amount') ?? 0;
    }

    // whereHas('purchase', ...) both excludes payments whose purchase was
    // soft-deleted and, critically, excludes payments made against CASH
    // purchases - those were never added to total_purchases above, so
    // counting their payments here was subtracting money that was never
    // added in the first place (the supplier balance going negative on a
    // fully-settled cash purchase).
    public function getTotalPaidAttribute()
    {
        return $this->purchasePayments()
            ->whereHas('purchase', function ($q) {
                $q->where('payment_term', 'credit');
            })
            ->sum('amount') ?? 0;
    }

    // Same credit-only filter as total_purchases - a return against a cash
    // purchase doesn't reduce a payable balance that never counted that
    // purchase to begin with.
    public function getTotalReturnedAttribute()
    {
        return $this->purchaseReturns()
            ->whereHas('purchase', function ($q) {
                $q->where('payment_term', 'credit');
            })
            ->sum('total_amount') ?? 0;
    }

    public function getTotalDueAttribute()
    {
        return $this->total_purchases - $this->total_paid - $this->total_returned;
    }

    public function getBalanceAttribute()
    {
        return ($this->opening_balance ?? 0) + $this->total_due;
    }

    public function getFormattedBalanceAttribute()
    {
        return 'Rs. ' . number_format($this->balance, 2);
    }

    public function getFormattedOpeningBalanceAttribute()
    {
        return 'Rs. ' . number_format($this->opening_balance, 2);
    }

    public function getFormattedCreditLimitAttribute()
    {
        return 'Rs. ' . number_format($this->credit_limit, 2);
    }
}