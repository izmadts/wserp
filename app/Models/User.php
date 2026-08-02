<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_id',
        'phone',
        'cnic',
        'address',
        'city',
        'guardian_name',
        'whatsapp_number',
        'cnic_front_image',
        'cnic_back_image',
        'personal_photo',
        'basic_salary',
        'fuel_allowance',
        'commission_rate_cash',
        'commission_rate_credit',
        'commission_slabs',
        'sales_target',
        'payout_account_type',
        'payout_account_title',
        'payout_account_number',
        'payout_account_provider',
        'is_active',
        'approved_at',
        'approved_by',
        'admin_note',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'sales_target' => 'array',
        'commission_slabs' => 'array',
        'approved_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    // =============================================
    // ROLE CHECKS
    // =============================================

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isManager()
    {
        return $this->role === 'manager';
    }

    public function isAccountant()
    {
        return $this->role === 'accountant';
    }

    public function isSalesAgent()
    {
        return $this->role === 'sales_agent';
    }

    public function isActive()
    {
        return $this->is_active && !is_null($this->approved_at);
    }

    public function isApproved()
    {
        return !is_null($this->approved_at);
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function customers()
    {
        return $this->hasMany(Customer::class, 'created_by_agent_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'agent_id');
    }

    // ✅ FIX: Add commissionLogs relationship
    public function commissionLogs()
    {
        return $this->hasMany(AgentCommissionLog::class, 'agent_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopeAgents($query)
    {
        return $query->where('role', 'sales_agent');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopePending($query)
    {
        return $query->where('role', 'sales_agent')
            ->where('is_active', false)
            ->whereNull('approved_at');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getFormattedCnicAttribute()
    {
        return $this->cnic ?? '-';
    }

    public function getFormattedPhoneAttribute()
    {
        return $this->phone ?? '-';
    }

    public function getFormattedAddressAttribute()
    {
        return $this->address ?? '-';
    }

    public function getFormattedCityAttribute()
    {
        return $this->city ?? '-';
    }

    public function getStatusLabelAttribute()
    {
        if (!$this->is_active) {
            return 'Pending';
        }
        if (!$this->isApproved()) {
            return 'Pending Approval';
        }
        return 'Active';
    }

    public function getStatusColorAttribute()
    {
        if (!$this->is_active) {
            return 'bg-yellow-100 text-yellow-800';
        }
        if (!$this->isApproved()) {
            return 'bg-orange-100 text-orange-800';
        }
        return 'bg-green-100 text-green-800';
    }
    /**
     * Calculate commission based on sale
     */
    public function calculateCommission($sale)
    {
        $commission = 0;

        // Cash Sales - Tiered Commission
        if ($sale->payment_term == 'cash') {
            $amount = $sale->total_amount;

            if ($amount <= 300000) {
                $rate = 1;
            } elseif ($amount <= 700000) {
                $rate = 1.5;
            } elseif ($amount <= 1500000) {
                $rate = 2;
            } else {
                $rate = 2.5;
            }
            $commission = ($amount * $rate / 100);
        }
        // Credit Sales - 1% on recovered amount
        else if ($sale->payment_term == 'credit') {
            $commission = ($sale->paid_amount * 1 / 100);
        }

        return $commission;
    }

    /**
     * Check New Customer Bonus
     */
    public function checkNewCustomerBonus($customer, $sale)
    {
        // Check if customer is new (created by this agent)
        if ($customer->created_by_agent_id == $this->id && $customer->order_count >= 3) {
            // Check if bonus already given
            $existing = AgentCommissionLog::where('agent_id', $this->id)
                ->where('reference_type', 'new_customer_bonus')
                ->where('reference_id', $customer->id)
                ->exists();

            if (!$existing) {
                $bonusAmount = rand(500, 1000);

                AgentCommissionLog::create([
                    'agent_id' => $this->id,
                    'sale_id' => $sale->id,
                    'reference_type' => 'new_customer_bonus',
                    'reference_id' => $customer->id,
                    'amount' => $bonusAmount,
                    'commission_rate' => 0,
                    'commission_type' => 'bonus',
                    'description' => "New Customer Bonus: {$customer->name}",
                    'is_paid' => false,
                ]);

                return $bonusAmount;
            }
        }
        return 0;
    }

    /**
     * Check Recovery Bonus
     */
    public function checkRecoveryBonus($sale)
    {
        if ($sale->recovery_percentage >= 95) {
            $bonusAmount = $sale->total_amount * 0.005; // 0.5%

            AgentCommissionLog::create([
                'agent_id' => $this->id,
                'sale_id' => $sale->id,
                'reference_type' => 'recovery_bonus',
                'reference_id' => $sale->id,
                'amount' => $bonusAmount,
                'commission_rate' => 0.5,
                'commission_type' => 'bonus',
                'description' => "Recovery Bonus (95%+ recovery)",
                'is_paid' => false,
            ]);

            return $bonusAmount;
        }
        return 0;
    }
}
