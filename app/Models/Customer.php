<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'cnic',
        'ntn',
        'opening_balance',
        'credit_limit',
        'credit_days',
        'notes',
        'is_active',
        'created_by_agent_id',
        'is_agent_customer',
        'activated_at',
        'order_count'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'is_active' => 'boolean',
        'is_agent_customer' => 'boolean',
        'activated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->code)) {
                $customer->code = 'CUS-' . strtoupper(Str::random(8));
            }
        });
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function salePayments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function createdByAgent()
    {
        return $this->belongsTo(User::class, 'created_by_agent_id');
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAgentCustomers($query, $agentId)
    {
        return $query->where('created_by_agent_id', $agentId);
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getTotalSalesAttribute()
    {
        return $this->sales()->sum('total_amount');
    }

    public function getTotalPaidAttribute()
    {
        return $this->salePayments()->sum('amount');
    }

    public function getBalanceAttribute()
    {
        return $this->opening_balance + $this->total_sales - $this->total_paid;
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

    public function getIsNewCustomerAttribute()
    {
        // New customer if active and has at least 3 orders
        return $this->is_active && $this->order_count >= 3;
    }

    public function getNewCustomerBonusEligibleAttribute()
    {
        return $this->is_active && $this->order_count >= 3 && $this->is_agent_customer;
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    public function incrementOrderCount()
    {
        $this->order_count++;
        $this->save();
        return $this;
    }

    public function activate()
    {
        $this->is_active = true;
        $this->activated_at = now();
        $this->save();
        return $this;
    }
}
