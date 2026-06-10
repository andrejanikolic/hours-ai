<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServingTimeSeeder extends Seeder
{
    public function run(): void
    {
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $weekend  = ['saturday', 'sunday'];
        $allDays  = [...$weekdays, ...$weekend];

        // Brand: Demo Burger — open all week, same hours
        $demoBurgerId = DB::table('brands')->where('slug', 'demo-burger')->value('id');
        $this->insert('brand', $demoBurgerId, 'weekday', $allDays, null, null, '08:00', '22:00', true);

        // Brand: Pasta House — weekdays only
        $pastaHouseId = DB::table('brands')->where('slug', 'pasta-house')->value('id');
        $this->insert('brand', $pastaHouseId, 'weekday', $weekdays, null, null, '11:00', '23:00', true);
        $this->insert('brand', $pastaHouseId, 'weekday', $weekend, null, null, null, null, false);

        // Venue: Demo Burger — Downtown (stays open later on weekends)
        $downtownId = DB::table('venues')->where('slug', 'downtown')->value('id');
        $this->insert('venue', $downtownId, 'weekday', $weekdays, null, null, '08:00', '22:00', true);
        $this->insert('venue', $downtownId, 'weekday', $weekend, null, null, '09:00', '23:00', true);
        // Special: closed Christmas
        $this->insert('venue', $downtownId, 'special', null, '2026-12-25', null, null, null, false);

        // Menu: Breakfast — only available weekday mornings
        $breakfastId = DB::table('menus')->where('internal_name', 'BK Morning Menu')->value('id');
        $this->insert('menu', $breakfastId, 'weekday', $weekdays, null, null, '07:00', '11:00', true);
        $this->insert('menu', $breakfastId, 'weekday', $weekend, null, null, '08:00', '13:00', true);

        // ── Starbird ──────────────────────────────────────────────────────
        // Cupertino: Mon–Sat 10:30–22:00, Sun 10:30–21:00
        $cupertino = DB::table('venues')->where('slug', 'cupertino')->value('id');
        $this->insert('venue', $cupertino, 'weekday', ['monday','tuesday','wednesday','thursday','friday','saturday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $cupertino, 'weekday', ['sunday'], null, null, '10:30', '21:00', true);

        // South San Francisco: Mon–Sat 10:30–22:00, Sun 10:30–21:00
        $southSF = DB::table('venues')->where('slug', 'south-san-francisco')->value('id');
        $this->insert('venue', $southSF, 'weekday', ['monday','tuesday','wednesday','thursday','friday','saturday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $southSF, 'weekday', ['sunday'], null, null, '10:30', '21:00', true);

        // Palo Alto: Mon–Sat 10:30–22:00, Sun 10:30–21:00
        $paloAlto = DB::table('venues')->where('slug', 'palo-alto')->value('id');
        $this->insert('venue', $paloAlto, 'weekday', ['monday','tuesday','wednesday','thursday','friday','saturday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $paloAlto, 'weekday', ['sunday'], null, null, '10:30', '21:00', true);

        // Pleasanton: Sun–Tue 10:30–22:00, Wed–Sat 10:30–23:00
        $pleasanton = DB::table('venues')->where('slug', 'pleasanton')->value('id');
        $this->insert('venue', $pleasanton, 'weekday', ['sunday','monday','tuesday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $pleasanton, 'weekday', ['wednesday','thursday','friday','saturday'], null, null, '10:30', '23:00', true);

        // San Francisco SOMA: Mon–Thu 10:30–22:00, Fri–Sat 10:30–22:30, Sun 10:30–21:00
        $soma = DB::table('venues')->where('slug', 'san-francisco-soma')->value('id');
        $this->insert('venue', $soma, 'weekday', ['monday','tuesday','wednesday','thursday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $soma, 'weekday', ['friday','saturday'], null, null, '10:30', '22:30', true);
        $this->insert('venue', $soma, 'weekday', ['sunday'], null, null, '10:30', '21:00', true);

        // SFO Airport Terminal 1B: Daily 05:00–23:00
        $sfo = DB::table('venues')->where('slug', 'sfo-airport-t1b')->value('id');
        $this->insert('venue', $sfo, 'weekday', $allDays, null, null, '05:00', '23:00', true);

        // Cal Memorial Stadium & Levi's Stadium: event-specific, no fixed hours (no serving times inserted)

        // San Jose: Mon–Sat 10:30–22:00, Sun 10:30–21:00
        $sanJose = DB::table('venues')->where('slug', 'san-jose')->value('id');
        $this->insert('venue', $sanJose, 'weekday', ['monday','tuesday','wednesday','thursday','friday','saturday'], null, null, '10:30', '22:00', true);
        $this->insert('venue', $sanJose, 'weekday', ['sunday'], null, null, '10:30', '21:00', true);

        // Order type: Delivery for Downtown — limited hours
        $deliveryVot = DB::table('venue_order_types')
            ->where('venue_id', $downtownId)
            ->where('order_type_id', 2) // delivery
            ->value('id');

        $this->insert('order_type', $deliveryVot, 'weekday', $allDays, null, null, '11:00', '21:00', true);
    }

    private function insert(
        string $parentType,
        int $parentId,
        string $type,
        ?array $days,
        ?string $date,
        ?string $dateTo,
        ?string $timeFrom,
        ?string $timeTo,
        bool $working
    ): void {
        DB::table('serving_times')->insert([
            'parent_type' => $parentType,
            'parent_id'   => $parentId,
            'type'        => $type,
            'days'        => $days ? json_encode($days) : null,
            'date'        => $date,
            'date_to'     => $dateTo,
            'time_from'   => $timeFrom,
            'time_to'     => $timeTo,
            'working'     => $working,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
