<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ServingTime;
use App\Services\DeepSeekServingTimesParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingTimesControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function weekday(int $parentId, array $days, string $from = '09:00', string $to = '17:00', string $parentType = 'brand'): ServingTime
    {
        return ServingTime::create([
            'parent_type' => $parentType,
            'parent_id'   => $parentId,
            'type'        => 'weekday',
            'days'        => json_encode($days),
            'time_from'   => $from,
            'time_to'     => $to,
            'working'     => true,
        ]);
    }

    private function special(int $parentId, string $date, bool $working = false, string $parentType = 'brand'): ServingTime
    {
        return ServingTime::create([
            'parent_type' => $parentType,
            'parent_id'   => $parentId,
            'type'        => 'special',
            'date'        => $date,
            'working'     => $working,
        ]);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_serving_times_for_parent(): void
    {
        $this->weekday(1, ['monday', 'tuesday']);
        $this->weekday(2, ['wednesday']); // different parent — should not appear

        $this->getJson('/api/serving-times?parent_type=brand&parent_id=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.type', 'weekday');
    }

    public function test_index_requires_parent_type_and_parent_id(): void
    {
        $this->getJson('/api/serving-times')->assertUnprocessable();
        $this->getJson('/api/serving-times?parent_type=brand')->assertUnprocessable();
        $this->getJson('/api/serving-times?parent_id=1')->assertUnprocessable();
    }

    public function test_index_rejects_invalid_parent_type(): void
    {
        $this->getJson('/api/serving-times?parent_type=store&parent_id=1')->assertUnprocessable();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_weekday_serving_time(): void
    {
        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'type'        => 'weekday',
            'days'        => ['monday', 'tuesday', 'wednesday'],
            'time_from'   => '08:00',
            'time_to'     => '22:00',
            'working'     => true,
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'weekday')
            ->assertJsonPath('time_from', '08:00');
    }

    public function test_store_creates_special_date_serving_time(): void
    {
        $this->postJson('/api/serving-times', [
            'parent_type' => 'venue',
            'parent_id'   => 5,
            'type'        => 'special',
            'date'        => '2026-12-25',
            'working'     => false,
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'special')
            ->assertJsonPath('date', '2026-12-25')
            ->assertJsonPath('working', false);
    }

    public function test_store_rejects_overlapping_weekday_same_day(): void
    {
        $this->weekday(1, ['monday', 'tuesday', 'wednesday']);

        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'type'        => 'weekday',
            'days'        => ['wednesday', 'thursday'],
            'time_from'   => '10:00',
            'time_to'     => '20:00',
            'working'     => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn($v) => str_contains($v, 'wednesday'));
    }

    public function test_store_allows_non_overlapping_weekdays(): void
    {
        $this->weekday(1, ['monday', 'tuesday']);

        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'type'        => 'weekday',
            'days'        => ['wednesday', 'thursday'],
            'time_from'   => '10:00',
            'time_to'     => '20:00',
            'working'     => true,
        ])->assertCreated();
    }

    public function test_store_does_not_check_overlap_across_different_parents(): void
    {
        $this->weekday(1, ['monday', 'tuesday']);

        // Same days but different parent_id — should be allowed
        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 2,
            'type'        => 'weekday',
            'days'        => ['monday', 'tuesday'],
            'time_from'   => '09:00',
            'time_to'     => '17:00',
            'working'     => true,
        ])->assertCreated();
    }

    public function test_store_requires_type(): void
    {
        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_invalid_day_name(): void
    {
        $this->postJson('/api/serving-times', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'type'        => 'weekday',
            'days'        => ['funday'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['days.0']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_serving_time(): void
    {
        $st = $this->weekday(1, ['friday']);

        $this->putJson("/api/serving-times/{$st->id}", [
            'time_from' => '11:00',
            'time_to'   => '23:00',
        ])
            ->assertOk()
            ->assertJsonPath('time_from', '11:00')
            ->assertJsonPath('time_to', '23:00');
    }

    public function test_update_can_set_working_false(): void
    {
        $st = $this->weekday(1, ['saturday']);

        $this->putJson("/api/serving-times/{$st->id}", ['working' => false])
            ->assertOk()
            ->assertJsonPath('working', false);
    }

    public function test_update_returns_404_for_missing_serving_time(): void
    {
        $this->putJson('/api/serving-times/9999', ['working' => false])->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_serving_time(): void
    {
        $st = $this->weekday(1, ['monday']);

        $this->deleteJson("/api/serving-times/{$st->id}")->assertNoContent();
        $this->assertDatabaseMissing('serving_times', ['id' => $st->id]);
    }

    public function test_destroy_returns_404_for_missing_serving_time(): void
    {
        $this->deleteJson('/api/serving-times/9999')->assertNotFound();
    }

    // ── replace ───────────────────────────────────────────────────────────────

    public function test_replace_removes_existing_and_inserts_new(): void
    {
        $this->weekday(1, ['monday']);
        $this->weekday(1, ['tuesday']);

        $this->putJson('/api/serving-times/replace', [
            'parent_type'   => 'brand',
            'parent_id'     => 1,
            'serving_times' => [
                ['type' => 'weekday', 'days' => ['wednesday'], 'time_from' => '10:00', 'time_to' => '20:00', 'working' => true],
            ],
        ])->assertOk()->assertJsonCount(1);

        $this->assertDatabaseCount('serving_times', 1);
        $this->assertDatabaseHas('serving_times', ['parent_id' => 1, 'parent_type' => 'brand']);
    }

    public function test_replace_is_atomic_and_only_affects_given_parent(): void
    {
        $this->weekday(1, ['monday']);
        $this->weekday(2, ['friday']); // different parent — must survive

        $this->putJson('/api/serving-times/replace', [
            'parent_type'   => 'brand',
            'parent_id'     => 1,
            'serving_times' => [
                ['type' => 'weekday', 'days' => ['sunday'], 'time_from' => '12:00', 'time_to' => '18:00', 'working' => true],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('serving_times', 2);
        $this->assertDatabaseHas('serving_times', ['parent_id' => 2]);
    }

    public function test_replace_rejects_overlapping_days_within_batch(): void
    {
        $this->putJson('/api/serving-times/replace', [
            'parent_type'   => 'brand',
            'parent_id'     => 1,
            'serving_times' => [
                ['type' => 'weekday', 'days' => ['monday', 'tuesday'], 'time_from' => '09:00', 'time_to' => '17:00', 'working' => true],
                ['type' => 'weekday', 'days' => ['tuesday', 'wednesday'], 'time_from' => '12:00', 'time_to' => '22:00', 'working' => true],
            ],
        ])->assertUnprocessable()->assertJsonPath('message', fn($v) => str_contains($v, 'tuesday'));
    }

    public function test_replace_accepts_empty_serving_times(): void
    {
        $this->weekday(1, ['monday']);

        $this->putJson('/api/serving-times/replace', [
            'parent_type'   => 'brand',
            'parent_id'     => 1,
            'serving_times' => [],
        ])->assertOk()->assertExactJson([]);

        $this->assertDatabaseCount('serving_times', 0);
    }

    // ── parse ─────────────────────────────────────────────────────────────────

    public function test_parse_calls_parser_and_returns_preview(): void
    {
        $parsedTimes = [
            ['type' => 'weekday', 'days' => ['monday'], 'time_from' => '09:00', 'time_to' => '21:00', 'working' => true],
        ];

        $this->mock(DeepSeekServingTimesParser::class, function ($mock) use ($parsedTimes) {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturn([
                    'serving_times'         => $parsedTimes,
                    'should_update'         => true,
                    'clarification_needed'  => false,
                    'clarification_message' => null,
                ]);
        });

        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'prompt'      => 'Open Monday 9am to 9pm',
        ])
            ->assertOk()
            ->assertJsonPath('clarification_needed', false)
            ->assertJsonPath('should_update', true)
            ->assertJsonPath('preview.0.type', 'weekday');
    }

    public function test_parse_forwards_entity_name_to_parser(): void
    {
        $this->mock(DeepSeekServingTimesParser::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->withArgs(fn($prompt, $current, $entityName) => $entityName === 'Lunch')
                ->andReturn([
                    'serving_times'         => [],
                    'should_update'         => true,
                    'clarification_needed'  => false,
                    'clarification_message' => null,
                ]);
        });

        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'menu',
            'parent_id'   => 7,
            'prompt'      => 'Lunch menu open 11am to 3pm',
            'entity_name' => 'Lunch',
        ])->assertOk();
    }

    public function test_parse_returns_clarification_needed_when_parser_signals_it(): void
    {
        $this->mock(DeepSeekServingTimesParser::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturn([
                    'serving_times'         => [],
                    'should_update'         => true,
                    'clarification_needed'  => true,
                    'clarification_message' => 'Which timezone?',
                ]);
        });

        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'venue',
            'parent_id'   => 1,
            'prompt'      => 'Open at 9',
        ])
            ->assertOk()
            ->assertJsonPath('clarification_needed', true)
            ->assertJsonPath('clarification_message', 'Which timezone?');
    }

    public function test_parse_returns_should_update_false_when_parser_signals_it(): void
    {
        $this->mock(DeepSeekServingTimesParser::class, function ($mock) {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturn([
                    'serving_times'         => [],
                    'should_update'         => false,
                    'clarification_needed'  => false,
                    'clarification_message' => null,
                ]);
        });

        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'menu',
            'parent_id'   => 3,
            'prompt'      => 'Lunch menu open 11am to 3pm',
            'entity_name' => 'Breakfast',
        ])
            ->assertOk()
            ->assertJsonPath('should_update', false);
    }

    public function test_parse_passes_existing_serving_times_as_context(): void
    {
        $this->weekday(99, ['monday', 'tuesday'], '08:00', '18:00', 'venue');

        $capturedCurrent = null;

        $this->mock(DeepSeekServingTimesParser::class, function ($mock) use (&$capturedCurrent) {
            $mock->shouldReceive('parse')
                ->once()
                ->withArgs(function ($prompt, $current, $entityName) use (&$capturedCurrent) {
                    $capturedCurrent = $current;
                    return true;
                })
                ->andReturn([
                    'serving_times'         => [],
                    'should_update'         => true,
                    'clarification_needed'  => false,
                    'clarification_message' => null,
                ]);
        });

        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'venue',
            'parent_id'   => 99,
            'prompt'      => 'Change to 10am',
        ])->assertOk();

        $this->assertCount(1, $capturedCurrent);
        $this->assertSame('weekday', $capturedCurrent[0]['type']);
    }

    public function test_parse_requires_prompt(): void
    {
        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['prompt']);
    }

    public function test_parse_rejects_prompt_exceeding_5000_chars(): void
    {
        $this->postJson('/api/serving-times/parse', [
            'parent_type' => 'brand',
            'parent_id'   => 1,
            'prompt'      => str_repeat('x', 5001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['prompt']);
    }
}
