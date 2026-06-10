<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueControllerTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_venues_for_brand(): void
    {
        Venue::create(['brand_id' => $this->brand->id, 'name' => 'Downtown', 'slug' => 'downtown']);

        $this->getJson("/api/brands/{$this->brand->id}/venues")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Downtown');
    }

    public function test_index_does_not_return_venues_from_other_brands(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        Venue::create(['brand_id' => $other->id, 'name' => 'Secret', 'slug' => 'secret']);

        $this->getJson("/api/brands/{$this->brand->id}/venues")
            ->assertOk()
            ->assertJsonCount(0);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_venue(): void
    {
        $venue = Venue::create(['brand_id' => $this->brand->id, 'name' => 'HQ', 'slug' => 'hq']);

        $this->getJson("/api/brands/{$this->brand->id}/venues/{$venue->id}")
            ->assertOk()
            ->assertJsonPath('name', 'HQ');
    }

    public function test_show_returns_404_when_venue_belongs_to_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $venue = Venue::create(['brand_id' => $other->id, 'name' => 'Intruder', 'slug' => 'intruder']);

        $this->getJson("/api/brands/{$this->brand->id}/venues/{$venue->id}")->assertNotFound();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_venue_under_brand(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/venues", [
            'name'     => 'New Location',
            'city'     => 'San Francisco',
            'timezone' => 'America/Los_Angeles',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'New Location')
            ->assertJsonPath('slug', 'new-location')
            ->assertJsonPath('brand_id', $this->brand->id);
    }

    public function test_store_requires_name(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/venues", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_auto_generates_slug(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/venues", ['name' => 'Times Square'])
            ->assertCreated()
            ->assertJsonPath('slug', 'times-square');
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_venue(): void
    {
        $venue = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Old', 'slug' => 'old']);

        $this->putJson("/api/brands/{$this->brand->id}/venues/{$venue->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated');
    }

    public function test_update_returns_404_for_venue_from_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $venue = Venue::create(['brand_id' => $other->id, 'name' => 'Intruder', 'slug' => 'intruder']);

        $this->putJson("/api/brands/{$this->brand->id}/venues/{$venue->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_venue(): void
    {
        $venue = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Gone', 'slug' => 'gone']);

        $this->deleteJson("/api/brands/{$this->brand->id}/venues/{$venue->id}")->assertNoContent();
        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }

    public function test_destroy_returns_404_for_venue_from_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $venue = Venue::create(['brand_id' => $other->id, 'name' => 'Intruder', 'slug' => 'intruder']);

        $this->deleteJson("/api/brands/{$this->brand->id}/venues/{$venue->id}")->assertNotFound();
    }
}
