<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\OrderType;
use App\Models\ServingTime;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->venue = Venue::create(['brand_id' => $this->brand->id, 'name' => 'HQ', 'slug' => 'hq']);
    }

    private function createOrderType(string $name, string $slug): OrderType
    {
        return OrderType::create(['name' => $name, 'slug' => $slug]);
    }

    private function attachToVenue(Venue $venue, OrderType $orderType): int
    {
        return DB::table('venue_order_types')->insertGetId([
            'venue_id'      => $venue->id,
            'order_type_id' => $orderType->id,
            'active'        => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_all_order_types_with_serving_times(): void
    {
        $ot = $this->createOrderType('Delivery', 'delivery');
        ServingTime::create([
            'parent_type' => 'order_type',
            'parent_id'   => $ot->id,
            'type'        => 'weekday',
            'days'        => json_encode(['monday']),
            'time_from'   => '10:00',
            'time_to'     => '22:00',
            'working'     => true,
        ]);

        $response = $this->getJson('/api/order-types')->assertOk();

        // Find the Delivery entry in the response (other order types may exist from migrations)
        $deliveryEntry = collect($response->json())->firstWhere('name', 'Delivery');
        $this->assertNotNull($deliveryEntry);
        $this->assertSame('weekday', $deliveryEntry['serving_times'][0]['type'] ?? null);
    }

    public function test_index_returns_empty_serving_times_when_none_configured(): void
    {
        $this->createOrderType('Pickup', 'pickup');

        $this->getJson('/api/order-types')
            ->assertOk()
            ->assertJsonPath('0.serving_times', []);
    }

    // ── venueOrderTypes ───────────────────────────────────────────────────────

    public function test_venue_order_types_returns_order_types_with_pivot_info(): void
    {
        $ot    = $this->createOrderType('Delivery', 'delivery');
        $votId = $this->attachToVenue($this->venue, $ot);

        $this->getJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Delivery')
            ->assertJsonPath('0.venue_order_type_id', $votId);
    }

    public function test_venue_order_types_returns_404_for_wrong_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);

        $this->getJson("/api/brands/{$other->id}/venues/{$this->venue->id}/order-types")
            ->assertNotFound();
    }

    public function test_venue_order_types_includes_serving_times_per_pivot(): void
    {
        $ot    = $this->createOrderType('Delivery', 'delivery');
        $votId = $this->attachToVenue($this->venue, $ot);

        ServingTime::create([
            'parent_type' => 'order_type',
            'parent_id'   => $votId,
            'type'        => 'weekday',
            'days'        => json_encode(['tuesday']),
            'time_from'   => '11:00',
            'time_to'     => '21:00',
            'working'     => true,
        ]);

        $this->getJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types")
            ->assertOk()
            ->assertJsonPath('0.serving_times.0.type', 'weekday');
    }

    // ── attachToVenue ─────────────────────────────────────────────────────────

    public function test_attach_adds_order_type_to_venue(): void
    {
        $ot = $this->createOrderType('Dine In', 'dine-in');

        $this->postJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types", [
            'order_type_id' => $ot->id,
        ])->assertCreated();

        $this->assertDatabaseHas('venue_order_types', [
            'venue_id'      => $this->venue->id,
            'order_type_id' => $ot->id,
        ]);
    }

    public function test_attach_returns_409_when_already_attached(): void
    {
        $ot = $this->createOrderType('Pickup', 'pickup');
        $this->attachToVenue($this->venue, $ot);

        $this->postJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types", [
            'order_type_id' => $ot->id,
        ])->assertStatus(409);
    }

    public function test_attach_returns_422_for_nonexistent_order_type(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types", [
            'order_type_id' => 9999,
        ])->assertUnprocessable();
    }

    // ── detachFromVenue ───────────────────────────────────────────────────────

    public function test_detach_removes_order_type_from_venue(): void
    {
        $ot = $this->createOrderType('Delivery', 'delivery');
        $this->attachToVenue($this->venue, $ot);

        $this->deleteJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types/{$ot->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('venue_order_types', [
            'venue_id'      => $this->venue->id,
            'order_type_id' => $ot->id,
        ]);
    }

    public function test_detach_returns_404_when_not_attached(): void
    {
        $ot = $this->createOrderType('Drive Thru', 'drive-thru');

        $this->deleteJson("/api/brands/{$this->brand->id}/venues/{$this->venue->id}/order-types/{$ot->id}")
            ->assertNotFound();
    }
}
