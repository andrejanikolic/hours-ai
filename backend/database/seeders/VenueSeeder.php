<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $demoBurgerId = DB::table('brands')->where('slug', 'demo-burger')->value('id');
        $pastaHouseId = DB::table('brands')->where('slug', 'pasta-house')->value('id');

        $venues = [
            // Demo Burger venues
            [
                'brand_id' => $demoBurgerId,
                'name'     => 'Downtown',
                'slug'     => 'downtown',
                'address'  => '123 Main St',
                'city'     => 'New York',
                'country'  => 'US',
                'timezone' => null, // inherits from brand
                'phone'    => '+1 212 555 0101',
                'active'   => true,
            ],
            [
                'brand_id' => $demoBurgerId,
                'name'     => 'Airport Terminal 2',
                'slug'     => 'airport-t2',
                'address'  => 'JFK Terminal 2',
                'city'     => 'New York',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => '+1 212 555 0102',
                'active'   => true,
            ],
            // Pasta House venues
            [
                'brand_id' => $pastaHouseId,
                'name'     => 'Knez Mihailova',
                'slug'     => 'knez-mihailova',
                'address'  => 'Knez Mihailova 12',
                'city'     => 'Belgrade',
                'country'  => 'RS',
                'timezone' => null,
                'phone'    => '+381 11 555 0201',
                'active'   => true,
            ],
            [
                'brand_id' => $pastaHouseId,
                'name'     => 'Novi Sad',
                'slug'     => 'novi-sad',
                'address'  => 'Zmaj Jovina 5',
                'city'     => 'Novi Sad',
                'country'  => 'RS',
                'timezone' => 'Europe/Belgrade',
                'phone'    => '+381 21 555 0202',
                'active'   => true,
            ],
        ];

        foreach ($venues as $venue) {
            DB::table('venues')->insert([
                ...$venue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
