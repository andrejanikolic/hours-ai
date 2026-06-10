<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_types')->insert([
            'id'   => 5,
            'name' => 'Catering Delivery',
            'slug' => 'catering-delivery',
        ]);

        $venues = DB::table('venues')->pluck('id');

        foreach ($venues as $venueId) {
            DB::table('venue_order_types')->insert([
                'venue_id'      => $venueId,
                'order_type_id' => 5,
                'active'        => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('venue_order_types')->where('order_type_id', 5)->delete();
        DB::table('order_types')->where('id', 5)->delete();
    }
};
