<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Menu;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Brand $brand, Venue $venue): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);

        return response()->json($venue->menus()->with('servingTimes')->orderBy('position')->get());
    }

    public function show(Brand $brand, Venue $venue, Menu $menu): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);
        abort_if($menu->venue_id !== $venue->id, 404);

        return response()->json($menu->load('servingTimes'));
    }

    public function store(Request $request, Brand $brand, Venue $venue): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'internal_name' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'active'        => 'nullable|boolean',
            'position'      => 'nullable|integer|min:0',
        ]);

        $data['venue_id'] = $venue->id;

        $menu = Menu::create($data);

        return response()->json($menu, 201);
    }

    public function update(Request $request, Brand $brand, Venue $venue, Menu $menu): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);
        abort_if($menu->venue_id !== $venue->id, 404);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'internal_name' => 'sometimes|nullable|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'active'        => 'sometimes|boolean',
            'position'      => 'sometimes|integer|min:0',
        ]);

        $menu->update($data);

        return response()->json($menu->fresh());
    }

    public function destroy(Brand $brand, Venue $venue, Menu $menu): JsonResponse
    {
        abort_if($venue->brand_id !== $brand->id, 404);
        abort_if($menu->venue_id !== $venue->id, 404);

        $menu->delete();

        return response()->json(null, 204);
    }
}
