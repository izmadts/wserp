<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\GoldenClubTrait;
use Illuminate\Support\Str;

/**
 * Extends Authenticatable (not the plain Model) so a Customer can hold
 * Sanctum tokens and be resolved by $request->user() on customer API routes
 * - see App\Http\Controllers\Api\Customer\AuthController::connect(). There
 * is no password-based login: identity is established by the seller app's
 * one-time /connect call (behind the integration key), not by a customer
 * typing a password, so getAuthPassword() is never actually invoked.
 */
class Customer extends Authenticatable
{
    use SoftDeletes, HasApiTokens, GoldenClubTrait;

    protected $fillable = [
        'code',
        'referral_code',
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
        'customer_group_id',
        'activated_at',
        'order_count',
        // Golden Club fields
        'membership_level',
        'loyalty_points',
        'lucky_draw_entries',
        'registration_source',
        'otp_verified',
        'club_join_date',
        'total_purchase',
        'lifetime_purchase',
        'last_purchase',
        'customer_rank',
        'shop_name',
        'gps_location',
        'shop_picture',
        'category',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'is_active' => 'boolean',
        'is_agent_customer' => 'boolean',
        'activated_at' => 'datetime',
        'loyalty_points' => 'decimal:2',
        'lucky_draw_entries' => 'integer',
        'otp_verified' => 'boolean',
        'club_join_date' => 'datetime',
        'total_purchase' => 'decimal:2',
        'lifetime_purchase' => 'decimal:2',
        'last_purchase' => 'datetime',
        'customer_rank' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            // Set club join date
            if (!$customer->club_join_date) {
                $customer->club_join_date = now();
            }

            // The code a customer shares to refer someone else - generated
            // up front (not after insert like `code` below) since it has
            // no dependency on the row's own id, matching the
            // Sale::invoice_no / SalesReturn::return_no random-suffix
            // pattern used elsewhere in this codebase.
            if (empty($customer->referral_code)) {
                $customer->referral_code = strtoupper(Str::random(8));
            }
        });

        // Code is generated after insert (not in creating()) so it can use
        // the row's own auto-increment id as the sequence - collision-proof
        // without a separate counter table, matching the IZM-{City}-{seq}
        // format from the Golden Club spec.
        static::created(function ($customer) {
            if (empty($customer->code)) {
                $cityCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $customer->city ?? ''), 0, 3));
                $cityCode = str_pad($cityCode, 3, 'X');
                $customer->code = 'IZM-' . $cityCode . '-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT);
                $customer->saveQuietly();
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

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
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

    /**
     * Sales statuses the ledger actually recognizes as revenue/receivable
     * (see SaleService::applyStockAndAccounting). Draft sales never posted
     * anything and cancelled ones had it reversed - counting them here
     * would show money owed that doesn't exist anywhere in the books.
     */
    private const BALANCE_SALE_STATUSES = ['confirmed', 'partial', 'paid'];

    public function getTotalSalesAttribute()
    {
        return $this->sales()->whereIn('status', self::BALANCE_SALE_STATUSES)->sum('total_amount');
    }

    public function getTotalPaidAttribute()
    {
        // whereHas('sale') excludes payments whose sale has since been
        // soft-deleted - deleting a sale reverses its own accounting, but
        // a payment tied to that now-gone sale must stop counting here too.
        return $this->salePayments()->whereHas('sale')->sum('amount');
    }

    public function getTotalReturnedAttribute()
    {
        return $this->sales()->whereIn('status', self::BALANCE_SALE_STATUSES)->sum('refunded_amount');
    }

    public function getBalanceAttribute()
    {
        return $this->opening_balance + $this->total_sales - $this->total_paid - $this->total_returned;
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
// Add relationships for Golden Club
    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function luckyDrawEntries()
    {
        return $this->hasMany(LuckyDrawEntry::class);
    }

    public function redeemedRewards()
    {
        return $this->hasMany(RedeemedReward::class);
    }

    public function referrals()
    {
        return $this->hasMany(CustomerReferral::class);
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
    public function notifications()
    {
        return $this->hasMany(CustomerNotification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(CustomerNotification::class)->where('is_read', false);
    }
}
