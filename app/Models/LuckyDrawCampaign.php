<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawCampaign extends Model
{
    protected $table = 'lucky_draw_campaigns';

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'minimum_purchase',
        'entry_formula',
        'prizes',
        'status',
        'total_entries'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'minimum_purchase' => 'decimal:2',
        'entry_formula' => 'array',
        'prizes' => 'array',
        'total_entries' => 'integer'
    ];

    public function entries()
    {
        return $this->hasMany(LuckyDrawEntry::class, 'campaign_id');
    }

    public function winners()
    {
        return $this->hasMany(LuckyDrawEntry::class, 'campaign_id')
            ->where('is_winner', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'draft' => 'bg-gray-100 text-gray-800',
            'active' => 'bg-green-100 text-green-800',
            'completed' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800'
        ];
        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getEntriesPerCustomer($customerId)
    {
        return $this->entries()->where('customer_id', $customerId)->sum('entry_count');
    }

    public function getRemainingEntries()
    {
        // Logic to get remaining entries if there's a limit
        return null;
    }
}
