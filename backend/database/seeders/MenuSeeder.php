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
        // Original venues keep their internal names (referenced by ServingTimeSeeder)
        $downtownId = $venue('downtown');
        if ($downtownId) {
            $add($downtownId, 'Breakfast',  'BK Morning Menu', 'Morning items served before 11am.', 1);
            $add($downtownId, 'All Day',    'BK Main Menu',    'Full menu available all day.',      2);
            $add($downtownId, 'Late Night', 'BK Late',         'Reduced menu after 10pm.',          3);
        }
        $airportT2Id = $venue('airport-t2');
        if ($airportT2Id) {
            $add($airportT2Id, 'Breakfast', 'BK Morning Menu T2', 'Morning items served before 11am.', 1);
            $add($airportT2Id, 'All Day',   'BK Main Menu T2',    'Full menu available all day.',      2);
        }
        // All other Demo Burger venues (seeded by DemoBurgerVenuesSeeder)
        $demoBurgerId = DB::table('brands')->where('slug', 'demo-burger')->value('id');
        DB::table('venues')
            ->where('brand_id', $demoBurgerId)
            ->whereNotIn('slug', ['downtown', 'airport-t2'])
            ->pluck('id', 'name')
            ->each(function (int $id, string $name) use ($add) {
                $add($id, 'Breakfast',  null, 'Morning items served before 11am.', 1);
                $add($id, 'All Day',    null, 'Full menu available all day.',      2);
            });

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
