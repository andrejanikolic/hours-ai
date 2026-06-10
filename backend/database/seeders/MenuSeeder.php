<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $demoBurgerDowntownId = DB::table('venues')->where('slug', 'downtown')->value('id');
        $pastaHouseKnezId     = DB::table('venues')->where('slug', 'knez-mihailova')->value('id');

        $menus = [
            // Demo Burger — Downtown venue menus
            [
                'venue_id'      => $demoBurgerDowntownId,
                'name'          => 'Breakfast',
                'internal_name' => 'BK Morning Menu',
                'description'   => 'Morning items served before 11am.',
                'active'        => true,
                'position'      => 1,
            ],
            [
                'venue_id'      => $demoBurgerDowntownId,
                'name'          => 'All Day',
                'internal_name' => 'BK Main Menu',
                'description'   => 'Full menu available all day.',
                'active'        => true,
                'position'      => 2,
            ],
            [
                'venue_id'      => $demoBurgerDowntownId,
                'name'          => 'Late Night',
                'internal_name' => 'BK Late',
                'description'   => 'Reduced menu after 10pm.',
                'active'        => true,
                'position'      => 3,
            ],
            // Pasta House — Knez Mihailova venue menus
            [
                'venue_id'      => $pastaHouseKnezId,
                'name'          => 'Lunch',
                'internal_name' => 'PH Lunch',
                'description'   => 'Lunch specials 12pm–3pm.',
                'active'        => true,
                'position'      => 1,
            ],
            [
                'venue_id'      => $pastaHouseKnezId,
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
