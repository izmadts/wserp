<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'description',
        'image',
        'reward_type',
        'eligible_membership_levels',
        'points_required',
        'stock',
        'metadata',
        'status'
    ];

    protected $casts = [
        'points_required' => 'decimal:2',
        'stock' => 'integer',
        'metadata' => 'array',
        'eligible_membership_levels' => 'array'
    ];

    public function redeemedRewards()
    {
        return $this->hasMany(RedeemedReward::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getRewardTypeLabelAttribute()
    {
        $labels = [
            'gift' => 'Gift',
            'coupon' => 'Coupon',
            'product' => 'Product',
            'discount' => 'Discount',
            'free_delivery' => 'Free Delivery',
            'lucky_draw_entry' => 'Lucky Draw Entry',
        ];
        return $labels[$this->reward_type] ?? ucfirst(str_replace('_', ' ', $this->reward_type));
    }

    public function getFormattedPointsRequiredAttribute()
    {
        return number_format($this->points_required, 0);
    }

    /**
     * A product-linked reward's real stock IS the product's current_stock -
     * rewards.stock is only meaningful for a free-text (unlinked) reward.
     * Types with no physical fulfillment at all (coupon/discount/
     * free_delivery/lucky_draw_entry, when not product-linked) are never
     * stock-limited.
     */
    public function isInStock()
    {
        if ($this->product_id) {
            return $this->product && (float) $this->product->current_stock > 0;
        }

        if (in_array($this->reward_type, ['product', 'gift'])) {
            return $this->stock > 0;
        }

        return true;
    }

    public function isAvailableToCustomer(Customer $customer): bool
    {
        if (empty($this->eligible_membership_levels)) {
            return true;
        }

        return in_array($customer->membership_level, $this->eligible_membership_levels, true);
    }

    /**
     * Validates and spends the points; the RedeemedReward this creates
     * always starts 'pending' (via Customer::redeemPoints()) EXCEPT
     * lucky_draw_entry, which has no physical fulfillment step for an
     * admin to approve/deliver - its entry is created immediately and the
     * record is marked 'delivered' right here.
     */
    public function redeem($customerId)
    {
        if (!$this->isInStock()) {
            throw new \Exception('Reward out of stock!');
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            throw new \Exception('Customer not found!');
        }

        if (!$this->isAvailableToCustomer($customer)) {
            $tiers = collect($this->eligible_membership_levels)->map(fn ($level) => ucfirst($level))->implode('/');
            throw new \Exception("This reward is reserved for {$tiers} members.");
        }

        if ($customer->loyalty_points < $this->points_required) {
            throw new \Exception('Insufficient points!');
        }

        $redeemedReward = $customer->redeemPoints($this->points_required, $this->id, "Redeemed: {$this->title}");

        if ($this->reward_type === 'lucky_draw_entry') {
            $entryCount = (int) ($this->metadata['entry_count'] ?? 1);
            $campaignId = $this->metadata['campaign_id'] ?? null;
            $campaign = $campaignId
                ? LuckyDrawCampaign::find($campaignId)
                : LuckyDrawCampaign::active()->first();

            if (!$campaign) {
                throw new \Exception('No active lucky draw campaign to enter right now - please try again later.');
            }

            LuckyDrawEntry::create([
                'customer_id' => $customer->id,
                'campaign_id' => $campaign->id,
                'purchase_amount' => 0,
                'entry_count' => $entryCount,
                'is_winner' => false,
            ]);

            $customer->lucky_draw_entries += $entryCount;
            $customer->save();

            $campaign->increment('total_entries', $entryCount);

            $redeemedReward->update(['status' => 'delivered']);
        }

        return $redeemedReward;
    }
}
