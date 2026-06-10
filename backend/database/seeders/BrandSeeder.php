<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Demo Burger',  'slug' => 'demo-burger',  'timezone' => 'America/New_York',    'active' => true],
            ['name' => 'Pasta House',  'slug' => 'pasta-house',  'timezone' => 'Europe/Belgrade',     'active' => true],
            ['name' => 'Starbird',     'slug' => 'starbird',     'timezone' => 'America/Los_Angeles', 'active' => true],
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->insert([
                ...$brand,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
