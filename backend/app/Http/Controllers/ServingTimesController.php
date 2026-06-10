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

        if (($data['type'] ?? '') === 'weekday') {
            $existing = ServingTime::where('parent_type', $data['parent_type'])
                ->where('parent_id', $data['parent_id'])
                ->where('type', 'weekday')
                ->get()
                ->map(fn($st) => $st->toArray())
                ->toArray();

            $conflict = $this->findWeekdayOverlap([$data], $existing);
            if ($conflict) {
                return response()->json(['message' => $conflict], 422);
            }
        }

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
            'prompt'      => 'required|string|max:5000',
            'entity_name' => 'nullable|string|max:255',
        ]);

        $current = ServingTime::where('parent_type', $data['parent_type'])
            ->where('parent_id', $data['parent_id'])
            ->get()
            ->map(fn($st) => $st->toArray())
            ->toArray();

        $preview = $this->parser->parse(
            $data['prompt'],
            $current,
            $data['entity_name'] ?? ''
        );

        return response()->json([
            'preview'               => $preview['serving_times'],
            'clarification_needed'  => $preview['clarification_needed'],
            'clarification_message' => $preview['clarification_message'] ?? null,
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

        $weekdays = array_values(array_filter($data['serving_times'], fn($st) => ($st['type'] ?? '') === 'weekday'));
        $conflict = $this->findWeekdayOverlap($weekdays, []);
        if ($conflict) {
            return response()->json(['message' => $conflict], 422);
        }

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

    /**
     * Check for overlapping weekday entries.
     * $newEntries are checked against each other AND against $existing.
     * Returns an error message string, or null when no overlap found.
     */
    private function findWeekdayOverlap(array $newEntries, array $existing): ?string
    {
        $all = array_merge($newEntries, $existing);

        for ($i = 0; $i < count($all); $i++) {
            for ($j = $i + 1; $j < count($all); $j++) {
                $a = $all[$i];
                $b = $all[$j];

                $aDays = is_array($a['days'] ?? null) ? $a['days'] : (is_string($a['days'] ?? null) ? json_decode($a['days'], true) : []);
                $bDays = is_array($b['days'] ?? null) ? $b['days'] : (is_string($b['days'] ?? null) ? json_decode($b['days'], true) : []);

                $sharedDays = array_intersect($aDays ?? [], $bDays ?? []);

                if (empty($sharedDays)) {
                    continue;
                }

                $shared = implode(', ', array_values($sharedDays));
                return "Overlapping weekday entries for: {$shared}. Each day can only appear in one weekday schedule.";
            }
        }

        return null;
    }
}
