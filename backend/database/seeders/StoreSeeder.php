<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = DB::table('stores')->insertGetId(['name' => 'Demo Burger', 'created_at' => now(), 'updated_at' => now()]);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            DB::table('store_hours')->insert([
                'store_id' => $storeId,
                'day'      => $day,
                'open'     => in_array($day, ['saturday', 'sunday']) ? '09:00:00' : '08:00:00',
                'close'    => in_array($day, ['saturday', 'sunday']) ? '23:00:00' : '22:00:00',
                'closed'   => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('store_settings')->insert([
            'store_id'             => $storeId,
            'order_cutoff_minutes' => null,
            'delivery_open'        => null,
            'delivery_close'       => null,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }
}
