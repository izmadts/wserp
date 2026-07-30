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
                $supplier->code = 'SUP-' . date('dmy-Hi-s');
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ✅ FIXED: Only credit purchases affect supplier balance
    public function getTotalPurchasesAttribute()
    {
        return $this->purchases()
            ->whereIn('status', ['received', 'partial', 'paid'])
            ->where('payment_term', 'credit')
            ->sum('total_amount') ?? 0;
    }

    public function getTotalPaidAttribute()
    {
        return $this->purchasePayments()->sum('amount') ?? 0;
    }

    public function getBalanceAttribute()
    {
        return ($this->opening_balance ?? 0) + $this->total_purchases - $this->total_paid;
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