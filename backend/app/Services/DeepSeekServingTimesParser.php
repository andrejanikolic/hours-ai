<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekServingTimesParser
{
    private const VALID_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const SCHEMA = <<<'JSON'
    {
      "should_update": true,
      "clarification_needed": false,
      "clarification_message": null,
      "serving_times": [
        {
          "type": "weekday",
          "days": ["monday", "tuesday"],
          "time_from": "09:00",
          "time_to": "22:00",
          "working": true
        },
        {
          "type": "special",
          "date": "2026-12-25",
          "date_to": null,
          "time_from": null,
          "time_to": null,
          "working": false
        }
      ]
    }
    JSON;

    private const MAX_RETRIES = 4;

    public function parse(string $prompt, array $currentServingTimes, string $entityName = ''): array
    {
        $currentText = $this->formatCurrentTimes($currentServingTimes);
        $entityHint  = $entityName !== '' ? "You are configuring hours for: {$entityName}.\n\n" : '';

        $systemPrompt = <<<PROMPT
        You are a serving-times configuration assistant for a restaurant management system.
        {$entityHint}
        CURRENT SERVING TIMES:
        {$currentText}

        TASK: Parse the user's plain-English instruction into a JSON object.
        Rules:
        - Keep unchanged serving times from the CURRENT SERVING TIMES unless the instruction changes them.
        - Use "weekday" type for recurring days-of-week schedules; use "special" type for specific date overrides.
        - Times must be in HH:MM 24-hour format (convert 12-hour am/pm to 24-hour) or null.
        - For weekday entries, "days" must be an array of day names (lowercase).
        - For special entries, "date" is required (YYYY-MM-DD); "date_to" is optional for date ranges.
        - Set "working": false for closed periods.
        - For relative time expressions (e.g. "stop taking orders 30 minutes before closing", "last order 15 min before close"), look up the closing time (time_to) for the relevant day(s) in CURRENT SERVING TIMES, subtract the offset, and return the computed absolute time as time_to in HH:MM format. If different days have different closing times, apply the offset per day independently.
        - If the instruction is ambiguous or cannot be parsed, set clarification_needed to true and explain in clarification_message.
        - If the instruction clearly refers to a different entity (e.g. "Lunch menu" when you are configuring "Breakfast"), set should_update to false and serving_times to [].
        - Return ONLY a valid JSON object matching this schema — no markdown, no explanation:

        PROMPT . self::SCHEMA;

        $response = $this->callWithRetry($systemPrompt, $prompt);

        $content = $response->json('choices.0.message.content');
        $content = $this->stripMarkdown($content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('DeepSeek returned invalid JSON', ['content' => $content]);
            throw new \RuntimeException('DeepSeek returned invalid JSON');
        }

        // Handle both response shapes:
        // - object: {clarification_needed, clarification_message, serving_times: [...]}
        // - plain array: [...] (DeepSeek sometimes ignores the wrapper schema)
        if (array_is_list($parsed)) {
            return [
                'serving_times'         => $this->sanitizeRows($parsed),
                'clarification_needed'  => false,
                'clarification_message' => null,
            ];
        }

        $shouldUpdate        = (bool) ($parsed['should_update'] ?? true);
        $clarificationNeeded = (bool) ($parsed['clarification_needed'] ?? false);

        return [
            'should_update'         => $shouldUpdate,
            'serving_times'         => $shouldUpdate ? $this->sanitizeRows($parsed['serving_times'] ?? []) : [],
            'clarification_needed'  => $clarificationNeeded,
            'clarification_message' => $parsed['clarification_message'] ?? null,
        ];
    }

    private function callWithRetry(string $systemPrompt, string $userPrompt): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $delay   = 2;

        while (true) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post(config('services.deepseek.api_url') . '/chat/completions', [
                'model'       => 'deepseek-chat',
                'temperature' => 0,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

            if ($response->status() === 429 && $attempt < self::MAX_RETRIES) {
                Log::warning('DeepSeek 429, retrying', ['attempt' => $attempt + 1, 'delay' => $delay]);
                sleep($delay);
                $delay  *= 2;
                $attempt++;
                continue;
            }

            if ($response->failed()) {
                Log::error('DeepSeek API error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException('DeepSeek API request failed: ' . $response->status());
            }

            return $response;
        }
    }

    private function formatCurrentTimes(array $times): string
    {
        if (empty($times)) {
            return 'None configured.';
        }

        return collect($times)->map(function ($t) {
            if ($t['type'] === 'weekday') {
                $days = implode(', ', $t['days'] ?? []);
                $hours = $t['working']
                    ? ($t['time_from'] . '-' . $t['time_to'])
                    : 'closed';
                return "Weekday [{$days}]: {$hours}";
            }

            $range = $t['date_to'] ? "{$t['date']} to {$t['date_to']}" : $t['date'];
            $hours = $t['working']
                ? ($t['time_from'] . '-' . $t['time_to'])
                : 'closed';
            return "Special [{$range}]: {$hours}";
        })->implode("\n");
    }

    private function stripMarkdown(string $content): string
    {
        $content = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $content);

        return trim($content);
    }

    private function sanitizeRows(array $rows): array
    {
        $allowed = ['type', 'days', 'date', 'date_to', 'time_from', 'time_to', 'working'];

        return array_map(function ($row) use ($allowed) {
            $clean = array_intersect_key($row, array_flip($allowed));

            $clean['working'] = (bool) ($clean['working'] ?? true);

            if (($clean['type'] ?? '') === 'weekday' && isset($clean['days'])) {
                $clean['days'] = array_values(
                    array_filter($clean['days'], fn($d) => in_array($d, self::VALID_DAYS))
                );
            }

            return $clean;
        }, $rows);
    }
}
