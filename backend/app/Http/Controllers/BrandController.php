<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Brand::with('servingTimes')->get());
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->load(['servingTimes', 'venues', 'menus']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'slug'     => 'nullable|string|max:255|unique:brands,slug',
            'timezone' => 'nullable|string|max:100',
            'active'   => 'nullable|boolean',
        ]);

        $data['slug'] ??= Str::slug($data['name']);

        $brand = Brand::create($data);

        return response()->json($brand, 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'slug'     => 'sometimes|string|max:255|unique:brands,slug,' . $brand->id,
            'timezone' => 'sometimes|string|max:100',
            'active'   => 'sometimes|boolean',
        ]);

        $brand->update($data);

        return response()->json($brand->fresh());
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(null, 204);
    }
}
