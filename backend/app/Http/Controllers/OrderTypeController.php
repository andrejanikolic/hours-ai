<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrderType;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(OrderType::all());
    }

    public function venueOrderTypes(Venue $venue): JsonResponse
    {
        $orderTypes = $venue->orderTypes()->get()->map(function ($ot) use ($venue) {
            $votId = DB::table('venue_order_types')
                ->where('venue_id', $venue->id)
                ->where('order_type_id', $ot->id)
                ->value('id');

            return [
                'id'              => $ot->id,
                'name'            => $ot->name,
                'slug'            => $ot->slug,
                'active'          => (bool) $ot->pivot->active,
                'venue_order_type_id' => $votId,
                'serving_times'   => \App\Models\ServingTime::where('parent_type', 'order_type')
                    ->where('parent_id', $votId)
                    ->get(),
            ];
        });

        return response()->json($orderTypes);
    }

    public function attachToVenue(Request $request, Venue $venue): JsonResponse
    {
        $data = $request->validate([
            'order_type_id' => 'required|exists:order_types,id',
        ]);

        $already = DB::table('venue_order_types')
            ->where('venue_id', $venue->id)
            ->where('order_type_id', $data['order_type_id'])
            ->exists();

        if ($already) {
            return response()->json(['message' => 'Already attached'], 409);
        }

        $id = DB::table('venue_order_types')->insertGetId([
            'venue_id'      => $venue->id,
            'order_type_id' => $data['order_type_id'],
            'active'        => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['venue_order_type_id' => $id], 201);
    }

    public function detachFromVenue(Venue $venue, int $orderTypeId): JsonResponse
    {
        $deleted = DB::table('venue_order_types')
            ->where('venue_id', $venue->id)
            ->where('order_type_id', $orderTypeId)
            ->delete();

        abort_if($deleted === 0, 404);

        return response()->json(null, 204);
    }
}
