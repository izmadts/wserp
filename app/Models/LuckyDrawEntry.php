<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawEntry extends Model
{
    protected $fillable = [
        'customer_id',
        'campaign_id',
        'sale_id',
        'purchase_amount',
        'entry_count',
        'is_winner',
        'prize'
    ];

    protected $casts = [
        'purchase_amount' => 'decimal:2',
        'entry_count' => 'integer',
        'is_winner' => 'boolean'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign()
    {
        return $this->belongsTo(LuckyDrawCampaign::class, 'campaign_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeWinners($query)
    {
        return $query->where('is_winner', true);
    }
}
