<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DeepSeekHoursParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreHoursController extends Controller
{
    public function __construct(private readonly DeepSeekHoursParser $parser) {}

    public function parse(Request $request, int $storeId): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:1000']);

        $store = DB::table('stores')->find($storeId);
        abort_unless($store, 404, 'Store not found');

        $currentHours = DB::table('store_hours')
            ->where('store_id', $storeId)
            ->orderByRaw("FIELD(day, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday')")
            ->get(['day', 'open', 'close', 'closed'])
            ->toArray();

        try {
            $parsed = $this->parser->parse($request->string('text'), (array) $currentHours);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($parsed);
    }

    public function update(Request $request, int $storeId): JsonResponse
    {
        $data = $request->validate([
            'days'                   => 'required|array|size:7',
            'days.*.day'             => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'days.*.open'            => 'nullable|date_format:H:i',
            'days.*.close'           => 'nullable|date_format:H:i',
            'days.*.closed'          => 'required|boolean',
            'specialClosures'        => 'array',
            'specialClosures.*.date' => 'required|date_format:Y-m-d',
            'specialClosures.*.reason' => 'nullable|string',
            'orderCutoffMinutes'     => 'nullable|integer|min:0|max:120',
            'deliveryWindow'         => 'nullable|array',
            'deliveryWindow.open'    => 'required_with:deliveryWindow|date_format:H:i',
            'deliveryWindow.close'   => 'required_with:deliveryWindow|date_format:H:i',
            'pickupWindow'           => 'nullable|array',
            'pickupWindow.open'      => 'required_with:pickupWindow|date_format:H:i',
            'pickupWindow.close'     => 'required_with:pickupWindow|date_format:H:i',
        ]);

        $store = DB::table('stores')->find($storeId);
        abort_unless($store, 404, 'Store not found');

        DB::transaction(function () use ($storeId, $data) {
            foreach ($data['days'] as $day) {
                DB::table('store_hours')->updateOrInsert(
                    ['store_id' => $storeId, 'day' => $day['day']],
                    [
                        'open'       => $day['closed'] ? null : $day['open'],
                        'close'      => $day['closed'] ? null : $day['close'],
                        'closed'     => $day['closed'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::table('store_special_closures')->where('store_id', $storeId)->delete();
            foreach ($data['specialClosures'] ?? [] as $closure) {
                DB::table('store_special_closures')->insert([
                    'store_id'   => $storeId,
                    'date'       => $closure['date'],
                    'reason'     => $closure['reason'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('store_settings')->updateOrInsert(
                ['store_id' => $storeId],
                [
                    'order_cutoff_minutes' => $data['orderCutoffMinutes'] ?? null,
                    'delivery_open'        => $data['deliveryWindow']['open'] ?? null,
                    'delivery_close'       => $data['deliveryWindow']['close'] ?? null,
                    'pickup_open'          => $data['pickupWindow']['open'] ?? null,
                    'pickup_close'         => $data['pickupWindow']['close'] ?? null,
                    'updated_at'           => now(),
                    'created_at'           => now(),
                ]
            );
        });

        return response()->json(['message' => 'Store hours updated successfully']);
    }

    public function show(int $storeId): JsonResponse
    {
        $store = DB::table('stores')->find($storeId);
        abort_unless($store, 404, 'Store not found');

        $hours = DB::table('store_hours')
            ->where('store_id', $storeId)
            ->orderByRaw("FIELD(day, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday')")
            ->get();

        $settings = DB::table('store_settings')->where('store_id', $storeId)->first();
        $closures = DB::table('store_special_closures')->where('store_id', $storeId)->get();

        return response()->json([
            'store'           => $store,
            'days'            => $hours,
            'specialClosures' => $closures,
            'settings'        => $settings,
        ]);
    }
}
