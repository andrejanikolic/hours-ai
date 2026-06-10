<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuControllerTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_menus_ordered_by_position(): void
    {
        Menu::create(['brand_id' => $this->brand->id, 'name' => 'Dinner',    'position' => 2]);
        Menu::create(['brand_id' => $this->brand->id, 'name' => 'Breakfast', 'position' => 1]);

        $response = $this->getJson("/api/brands/{$this->brand->id}/menus")->assertOk();

        $this->assertSame('Breakfast', $response->json('0.name'));
        $this->assertSame('Dinner',    $response->json('1.name'));
    }

    public function test_index_does_not_return_menus_from_other_brands(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        Menu::create(['brand_id' => $other->id, 'name' => 'Hidden']);

        $this->getJson("/api/brands/{$this->brand->id}/menus")
            ->assertOk()
            ->assertJsonCount(0);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_menu(): void
    {
        $menu = Menu::create(['brand_id' => $this->brand->id, 'name' => 'Lunch']);

        $this->getJson("/api/brands/{$this->brand->id}/menus/{$menu->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Lunch');
    }

    public function test_show_returns_404_for_menu_from_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['brand_id' => $other->id, 'name' => 'Intruder']);

        $this->getJson("/api/brands/{$this->brand->id}/menus/{$menu->id}")->assertNotFound();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_menu_under_brand(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/menus", [
            'name'     => 'Happy Hour',
            'position' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Happy Hour')
            ->assertJsonPath('brand_id', $this->brand->id);
    }

    public function test_store_requires_name(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/menus", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_negative_position(): void
    {
        $this->postJson("/api/brands/{$this->brand->id}/menus", ['name' => 'Test', 'position' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_menu(): void
    {
        $menu = Menu::create(['brand_id' => $this->brand->id, 'name' => 'Old']);

        $this->putJson("/api/brands/{$this->brand->id}/menus/{$menu->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated');
    }

    public function test_update_returns_404_for_menu_from_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['brand_id' => $other->id, 'name' => 'Intruder']);

        $this->putJson("/api/brands/{$this->brand->id}/menus/{$menu->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_menu(): void
    {
        $menu = Menu::create(['brand_id' => $this->brand->id, 'name' => 'Gone']);

        $this->deleteJson("/api/brands/{$this->brand->id}/menus/{$menu->id}")->assertNoContent();
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_destroy_returns_404_for_menu_from_different_brand(): void
    {
        $other = Brand::create(['name' => 'Other', 'slug' => 'other']);
        $menu  = Menu::create(['brand_id' => $other->id, 'name' => 'Intruder']);

        $this->deleteJson("/api/brands/{$this->brand->id}/menus/{$menu->id}")->assertNotFound();
    }
}
