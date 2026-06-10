<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoBurgerVenuesSeeder extends Seeder
{
    public function run(): void
    {
        $brandId = DB::table('brands')->where('slug', 'demo-burger')->value('id');

        // Remove any previously seeded extra venues (keep the 2 originals)
        DB::table('venues')
            ->where('brand_id', $brandId)
            ->whereNotIn('slug', ['downtown', 'airport-t2'])
            ->delete();

        $now  = now();
        $rows = [];

        // 98 US cities — each becomes one Demo Burger venue
        $cities = [
            ['name' => 'Los Angeles',       'slug' => 'db-los-angeles',       'address' => '6800 Sunset Blvd',         'city' => 'Los Angeles',       'tz' => 'America/Los_Angeles', 'phone' => '+1 213 555 0201'],
            ['name' => 'Chicago',            'slug' => 'db-chicago',           'address' => '111 W Wacker Dr',          'city' => 'Chicago',            'tz' => 'America/Chicago',     'phone' => '+1 312 555 0202'],
            ['name' => 'Houston',            'slug' => 'db-houston',           'address' => '500 McKinney St',          'city' => 'Houston',            'tz' => 'America/Chicago',     'phone' => '+1 713 555 0203'],
            ['name' => 'Phoenix',            'slug' => 'db-phoenix',           'address' => '1 E Washington St',        'city' => 'Phoenix',            'tz' => 'America/Phoenix',     'phone' => '+1 602 555 0204'],
            ['name' => 'Philadelphia',       'slug' => 'db-philadelphia',      'address' => '1500 Market St',           'city' => 'Philadelphia',       'tz' => 'America/New_York',    'phone' => '+1 215 555 0205'],
            ['name' => 'San Antonio',        'slug' => 'db-san-antonio',       'address' => '300 Alamo Plaza',          'city' => 'San Antonio',        'tz' => 'America/Chicago',     'phone' => '+1 210 555 0206'],
            ['name' => 'San Diego',          'slug' => 'db-san-diego',         'address' => '789 Broadway',             'city' => 'San Diego',          'tz' => 'America/Los_Angeles', 'phone' => '+1 619 555 0207'],
            ['name' => 'Dallas',             'slug' => 'db-dallas',            'address' => '400 S Akard St',           'city' => 'Dallas',             'tz' => 'America/Chicago',     'phone' => '+1 214 555 0208'],
            ['name' => 'San Jose',           'slug' => 'db-san-jose',          'address' => '200 E Santa Clara St',     'city' => 'San Jose',           'tz' => 'America/Los_Angeles', 'phone' => '+1 408 555 0209'],
            ['name' => 'Austin',             'slug' => 'db-austin',            'address' => '600 Congress Ave',         'city' => 'Austin',             'tz' => 'America/Chicago',     'phone' => '+1 512 555 0210'],
            ['name' => 'Jacksonville',       'slug' => 'db-jacksonville',      'address' => '1 Independent Dr',         'city' => 'Jacksonville',       'tz' => 'America/New_York',    'phone' => '+1 904 555 0211'],
            ['name' => 'Fort Worth',         'slug' => 'db-fort-worth',        'address' => '201 Main St',              'city' => 'Fort Worth',         'tz' => 'America/Chicago',     'phone' => '+1 817 555 0212'],
            ['name' => 'Columbus',           'slug' => 'db-columbus',          'address' => '50 W Broad St',            'city' => 'Columbus',           'tz' => 'America/New_York',    'phone' => '+1 614 555 0213'],
            ['name' => 'Charlotte',          'slug' => 'db-charlotte',         'address' => '100 N Tryon St',           'city' => 'Charlotte',          'tz' => 'America/New_York',    'phone' => '+1 704 555 0214'],
            ['name' => 'Indianapolis',       'slug' => 'db-indianapolis',      'address' => '200 S Meridian St',        'city' => 'Indianapolis',       'tz' => 'America/Indiana/Indianapolis', 'phone' => '+1 317 555 0215'],
            ['name' => 'San Francisco',      'slug' => 'db-san-francisco',     'address' => '1 Market St',              'city' => 'San Francisco',      'tz' => 'America/Los_Angeles', 'phone' => '+1 415 555 0216'],
            ['name' => 'Seattle',            'slug' => 'db-seattle',           'address' => '400 Pike St',              'city' => 'Seattle',            'tz' => 'America/Los_Angeles', 'phone' => '+1 206 555 0217'],
            ['name' => 'Denver',             'slug' => 'db-denver',            'address' => '1600 Glenarm Pl',          'city' => 'Denver',             'tz' => 'America/Denver',      'phone' => '+1 303 555 0218'],
            ['name' => 'Nashville',          'slug' => 'db-nashville',         'address' => '301 Broadway',             'city' => 'Nashville',          'tz' => 'America/Chicago',     'phone' => '+1 615 555 0219'],
            ['name' => 'Oklahoma City',      'slug' => 'db-oklahoma-city',     'address' => '100 N Harvey Ave',         'city' => 'Oklahoma City',      'tz' => 'America/Chicago',     'phone' => '+1 405 555 0220'],
            ['name' => 'El Paso',            'slug' => 'db-el-paso',           'address' => '300 N Stanton St',         'city' => 'El Paso',            'tz' => 'America/Denver',      'phone' => '+1 915 555 0221'],
            ['name' => 'Washington DC',      'slug' => 'db-washington-dc',     'address' => '601 F St NW',              'city' => 'Washington',         'tz' => 'America/New_York',    'phone' => '+1 202 555 0222'],
            ['name' => 'Boston',             'slug' => 'db-boston',            'address' => '100 Tremont St',           'city' => 'Boston',             'tz' => 'America/New_York',    'phone' => '+1 617 555 0223'],
            ['name' => 'Memphis',            'slug' => 'db-memphis',           'address' => '149 Union Ave',            'city' => 'Memphis',            'tz' => 'America/Chicago',     'phone' => '+1 901 555 0224'],
            ['name' => 'Louisville',         'slug' => 'db-louisville',        'address' => '500 W Main St',            'city' => 'Louisville',         'tz' => 'America/Kentucky/Louisville', 'phone' => '+1 502 555 0225'],
            ['name' => 'Baltimore',          'slug' => 'db-baltimore',         'address' => '300 Light St',             'city' => 'Baltimore',          'tz' => 'America/New_York',    'phone' => '+1 410 555 0226'],
            ['name' => 'Milwaukee',          'slug' => 'db-milwaukee',         'address' => '310 W Wisconsin Ave',      'city' => 'Milwaukee',          'tz' => 'America/Chicago',     'phone' => '+1 414 555 0227'],
            ['name' => 'Albuquerque',        'slug' => 'db-albuquerque',       'address' => '500 Marquette Ave NW',     'city' => 'Albuquerque',        'tz' => 'America/Denver',      'phone' => '+1 505 555 0228'],
            ['name' => 'Tucson',             'slug' => 'db-tucson',            'address' => '33 N Stone Ave',           'city' => 'Tucson',             'tz' => 'America/Phoenix',     'phone' => '+1 520 555 0229'],
            ['name' => 'Fresno',             'slug' => 'db-fresno',            'address' => '2550 W Shaw Ave',          'city' => 'Fresno',             'tz' => 'America/Los_Angeles', 'phone' => '+1 559 555 0230'],
            ['name' => 'Sacramento',         'slug' => 'db-sacramento',        'address' => '1600 K St',                'city' => 'Sacramento',         'tz' => 'America/Los_Angeles', 'phone' => '+1 916 555 0231'],
            ['name' => 'Mesa',               'slug' => 'db-mesa',              'address' => '252 W Main St',            'city' => 'Mesa',               'tz' => 'America/Phoenix',     'phone' => '+1 480 555 0232'],
            ['name' => 'Kansas City',        'slug' => 'db-kansas-city',       'address' => '2450 Grand Blvd',          'city' => 'Kansas City',        'tz' => 'America/Chicago',     'phone' => '+1 816 555 0233'],
            ['name' => 'Atlanta',            'slug' => 'db-atlanta',           'address' => '190 Marietta St NW',       'city' => 'Atlanta',            'tz' => 'America/New_York',    'phone' => '+1 404 555 0234'],
            ['name' => 'Omaha',              'slug' => 'db-omaha',             'address' => '1299 Farnam St',           'city' => 'Omaha',              'tz' => 'America/Chicago',     'phone' => '+1 402 555 0235'],
            ['name' => 'Colorado Springs',   'slug' => 'db-colorado-springs',  'address' => '16 S Tejon St',            'city' => 'Colorado Springs',   'tz' => 'America/Denver',      'phone' => '+1 719 555 0236'],
            ['name' => 'Raleigh',            'slug' => 'db-raleigh',           'address' => '300 Fayetteville St',      'city' => 'Raleigh',            'tz' => 'America/New_York',    'phone' => '+1 919 555 0237'],
            ['name' => 'Long Beach',         'slug' => 'db-long-beach',        'address' => '333 W Ocean Blvd',         'city' => 'Long Beach',         'tz' => 'America/Los_Angeles', 'phone' => '+1 562 555 0238'],
            ['name' => 'Virginia Beach',     'slug' => 'db-virginia-beach',    'address' => '2101 Parks Ave',           'city' => 'Virginia Beach',     'tz' => 'America/New_York',    'phone' => '+1 757 555 0239'],
            ['name' => 'Minneapolis',        'slug' => 'db-minneapolis',       'address' => '900 Nicollet Mall',        'city' => 'Minneapolis',        'tz' => 'America/Chicago',     'phone' => '+1 612 555 0240'],
            ['name' => 'Tampa',              'slug' => 'db-tampa',             'address' => '400 N Ashley Dr',          'city' => 'Tampa',              'tz' => 'America/New_York',    'phone' => '+1 813 555 0241'],
            ['name' => 'New Orleans',        'slug' => 'db-new-orleans',       'address' => '701 Poydras St',           'city' => 'New Orleans',        'tz' => 'America/Chicago',     'phone' => '+1 504 555 0242'],
            ['name' => 'Arlington',          'slug' => 'db-arlington',         'address' => '601 Six Flags Dr',         'city' => 'Arlington',          'tz' => 'America/Chicago',     'phone' => '+1 817 555 0243'],
            ['name' => 'Bakersfield',        'slug' => 'db-bakersfield',       'address' => '1414 18th St',             'city' => 'Bakersfield',        'tz' => 'America/Los_Angeles', 'phone' => '+1 661 555 0244'],
            ['name' => 'Honolulu',           'slug' => 'db-honolulu',          'address' => '2270 Kalakaua Ave',        'city' => 'Honolulu',           'tz' => 'Pacific/Honolulu',    'phone' => '+1 808 555 0245'],
            ['name' => 'Anaheim',            'slug' => 'db-anaheim',           'address' => '400 W Disney Way',         'city' => 'Anaheim',            'tz' => 'America/Los_Angeles', 'phone' => '+1 714 555 0246'],
            ['name' => 'Aurora',             'slug' => 'db-aurora',            'address' => '1401 Dallas St',           'city' => 'Aurora',             'tz' => 'America/Denver',      'phone' => '+1 303 555 0247'],
            ['name' => 'Santa Ana',          'slug' => 'db-santa-ana',         'address' => '200 W 5th St',             'city' => 'Santa Ana',          'tz' => 'America/Los_Angeles', 'phone' => '+1 714 555 0248'],
            ['name' => 'Corpus Christi',     'slug' => 'db-corpus-christi',    'address' => '101 N Shoreline Blvd',     'city' => 'Corpus Christi',     'tz' => 'America/Chicago',     'phone' => '+1 361 555 0249'],
            ['name' => 'Riverside',          'slug' => 'db-riverside',         'address' => '3750 Market St',           'city' => 'Riverside',          'tz' => 'America/Los_Angeles', 'phone' => '+1 951 555 0250'],
            ['name' => 'St. Louis',          'slug' => 'db-st-louis',          'address' => '1 Broadway',               'city' => 'St. Louis',          'tz' => 'America/Chicago',     'phone' => '+1 314 555 0251'],
            ['name' => 'Lexington',          'slug' => 'db-lexington',         'address' => '430 W Vine St',            'city' => 'Lexington',          'tz' => 'America/New_York',    'phone' => '+1 859 555 0252'],
            ['name' => 'Pittsburgh',         'slug' => 'db-pittsburgh',        'address' => '600 Grant St',             'city' => 'Pittsburgh',         'tz' => 'America/New_York',    'phone' => '+1 412 555 0253'],
            ['name' => 'Stockton',           'slug' => 'db-stockton',          'address' => '1150 W Robinhood Dr',      'city' => 'Stockton',           'tz' => 'America/Los_Angeles', 'phone' => '+1 209 555 0254'],
            ['name' => 'Cincinnati',         'slug' => 'db-cincinnati',        'address' => '525 Vine St',              'city' => 'Cincinnati',         'tz' => 'America/New_York',    'phone' => '+1 513 555 0255'],
            ['name' => 'Greensboro',         'slug' => 'db-greensboro',        'address' => '200 N Elm St',             'city' => 'Greensboro',         'tz' => 'America/New_York',    'phone' => '+1 336 555 0256'],
            ['name' => 'Anchorage',          'slug' => 'db-anchorage',         'address' => '630 W 6th Ave',            'city' => 'Anchorage',          'tz' => 'America/Anchorage',   'phone' => '+1 907 555 0257'],
            ['name' => 'Henderson',          'slug' => 'db-henderson',         'address' => '100 Water St',             'city' => 'Henderson',          'tz' => 'America/Los_Angeles', 'phone' => '+1 702 555 0258'],
            ['name' => 'Lincoln',            'slug' => 'db-lincoln',           'address' => '100 N 10th St',            'city' => 'Lincoln',            'tz' => 'America/Chicago',     'phone' => '+1 402 555 0259'],
            ['name' => 'Plano',              'slug' => 'db-plano',             'address' => '5901 Legacy Dr',           'city' => 'Plano',              'tz' => 'America/Chicago',     'phone' => '+1 972 555 0260'],
            ['name' => 'Orlando',            'slug' => 'db-orlando',           'address' => '20 W Church St',           'city' => 'Orlando',            'tz' => 'America/New_York',    'phone' => '+1 407 555 0261'],
            ['name' => 'Irvine',             'slug' => 'db-irvine',            'address' => '2600 Michelson Dr',        'city' => 'Irvine',             'tz' => 'America/Los_Angeles', 'phone' => '+1 949 555 0262'],
            ['name' => 'Newark',             'slug' => 'db-newark',            'address' => '1 Raymond Plaza W',        'city' => 'Newark',             'tz' => 'America/New_York',    'phone' => '+1 973 555 0263'],
            ['name' => 'Toledo',             'slug' => 'db-toledo',            'address' => '300 Madison Ave',          'city' => 'Toledo',             'tz' => 'America/New_York',    'phone' => '+1 419 555 0264'],
            ['name' => 'Chandler',           'slug' => 'db-chandler',          'address' => '260 S Arizona Ave',        'city' => 'Chandler',           'tz' => 'America/Phoenix',     'phone' => '+1 480 555 0265'],
            ['name' => 'Laredo',             'slug' => 'db-laredo',            'address' => '1300 Matamoros St',        'city' => 'Laredo',             'tz' => 'America/Chicago',     'phone' => '+1 956 555 0266'],
            ['name' => 'Madison',            'slug' => 'db-madison',           'address' => '30 W Mifflin St',          'city' => 'Madison',            'tz' => 'America/Chicago',     'phone' => '+1 608 555 0267'],
            ['name' => 'Durham',             'slug' => 'db-durham',            'address' => '333 Corcoran St',          'city' => 'Durham',             'tz' => 'America/New_York',    'phone' => '+1 919 555 0268'],
            ['name' => 'Lubbock',            'slug' => 'db-lubbock',           'address' => '1500 Broadway',            'city' => 'Lubbock',            'tz' => 'America/Chicago',     'phone' => '+1 806 555 0269'],
            ['name' => 'Winston-Salem',      'slug' => 'db-winston-salem',     'address' => '101 N Cherry St',          'city' => 'Winston-Salem',      'tz' => 'America/New_York',    'phone' => '+1 336 555 0270'],
            ['name' => 'Garland',            'slug' => 'db-garland',           'address' => '200 N 5th St',             'city' => 'Garland',            'tz' => 'America/Chicago',     'phone' => '+1 972 555 0271'],
            ['name' => 'Glendale AZ',        'slug' => 'db-glendale-az',       'address' => '5750 W Glenn Dr',          'city' => 'Glendale',           'tz' => 'America/Phoenix',     'phone' => '+1 623 555 0272'],
            ['name' => 'Hialeah',            'slug' => 'db-hialeah',           'address' => '501 Palm Ave',             'city' => 'Hialeah',            'tz' => 'America/New_York',    'phone' => '+1 305 555 0273'],
            ['name' => 'Reno',               'slug' => 'db-reno',              'address' => '1 S Center St',            'city' => 'Reno',               'tz' => 'America/Los_Angeles', 'phone' => '+1 775 555 0274'],
            ['name' => 'Baton Rouge',        'slug' => 'db-baton-rouge',       'address' => '100 Lafayette St',         'city' => 'Baton Rouge',        'tz' => 'America/Chicago',     'phone' => '+1 225 555 0275'],
            ['name' => 'Norfolk',            'slug' => 'db-norfolk',           'address' => '100 E Main St',            'city' => 'Norfolk',            'tz' => 'America/New_York',    'phone' => '+1 757 555 0276'],
            ['name' => 'Chesapeake',         'slug' => 'db-chesapeake',        'address' => '306 Cedar Rd',             'city' => 'Chesapeake',         'tz' => 'America/New_York',    'phone' => '+1 757 555 0277'],
            ['name' => 'Irving',             'slug' => 'db-irving',            'address' => '825 W Irving Blvd',        'city' => 'Irving',             'tz' => 'America/Chicago',     'phone' => '+1 972 555 0278'],
            ['name' => 'Scottsdale',         'slug' => 'db-scottsdale',        'address' => '7014 E Camelback Rd',      'city' => 'Scottsdale',         'tz' => 'America/Phoenix',     'phone' => '+1 480 555 0279'],
            ['name' => 'North Las Vegas',    'slug' => 'db-north-las-vegas',   'address' => '2250 Civic Center Dr',     'city' => 'North Las Vegas',    'tz' => 'America/Los_Angeles', 'phone' => '+1 702 555 0280'],
            ['name' => 'Fremont',            'slug' => 'db-fremont',           'address' => '3300 Capitol Ave',         'city' => 'Fremont',            'tz' => 'America/Los_Angeles', 'phone' => '+1 510 555 0281'],
            ['name' => 'Gilbert',            'slug' => 'db-gilbert',           'address' => '50 E Civic Center Dr',     'city' => 'Gilbert',            'tz' => 'America/Phoenix',     'phone' => '+1 480 555 0282'],
            ['name' => 'San Bernardino',     'slug' => 'db-san-bernardino',    'address' => '290 N D St',               'city' => 'San Bernardino',     'tz' => 'America/Los_Angeles', 'phone' => '+1 909 555 0283'],
            ['name' => 'Birmingham',         'slug' => 'db-birmingham',        'address' => '2100 Richard Arrington Jr Blvd N', 'city' => 'Birmingham', 'tz' => 'America/Chicago',  'phone' => '+1 205 555 0284'],
            ['name' => 'Boise',              'slug' => 'db-boise',             'address' => '150 N Capitol Blvd',       'city' => 'Boise',              'tz' => 'America/Boise',       'phone' => '+1 208 555 0285'],
            ['name' => 'Rochester',          'slug' => 'db-rochester',         'address' => '30 Church St',             'city' => 'Rochester',          'tz' => 'America/New_York',    'phone' => '+1 585 555 0286'],
            ['name' => 'Richmond',           'slug' => 'db-richmond',          'address' => '900 E Broad St',           'city' => 'Richmond',           'tz' => 'America/New_York',    'phone' => '+1 804 555 0287'],
            ['name' => 'Spokane',            'slug' => 'db-spokane',           'address' => '808 W Spokane Falls Blvd', 'city' => 'Spokane',            'tz' => 'America/Los_Angeles', 'phone' => '+1 509 555 0288'],
            ['name' => 'Des Moines',         'slug' => 'db-des-moines',        'address' => '400 Robert D Ray Dr',      'city' => 'Des Moines',         'tz' => 'America/Chicago',     'phone' => '+1 515 555 0289'],
            ['name' => 'Montgomery',         'slug' => 'db-montgomery',        'address' => '301 Adams Ave',            'city' => 'Montgomery',         'tz' => 'America/Chicago',     'phone' => '+1 334 555 0290'],
            ['name' => 'Modesto',            'slug' => 'db-modesto',           'address' => '1010 10th St',             'city' => 'Modesto',            'tz' => 'America/Los_Angeles', 'phone' => '+1 209 555 0291'],
            ['name' => 'Fayetteville',       'slug' => 'db-fayetteville',      'address' => '433 Hay St',               'city' => 'Fayetteville',       'tz' => 'America/New_York',    'phone' => '+1 910 555 0292'],
            ['name' => 'Tacoma',             'slug' => 'db-tacoma',            'address' => '747 Market St',            'city' => 'Tacoma',             'tz' => 'America/Los_Angeles', 'phone' => '+1 253 555 0293'],
            ['name' => 'Shreveport',         'slug' => 'db-shreveport',        'address' => '505 Travis St',            'city' => 'Shreveport',         'tz' => 'America/Chicago',     'phone' => '+1 318 555 0294'],
            ['name' => 'Little Rock',        'slug' => 'db-little-rock',       'address' => '500 W Markham St',         'city' => 'Little Rock',        'tz' => 'America/Chicago',     'phone' => '+1 501 555 0295'],
            ['name' => 'Oxnard',             'slug' => 'db-oxnard',            'address' => '305 W Third St',           'city' => 'Oxnard',             'tz' => 'America/Los_Angeles', 'phone' => '+1 805 555 0296'],
            ['name' => 'Glendale CA',        'slug' => 'db-glendale-ca',       'address' => '141 N Glendale Ave',       'city' => 'Glendale',           'tz' => 'America/Los_Angeles', 'phone' => '+1 818 555 0299'],
            ['name' => 'Providence',         'slug' => 'db-providence',        'address' => '444 Westminster St',       'city' => 'Providence',         'tz' => 'America/New_York',    'phone' => '+1 401 555 0300'],
        ];

        foreach ($cities as $c) {
            $rows[] = [
                'brand_id'   => $brandId,
                'name'       => $c['name'],
                'slug'       => $c['slug'],
                'address'    => $c['address'],
                'city'       => $c['city'],
                'country'    => 'US',
                'timezone'   => $c['tz'],
                'phone'      => $c['phone'],
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('venues')->insert($rows);

        // Attach all order types to newly added venues (idempotent)
        $orderTypes   = DB::table('order_types')->pluck('id');
        $coveredPairs = DB::table('venue_order_types')
            ->selectRaw('CONCAT(venue_id, "-", order_type_id) as pair')
            ->pluck('pair')
            ->flip();

        $newVenueIds = DB::table('venues')
            ->where('brand_id', $brandId)
            ->whereNotIn('slug', ['downtown', 'airport-t2'])
            ->pluck('id');

        $otRows = [];
        foreach ($newVenueIds as $vid) {
            foreach ($orderTypes as $otId) {
                if (!$coveredPairs->has("{$vid}-{$otId}")) {
                    $otRows[] = [
                        'venue_id'      => $vid,
                        'order_type_id' => $otId,
                        'active'        => true,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
            }
        }

        if ($otRows) {
            DB::table('venue_order_types')->insert($otRows);
        }
    }
}
