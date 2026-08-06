<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedeemedReward extends Model
{
    protected $fillable = [
        'customer_id',
        'reward_id',
        'points_used',
        'status',
        'notes'
    ];

    protected $casts = [
        'points_used' => 'decimal:2'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-blue-100 text-blue-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800'
        ];
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function approve()
    {
        $this->status = 'approved';
        $this->save();
        return $this;
    }

    public function deliver()
    {
        $this->status = 'delivered';
        $this->save();
        return $this;
    }
}
