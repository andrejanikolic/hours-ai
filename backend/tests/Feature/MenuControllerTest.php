<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Menu;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuControllerTest extends TestCase
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

    private function menuUrl(int $menuId = null): string
    {
        $base = "/api/brands/{$this->brand->id}/venues/{$this->venue->id}/menus";
        return $menuId ? "{$base}/{$menuId}" : $base;
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_menus_ordered_by_position(): void
    {
        Menu::create(['venue_id' => $this->venue->id, 'name' => 'Dinner',    'position' => 2]);
        Menu::create(['venue_id' => $this->venue->id, 'name' => 'Breakfast', 'position' => 1]);

        $response = $this->getJson($this->menuUrl())->assertOk();

        $this->assertSame('Breakfast', $response->json('0.name'));
        $this->assertSame('Dinner',    $response->json('1.name'));
    }

    public function test_index_does_not_return_menus_from_other_venues(): void
    {
        $other = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Other', 'slug' => 'other']);
        Menu::create(['venue_id' => $other->id, 'name' => 'Hidden']);

        $this->getJson($this->menuUrl())->assertOk()->assertJsonCount(0);
    }

    public function test_index_returns_404_when_venue_belongs_to_different_brand(): void
    {
        $otherBrand = Brand::create(['name' => 'Other', 'slug' => 'other']);

        $this->getJson("/api/brands/{$otherBrand->id}/venues/{$this->venue->id}/menus")
            ->assertNotFound();
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_menu(): void
    {
        $menu = Menu::create(['venue_id' => $this->venue->id, 'name' => 'Lunch']);

        $this->getJson($this->menuUrl($menu->id))
            ->assertOk()
            ->assertJsonPath('name', 'Lunch');
    }

    public function test_show_returns_404_for_menu_from_different_venue(): void
    {
        $other = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['venue_id' => $other->id, 'name' => 'Intruder']);

        $this->getJson($this->menuUrl($menu->id))->assertNotFound();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_menu_under_venue(): void
    {
        $this->postJson($this->menuUrl(), [
            'name'     => 'Happy Hour',
            'position' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Happy Hour')
            ->assertJsonPath('venue_id', $this->venue->id);
    }

    public function test_store_requires_name(): void
    {
        $this->postJson($this->menuUrl(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_negative_position(): void
    {
        $this->postJson($this->menuUrl(), ['name' => 'Test', 'position' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_menu(): void
    {
        $menu = Menu::create(['venue_id' => $this->venue->id, 'name' => 'Old']);

        $this->putJson($this->menuUrl($menu->id), ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated');
    }

    public function test_update_returns_404_for_menu_from_different_venue(): void
    {
        $other = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['venue_id' => $other->id, 'name' => 'Intruder']);

        $this->putJson($this->menuUrl($menu->id), ['name' => 'Hacked'])->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_menu(): void
    {
        $menu = Menu::create(['venue_id' => $this->venue->id, 'name' => 'Gone']);

        $this->deleteJson($this->menuUrl($menu->id))->assertNoContent();
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_destroy_returns_404_for_menu_from_different_venue(): void
    {
        $other = Venue::create(['brand_id' => $this->brand->id, 'name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['venue_id' => $other->id, 'name' => 'Intruder']);

        $this->deleteJson($this->menuUrl($menu->id))->assertNotFound();
    }
}
