<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Brand $brand): JsonResponse
    {
        return response()->json($brand->menus()->with('servingTimes')->orderBy('position')->get());
    }

    public function show(Brand $brand, Menu $menu): JsonResponse
    {
        abort_if($menu->brand_id !== $brand->id, 404);

        return response()->json($menu->load('servingTimes'));
    }

    public function store(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'internal_name' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'active'        => 'nullable|boolean',
            'position'      => 'nullable|integer|min:0',
        ]);

        $data['brand_id'] = $brand->id;

        $menu = Menu::create($data);

        return response()->json($menu, 201);
    }

    public function update(Request $request, Brand $brand, Menu $menu): JsonResponse
    {
        abort_if($menu->brand_id !== $brand->id, 404);

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

    public function destroy(Brand $brand, Menu $menu): JsonResponse
    {
        abort_if($menu->brand_id !== $brand->id, 404);

        $menu->delete();

        return response()->json(null, 204);
    }
}
