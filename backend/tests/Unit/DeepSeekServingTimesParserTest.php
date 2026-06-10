<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DeepSeekServingTimesParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekServingTimesParserTest extends TestCase
{
    private DeepSeekServingTimesParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DeepSeekServingTimesParser();

        config([
            'services.deepseek.api_key' => 'test-key',
            'services.deepseek.api_url' => 'https://api.deepseek.com',
        ]);
    }

    // ── object wrapper response ───────────────────────────────────────────────

    public function test_parse_handles_object_wrapper_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [
                            ['type' => 'weekday', 'days' => ['monday'], 'time_from' => '09:00', 'time_to' => '21:00', 'working' => true],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('open monday 9am to 9pm', []);

        $this->assertFalse($result['clarification_needed']);
        $this->assertTrue($result['should_update']);
        $this->assertCount(1, $result['serving_times']);
        $this->assertSame('weekday', $result['serving_times'][0]['type']);
    }

    // ── plain array fallback ──────────────────────────────────────────────────

    public function test_parse_handles_plain_array_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        ['type' => 'weekday', 'days' => ['tuesday'], 'time_from' => '10:00', 'time_to' => '22:00', 'working' => true],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('open tuesday 10am to 10pm', []);

        $this->assertFalse($result['clarification_needed']);
        $this->assertCount(1, $result['serving_times']);
    }

    // ── should_update: false ──────────────────────────────────────────────────

    public function test_parse_returns_should_update_false_and_empty_times(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => false,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('Lunch menu 11am to 3pm', [], 'Breakfast');

        $this->assertFalse($result['should_update']);
        $this->assertEmpty($result['serving_times']);
    }

    // ── clarification_needed ──────────────────────────────────────────────────

    public function test_parse_returns_clarification_needed_with_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => true,
                        'clarification_message' => 'Which timezone should I use?',
                        'serving_times'         => [],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('open at 9', []);

        $this->assertTrue($result['clarification_needed']);
        $this->assertSame('Which timezone should I use?', $result['clarification_message']);
    }

    // ── markdown stripping ────────────────────────────────────────────────────

    public function test_parse_strips_markdown_code_fences(): void
    {
        $json = json_encode([
            'should_update'         => true,
            'clarification_needed'  => false,
            'clarification_message' => null,
            'serving_times'         => [
                ['type' => 'weekday', 'days' => ['friday'], 'time_from' => '18:00', 'time_to' => '23:00', 'working' => true],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => "```json\n{$json}\n```"],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('open friday evening', []);

        $this->assertCount(1, $result['serving_times']);
    }

    // ── sanitize rows ─────────────────────────────────────────────────────────

    public function test_parse_removes_invalid_day_names(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [
                            ['type' => 'weekday', 'days' => ['monday', 'funday', 'tuesday'], 'time_from' => '09:00', 'time_to' => '17:00', 'working' => true],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('open weekdays', []);

        $this->assertEqualsCanonicalizing(['monday', 'tuesday'], $result['serving_times'][0]['days']);
    }

    public function test_parse_casts_working_to_boolean(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [
                            ['type' => 'weekday', 'days' => ['sunday'], 'time_from' => null, 'time_to' => null, 'working' => 0],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $result = $this->parser->parse('closed sundays', []);

        $this->assertFalse($result['serving_times'][0]['working']);
    }

    // ── entity_name injection ─────────────────────────────────────────────────

    public function test_parse_injects_entity_name_into_request(): void
    {
        $capturedBody = null;

        Http::fake(function (Request $request) use (&$capturedBody) {
            $capturedBody = $request->body();
            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [],
                    ])],
                ]],
            ], 200);
        });

        $this->parser->parse('open weekdays', [], 'Starbird Cupertino');

        $this->assertStringContainsString('Starbird Cupertino', $capturedBody);
    }

    // ── retry on 429 ─────────────────────────────────────────────────────────

    public function test_parse_retries_on_429_and_succeeds(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response(['error' => 'rate limit'], 429);
            }
            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'should_update'         => true,
                        'clarification_needed'  => false,
                        'clarification_message' => null,
                        'serving_times'         => [
                            ['type' => 'weekday', 'days' => ['wednesday'], 'time_from' => '09:00', 'time_to' => '17:00', 'working' => true],
                        ],
                    ])],
                ]],
            ], 200);
        });

        // Override sleep to not actually wait in tests
        $result = $this->parser->parse('open wednesday', []);

        $this->assertSame(3, $callCount);
        $this->assertCount(1, $result['serving_times']);
    }

    public function test_parse_throws_after_max_retries_on_429(): void
    {
        Http::fake(fn() => Http::response(['error' => 'rate limit'], 429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/429/');

        $this->parser->parse('open all week', []);
    }

    public function test_parse_throws_on_non_429_api_error(): void
    {
        Http::fake(fn() => Http::response(['error' => 'unauthorized'], 401));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $this->parser->parse('open monday', []);
    }

    public function test_parse_throws_on_invalid_json_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'not valid json at all {{{'],
                ]],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        $this->parser->parse('open monday', []);
    }
}
