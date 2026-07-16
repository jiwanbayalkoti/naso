<?php

namespace Database\Seeders;

use App\Helpers\OfferType;
use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        Offer::query()->updateOrCreate(
            ['name' => 'Rider loyalty — lower commission'],
            [
                'audience' => 'rider',
                'type' => OfferType::RIDER_COMMISSION_REDUCE,
                'is_active' => false,
                'priority' => 10,
                'window' => 'calendar_month',
                'description' => 'After 5 completed rides this month, platform commission drops to 10%.',
                'config' => [
                    'min_completed' => 5,
                    'commission_percent' => 10,
                ],
            ]
        );

        Offer::query()->updateOrCreate(
            ['name' => 'Shop loyalty — every 5th free'],
            [
                'audience' => 'shop',
                'type' => OfferType::SHOP_NTH_FREE,
                'is_active' => false,
                'priority' => 10,
                'window' => 'calendar_month',
                'description' => 'Every 5th completed delivery this month has Rs 0 delivery fee (platform absorbs; rider still paid).',
                'config' => [
                    'every_n' => 5,
                ],
            ]
        );
    }
}
