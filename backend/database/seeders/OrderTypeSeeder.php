<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $orderTypes = [
            ['id' => 1, 'name' => 'Pickup',            'slug' => 'pickup'],
            ['id' => 2, 'name' => 'Delivery',          'slug' => 'delivery'],
            ['id' => 3, 'name' => 'Dine In',           'slug' => 'dine-in'],
            ['id' => 4, 'name' => 'Drive Thru',        'slug' => 'drive-thru'],
            ['id' => 5, 'name' => 'Catering Delivery', 'slug' => 'catering-delivery'],
        ];

        DB::table('order_types')->insert($orderTypes);

        // Attach all 4 order types to every venue
        $venues = DB::table('venues')->pluck('id');

        foreach ($venues as $venueId) {
            foreach ($orderTypes as $orderType) {
                DB::table('venue_order_types')->insert([
                    'venue_id'      => $venueId,
                    'order_type_id' => $orderType['id'],
                    'active'        => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }
}
