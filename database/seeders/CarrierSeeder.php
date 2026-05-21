<?php

namespace Database\Seeders;

use App\Models\Carrier;
use Illuminate\Database\Seeder;

class CarrierSeeder extends Seeder
{
    public function run(): void
    {
        $carriers = [
            [
                'name' => 'Manual delivery',
                'code' => 'manual-delivery',
                'type' => Carrier::TYPE_MANUAL,
            ],
            [
                'name' => 'Store pickup',
                'code' => 'store-pickup',
                'type' => Carrier::TYPE_PICKUP,
            ],
            [
                'name' => 'DHL',
                'code' => 'dhl',
                'type' => Carrier::TYPE_COURIER,
                'website_url' => 'https://www.dhl.com',
                'tracking_url_template' => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id={tracking_number}',
            ],
            [
                'name' => 'UPS',
                'code' => 'ups',
                'type' => Carrier::TYPE_COURIER,
                'website_url' => 'https://www.ups.com',
                'tracking_url_template' => 'https://www.ups.com/track?tracknum={tracking_number}',
            ],
            [
                'name' => 'FedEx',
                'code' => 'fedex',
                'type' => Carrier::TYPE_COURIER,
                'website_url' => 'https://www.fedex.com',
                'tracking_url_template' => 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}',
            ],
            [
                'name' => 'USPS',
                'code' => 'usps',
                'type' => Carrier::TYPE_COURIER,
                'website_url' => 'https://www.usps.com',
                'tracking_url_template' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',
            ],
            [
                'name' => 'Local courier',
                'code' => 'local-courier',
                'type' => Carrier::TYPE_LOCAL_DELIVERY,
            ],
        ];

        foreach ($carriers as $carrier) {
            Carrier::query()->updateOrCreate(
                ['code' => $carrier['code']],
                [
                    'name' => $carrier['name'],
                    'type' => $carrier['type'],
                    'website_url' => $carrier['website_url'] ?? null,
                    'tracking_url_template' => $carrier['tracking_url_template'] ?? null,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
