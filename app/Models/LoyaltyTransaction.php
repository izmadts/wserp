<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    protected $fillable = [
        'customer_id',
        'sale_id',
        'points',
        'type',
        'expires_at',
        'reference_type',
        'reference_id',
        'remarks'
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'expires_at' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function getTypeLabelAttribute()
    {
        $labels = [
            'earn' => 'Earned',
            'redeem' => 'Redeemed',
            'bonus' => 'Bonus',
            'adjustment' => 'Adjusted',
            'expire' => 'Expired'
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getTypeColorAttribute()
    {
        $colors = [
            'earn' => 'text-green-600',
            'redeem' => 'text-red-600',
            'bonus' => 'text-purple-600',
            'adjustment' => 'text-yellow-600',
            'expire' => 'text-gray-500'
        ];
        return $colors[$this->type] ?? 'text-gray-600';
    }
}
