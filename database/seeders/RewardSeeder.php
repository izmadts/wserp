<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;

/**
 * A fresh install previously started with an empty Golden Club reward
 * store - the admin had to hand-create every reward before a customer
 * could redeem anything. These are the owner's own example catalog and
 * point values, seeded as free-text (unlinked) rewards since there's no
 * guarantee a matching real product exists yet on a fresh install - link
 * them to a real product later via Admin > Golden Club > Rewards > Edit
 * once that product exists and is flagged "Loyalty eligible".
 */
class RewardSeeder extends Seeder
{
    public function run(): void
    {
        if (Reward::count() > 0) {
            return;
        }

        $rewards = [
            ['title' => 'IZMA Shopping Bag', 'reward_type' => 'gift', 'points_required' => 500, 'stock' => 50],
            ['title' => 'Cap', 'reward_type' => 'gift', 'points_required' => 700, 'stock' => 50],
            ['title' => 'Apron', 'reward_type' => 'gift', 'points_required' => 1200, 'stock' => 30],
            ['title' => 'Organic Honey 250g', 'reward_type' => 'gift', 'points_required' => 2000, 'stock' => 30],
            ['title' => 'Cold Pressed Oil 500ml', 'reward_type' => 'gift', 'points_required' => 3500, 'stock' => 20],
            ['title' => 'Gift Basket', 'reward_type' => 'gift', 'points_required' => 8000, 'stock' => 10],
        ];

        foreach ($rewards as $reward) {
            Reward::create(array_merge($reward, [
                'description' => null,
                'status' => 'active',
            ]));
        }

        echo "✅ Golden Club starter reward catalog seeded successfully!" . PHP_EOL;
    }
}
