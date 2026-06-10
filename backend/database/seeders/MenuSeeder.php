<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('menus')->truncate();

        $now = now();

        // ── helpers ────────────────────────────────────────────────────────
        $venue = fn(string $slug): ?int => DB::table('venues')->where('slug', $slug)->value('id');

        $rows = [];
        $pos  = 1;
        $add  = function (int $venueId, string $name, ?string $internalName, ?string $description, int $position) use (&$rows, $now): void {
            $rows[] = [
                'venue_id'      => $venueId,
                'name'          => $name,
                'internal_name' => $internalName,
                'description'   => $description,
                'active'        => true,
                'position'      => $position,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        };

        // ── Demo Burger ───────────────────────────────────────────────────
        foreach (['downtown', 'airport-t2'] as $slug) {
            $id = $venue($slug);
            if (!$id) continue;
            $suffix = $slug === 'downtown' ? '' : ' T2';
            $add($id, 'Breakfast',  'BK Morning Menu' . $suffix, 'Morning items served before 11am.', 1);
            $add($id, 'All Day',    'BK Main Menu' . $suffix,    'Full menu available all day.',      2);
            if ($slug === 'downtown') {
                $add($id, 'Late Night', 'BK Late', 'Reduced menu after 10pm.', 3);
            }
        }

        // ── Pasta House ───────────────────────────────────────────────────
        foreach (['knez-mihailova', 'novi-sad'] as $slug) {
            $id = $venue($slug);
            if (!$id) continue;
            $suffix = $slug === 'knez-mihailova' ? '' : ' NS';
            $add($id, 'Lunch',  'PH Lunch' . $suffix,  'Lunch specials 12pm–3pm.', 1);
            $add($id, 'Dinner', 'PH Dinner' . $suffix, 'Full dinner menu.',         2);
        }

        // ── Starbird — standard venues ────────────────────────────────────
        $standardStarbird = [
            'cupertino', 'south-san-francisco', 'palo-alto', 'pleasanton',
            'san-francisco-soma', 'san-jose', 'sunnyvale', 'foster-city',
            'campbell', 'walnut-creek', 'corte-madera',
            'marina-del-rey', 'torrance', 'beverly-grove',
            'denver', 'castle-rock',
        ];
        foreach ($standardStarbird as $slug) {
            $id = $venue($slug);
            if (!$id) continue;
            $add($id, 'Lunch',  'SB Lunch',  'Available from opening until 3pm.',       1);
            $add($id, 'Dinner', 'SB Dinner', 'Available from 3pm until closing.',        2);
        }

        // SFO Airport — single all-day menu (5am–11pm)
        $sfoId = $venue('sfo-airport-t1b');
        if ($sfoId) {
            $add($sfoId, 'All Day', 'SB SFO All Day', 'Full menu available from 5am to 11pm.', 1);
        }

        // Stadium venues — event-driven menu
        foreach (['cal-memorial-stadium', 'levis-stadium'] as $slug) {
            $id = $venue($slug);
            if (!$id) continue;
            $add($id, 'Event Menu', 'SB Event', 'Available during scheduled events only.', 1);
        }

        DB::table('menus')->insert($rows);
    }
}
