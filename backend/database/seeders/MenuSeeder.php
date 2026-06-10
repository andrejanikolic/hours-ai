<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $demoBurgerId = DB::table('brands')->where('slug', 'demo-burger')->value('id');
        $pastaHouseId = DB::table('brands')->where('slug', 'pasta-house')->value('id');

        $menus = [
            // Demo Burger menus
            [
                'brand_id'      => $demoBurgerId,
                'name'          => 'Breakfast',
                'internal_name' => 'BK Morning Menu',
                'description'   => 'Morning items served before 11am.',
                'active'        => true,
                'position'      => 1,
            ],
            [
                'brand_id'      => $demoBurgerId,
                'name'          => 'All Day',
                'internal_name' => 'BK Main Menu',
                'description'   => 'Full menu available all day.',
                'active'        => true,
                'position'      => 2,
            ],
            [
                'brand_id'      => $demoBurgerId,
                'name'          => 'Late Night',
                'internal_name' => 'BK Late',
                'description'   => 'Reduced menu after 10pm.',
                'active'        => true,
                'position'      => 3,
            ],
            // Pasta House menus
            [
                'brand_id'      => $pastaHouseId,
                'name'          => 'Lunch',
                'internal_name' => 'PH Lunch',
                'description'   => 'Lunch specials 12pm–3pm.',
                'active'        => true,
                'position'      => 1,
            ],
            [
                'brand_id'      => $pastaHouseId,
                'name'          => 'Dinner',
                'internal_name' => 'PH Dinner',
                'description'   => 'Full dinner menu.',
                'active'        => true,
                'position'      => 2,
            ],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->insert([
                ...$menu,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
