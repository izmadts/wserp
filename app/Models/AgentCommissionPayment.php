<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCommissionLog extends Model
{
    protected $fillable = [
        'agent_id',
        'sale_id',
        'reference_type',
        'reference_id',
        'amount',
        'commission_rate',
        'commission_type',
        'description',
        'is_paid',
        'paid_date',
        'paid_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_date' => 'date',
    ];

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // Scopes
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return 'Rs. ' . number_format($this->amount, 2);
    }

    public function getTypeLabelAttribute()
    {
        $labels = [
            'sale' => 'Sale Commission',
            'target_bonus' => 'Target Bonus',
            'new_customer_bonus' => 'New Customer Bonus',
            'recovery_bonus' => 'Recovery Bonus'
        ];
        return $labels[$this->reference_type] ?? ucfirst($this->reference_type);
    }
}
