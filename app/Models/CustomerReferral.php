<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReferral extends Model
{
    protected $fillable = [
        'customer_id',
        'referred_customer',
        'referred_phone',
        'status',
        'reward_points'
    ];

    protected $casts = [
        'reward_points' => 'decimal:2'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'registered' => 'Registered',
            'converted' => 'Converted',
            'rewarded' => 'Rewarded'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'registered' => 'bg-blue-100 text-blue-800',
            'converted' => 'bg-green-100 text-green-800',
            'rewarded' => 'bg-purple-100 text-purple-800'
        ];
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }
}
