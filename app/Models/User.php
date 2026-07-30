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
        'is_active',
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
        'sales_target',
        'payout_account_type',
        'payout_account_title',
        'payout_account_number',
        'payout_account_provider',
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

    public function calculateCashCommission($amount)
    {
        if (empty($this->commission_slabs)) {
            // Fallback to single rate if no slabs defined
            return $amount * ($this->commission_rate_cash / 100);
        }

        $slabs = json_decode($this->commission_slabs, true);
        $commission = 0;

        foreach ($slabs as $slab) {
            $from = (float) $slab['from'];
            $to = $slab['to'] ? (float) $slab['to'] : INF;
            $rate = (float) $slab['rate'];

            if ($amount >= $from && ($amount <= $to || $to === INF)) {
                $commission = $amount * ($rate / 100);
                break;
            }
        }

        return $commission;
    }
}
