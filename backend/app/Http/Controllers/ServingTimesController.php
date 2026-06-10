<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServingTime;
use App\Services\DeepSeekServingTimesParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServingTimesController extends Controller
{
    public function __construct(private DeepSeekServingTimesParser $parser) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_type' => 'required|in:brand,venue,menu,order_type',
            'parent_id'   => 'required|integer',
        ]);

        $times = ServingTime::where('parent_type', $data['parent_type'])
            ->where('parent_id', $data['parent_id'])
            ->get();

        return response()->json($times);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_type' => 'required|in:brand,venue,menu,order_type',
            'parent_id'   => 'required|integer',
            'type'        => 'required|in:weekday,special',
            'days'        => 'nullable|array',
            'days.*'      => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'date'        => 'nullable|date_format:Y-m-d',
            'date_to'     => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'time_from'   => 'nullable|date_format:H:i',
            'time_to'     => 'nullable|date_format:H:i',
            'working'     => 'boolean',
        ]);

        $st = ServingTime::create($data);

        return response()->json($st, 201);
    }

    public function update(Request $request, ServingTime $servingTime): JsonResponse
    {
        $data = $request->validate([
            'type'      => 'sometimes|in:weekday,special',
            'days'      => 'sometimes|nullable|array',
            'days.*'    => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'date'      => 'sometimes|nullable|date_format:Y-m-d',
            'date_to'   => 'sometimes|nullable|date_format:Y-m-d',
            'time_from' => 'sometimes|nullable|date_format:H:i',
            'time_to'   => 'sometimes|nullable|date_format:H:i',
            'working'   => 'sometimes|boolean',
        ]);

        $servingTime->update($data);

        return response()->json($servingTime->fresh());
    }

    public function destroy(ServingTime $servingTime): JsonResponse
    {
        $servingTime->delete();

        return response()->json(null, 204);
    }

    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_type' => 'required|in:brand,venue,menu,order_type',
            'parent_id'   => 'required|integer',
            'prompt'      => 'required|string|max:1000',
        ]);

        $current = ServingTime::where('parent_type', $data['parent_type'])
            ->where('parent_id', $data['parent_id'])
            ->get()
            ->toArray();

        $preview = $this->parser->parse($data['prompt'], $current);

        return response()->json([
            'preview'              => $preview,
            'clarification_needed' => $preview['clarification_needed'] ?? false,
        ]);
    }

    public function replace(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_type'   => 'required|in:brand,venue,menu,order_type',
            'parent_id'     => 'required|integer',
            'serving_times' => 'required|array',
            'serving_times.*.type'      => 'required|in:weekday,special',
            'serving_times.*.days'      => 'nullable|array',
            'serving_times.*.days.*'    => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'serving_times.*.date'      => 'nullable|date_format:Y-m-d',
            'serving_times.*.date_to'   => 'nullable|date_format:Y-m-d',
            'serving_times.*.time_from' => 'nullable|date_format:H:i',
            'serving_times.*.time_to'   => 'nullable|date_format:H:i',
            'serving_times.*.working'   => 'boolean',
        ]);

        $rows = array_map(fn($st) => array_merge($st, [
            'parent_type' => $data['parent_type'],
            'parent_id'   => $data['parent_id'],
            'days'        => isset($st['days']) ? json_encode($st['days']) : null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]), $data['serving_times']);

        DB::transaction(function () use ($data, $rows) {
            ServingTime::where('parent_type', $data['parent_type'])
                ->where('parent_id', $data['parent_id'])
                ->delete();

            DB::table('serving_times')->insert($rows);
        });

        $result = ServingTime::where('parent_type', $data['parent_type'])
            ->where('parent_id', $data['parent_id'])
            ->get();

        return response()->json($result);
    }
}
