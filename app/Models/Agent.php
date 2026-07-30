<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agent extends Model
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
        'cnic',
        'commission_type',
        'commission_rate',
        'opening_balance',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($agent) {
            if (empty($agent->code)) {
                $agent->code = 'AGT-' . strtoupper(Str::random(8));
            }
        });
    }

    // Relationships
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function commissionPayments()
    {
        return $this->hasMany(AgentCommissionPayment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getTotalCommissionsAttribute()
    {
        return $this->sales()->sum('commission_amount');
    }

    public function getTotalPaidCommissionAttribute()
    {
        return $this->commissionPayments()->sum('amount');
    }

    public function getBalanceAttribute()
    {
        return $this->opening_balance + $this->total_commissions - $this->total_paid_commission;
    }

    public function getFormattedBalanceAttribute()
    {
        return 'Rs. ' . number_format($this->balance, 2);
    }
}
