<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNotification extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'message',
        'type',
        'is_read',
        'link'
    ];

    protected $casts = [
        'is_read' => 'boolean'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->save();
        return $this;
    }

    public function getTypeLabelAttribute()
    {
        $labels = [
            'registration' => 'Registration',
            'otp' => 'OTP Verification',
            'order' => 'Order Complete',
            'points' => 'Points Added',
            'membership' => 'Membership Upgrade',
            'lucky_draw' => 'Lucky Draw Entry',
            'reward' => 'Reward Redeemed',
            'birthday' => 'Birthday',
            'offer' => 'Special Offer',
            'points_expiring_soon' => 'Points Expiring Soon',
            'points_expired' => 'Points Expired'
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getTypeIconAttribute()
    {
        $icons = [
            'registration' => 'fa-user-plus',
            'otp' => 'fa-key',
            'order' => 'fa-shopping-bag',
            'points' => 'fa-coins',
            'membership' => 'fa-crown',
            'lucky_draw' => 'fa-trophy',
            'reward' => 'fa-gift',
            'birthday' => 'fa-birthday-cake',
            'offer' => 'fa-tags',
            'points_expiring_soon' => 'fa-hourglass-half',
            'points_expired' => 'fa-hourglass-end'
        ];
        return $icons[$this->type] ?? 'fa-bell';
    }
}
