<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VenueController extends Controller
{
    public function index(Brand $brand): JsonResponse
    {
        return response()->json($brand->venues()->with('servingTimes')->get());
    }

    public function show(Brand $brand, Venue $venue): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);

        return response()->json($venue->load(['servingTimes', 'orderTypes']));
    }

    public function store(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'slug'     => 'nullable|string|max:255',
            'address'  => 'nullable|string|max:255',
            'city'     => 'nullable|string|max:100',
            'country'  => 'nullable|string|size:2',
            'timezone' => 'nullable|string|max:100',
            'phone'    => 'nullable|string|max:30',
            'active'   => 'nullable|boolean',
        ]);

        $data['brand_id'] = $brand->id;
        $data['slug']   ??= Str::slug($data['name']);

        $venue = Venue::create($data);

        return response()->json($venue, 201);
    }

    public function update(Request $request, Brand $brand, Venue $venue): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'slug'     => 'sometimes|string|max:255',
            'address'  => 'sometimes|nullable|string|max:255',
            'city'     => 'sometimes|nullable|string|max:100',
            'country'  => 'sometimes|string|size:2',
            'timezone' => 'sometimes|nullable|string|max:100',
            'phone'    => 'sometimes|nullable|string|max:30',
            'active'   => 'sometimes|boolean',
        ]);

        $venue->update($data);

        return response()->json($venue->fresh());
    }

    public function destroy(Brand $brand, Venue $venue): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);

        $venue->delete();

        return response()->json(null, 204);
    }
}
