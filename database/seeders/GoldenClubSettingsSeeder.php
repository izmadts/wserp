<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GoldenClubSetting;

class GoldenClubSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'point_formula',
                'value' => '{"rate": 100}',
                'description' => 'Points per Rs. 100 = 1 point'
            ],
            [
                'key' => 'lucky_draw_formula',
                'value' => '{"amount": 20000, "entries": 1}',
                'description' => '1 entry per Rs. 20,000 purchase'
            ],
            [
                'key' => 'membership_silver',
                'value' => '{"minimum": 100000}',
                'description' => 'Silver membership minimum purchase'
            ],
            [
                'key' => 'membership_gold',
                'value' => '{"minimum": 500000}',
                'description' => 'Gold membership minimum purchase'
            ],
            [
                'key' => 'membership_platinum',
                'value' => '{"minimum": 1000000}',
                'description' => 'Platinum membership minimum purchase'
            ],
            [
                'key' => 'referral_bonus',
                'value' => '{"points": 50}',
                'description' => 'Referral bonus points'
            ],
        ];

        foreach ($settings as $setting) {
            GoldenClubSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description']
                ]
            );
        }

        $this->command->info('✅ Golden Club settings seeded successfully!');
    }
}
