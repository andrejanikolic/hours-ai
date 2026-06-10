<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekHoursParser
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const SCHEMA = <<<'JSON'
    {
      "days": [
        { "day": "monday|tuesday|wednesday|thursday|friday|saturday|sunday", "open": "HH:MM|null", "close": "HH:MM|null", "closed": true|false }
      ],
      "specialClosures": [
        { "date": "YYYY-MM-DD", "reason": "string" }
      ],
      "orderCutoffMinutes": integer|null,
      "deliveryWindow": { "open": "HH:MM", "close": "HH:MM" }|null,
      "pickupWindow": { "open": "HH:MM", "close": "HH:MM" }|null,
      "clarification_needed": true|false
    }
    JSON;

    public function parse(string $text, array $currentHours): array
    {
        $systemPrompt = $this->buildSystemPrompt($currentHours);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
            'Content-Type'  => 'application/json',
        ])->post(config('services.deepseek.api_url') . '/chat/completions', [
            'model'       => 'deepseek-v4-pro',
            'temperature' => 0,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text],
            ],
        ]);

        if ($response->failed()) {
            Log::error('DeepSeek API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('DeepSeek API request failed');
        }

        $content = $response->json('choices.0.message.content');
        $parsed  = json_decode($this->extractJson($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('DeepSeek returned invalid JSON', ['content' => $content]);
            throw new \RuntimeException('DeepSeek returned invalid JSON');
        }

        return $this->validate($parsed);
    }

    private function buildSystemPrompt(array $currentHours): string
    {
        $currentHoursText = collect($currentHours)
            ->map(fn($h) => "{$h['day']}: " . ($h['closed'] ? 'closed' : "{$h['open']}-{$h['close']}"))
            ->implode(', ');

        return <<<PROMPT
        You are a store hours configuration parser for a restaurant ordering platform.

        CURRENT STORE HOURS: {$currentHoursText}

        TASK: Parse the user's plain-English instruction into a structured JSON configuration.
        - Keep unchanged days exactly as they appear in CURRENT STORE HOURS.
        - Times must be in HH:MM 24-hour format.
        - All 7 days must always be present in the "days" array.
        - If the instruction is ambiguous or missing critical information, set clarification_needed to true.
        - Return ONLY valid JSON matching this schema — no markdown, no explanation, no code blocks:

        {self::SCHEMA}
        PROMPT;
    }

    private function extractJson(string $content): string
    {
        // Strip markdown code blocks if DeepSeek wraps the response
        $content = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $content);

        return trim($content);
    }

    private function validate(array $parsed): array
    {
        $allowedKeys = ['days', 'specialClosures', 'orderCutoffMinutes', 'deliveryWindow', 'pickupWindow', 'clarification_needed'];

        foreach (array_keys($parsed) as $key) {
            if (!in_array($key, $allowedKeys)) {
                unset($parsed[$key]);
            }
        }

        // Ensure all 7 days are present
        $presentDays = array_column($parsed['days'] ?? [], 'day');
        foreach (self::DAYS as $day) {
            if (!in_array($day, $presentDays)) {
                $parsed['days'][] = ['day' => $day, 'open' => null, 'close' => null, 'closed' => true];
            }
        }

        $parsed['clarification_needed'] = $parsed['clarification_needed'] ?? false;
        $parsed['specialClosures']      = $parsed['specialClosures'] ?? [];

        return $parsed;
    }
}
