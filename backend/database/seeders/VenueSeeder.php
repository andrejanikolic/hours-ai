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
        $starbirdId   = DB::table('brands')->where('slug', 'starbird')->value('id');

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
            // Starbird venues
            [
                'brand_id' => $starbirdId,
                'name'     => 'Cupertino',
                'slug'     => 'cupertino',
                'address'  => '20080 Stevens Creek Blvd, Ste 100',
                'city'     => 'Cupertino',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'South San Francisco',
                'slug'     => 'south-san-francisco',
                'address'  => '988 El Camino Real, Ste 2',
                'city'     => 'South San Francisco',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'Palo Alto',
                'slug'     => 'palo-alto',
                'address'  => '2515 El Camino Real, Ste 102',
                'city'     => 'Palo Alto',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'Pleasanton',
                'slug'     => 'pleasanton',
                'address'  => '6455 Owens Drive, Ste 5A',
                'city'     => 'Pleasanton',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'San Francisco SOMA',
                'slug'     => 'san-francisco-soma',
                'address'  => '60 Morris St (SOMA Eats)',
                'city'     => 'San Francisco',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'SFO Airport Terminal 1B',
                'slug'     => 'sfo-airport-t1b',
                'address'  => 'SFO Terminal 1B',
                'city'     => 'San Francisco',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'Cal Memorial Stadium',
                'slug'     => 'cal-memorial-stadium',
                'address'  => '2227 Piedmont Ave',
                'city'     => 'Berkeley',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => "Levi's Stadium",
                'slug'     => 'levis-stadium',
                'address'  => '4900 Marie P DeBartolo Way',
                'city'     => 'Santa Clara',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
                'active'   => true,
            ],
            [
                'brand_id' => $starbirdId,
                'name'     => 'San Jose',
                'slug'     => 'san-jose',
                'address'  => '1088 E Brokaw Rd',
                'city'     => 'San Jose',
                'country'  => 'US',
                'timezone' => null,
                'phone'    => null,
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
