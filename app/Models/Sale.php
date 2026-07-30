<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no', 'customer_id', 'agent_id', 'sale_date', 'due_date',
        'payment_term', 'status', 'sub_total', 'discount', 'discount_type',
        'tax', 'shipping_cost', 'total_amount', 'commission_amount',
        'commission_paid', 'paid_amount', 'due_amount', 'notes', 'created_by'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'due_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_paid' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($sale) {
            if (empty($sale->invoice_no)) {
                $sale->invoice_no = 'SA-' . date('dmy-Hi-s');
            }
            $sale->calculateTotals();
        });

        static::updating(function ($sale) {
            $sale->calculateTotals();
        });
    }

    public function calculateTotals()
    {
        $discountAmount = $this->discount_type == 'percentage' 
            ? ($this->sub_total * $this->discount / 100) 
            : $this->discount;
            
        $this->total_amount = $this->sub_total - $discountAmount + $this->tax + $this->shipping_cost;
        $this->due_amount = $this->total_amount - $this->paid_amount;
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'confirmed', 'partial']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'paid');
    }

    // Helpers
    public function isPaid()
    {
        return $this->due_amount <= 0;
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->paid_amount = $this->total_amount;
        $this->due_amount = 0;
        $this->save();
    }
    // Agent relationship should use User model
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}