<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ServingTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_all_brands_with_serving_times(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        ServingTime::create([
            'parent_type' => 'brand',
            'parent_id'   => $brand->id,
            'type'        => 'weekday',
            'days'        => json_encode(['monday']),
            'time_from'   => '09:00',
            'time_to'     => '17:00',
            'working'     => true,
        ]);

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Acme')
            ->assertJsonPath('0.serving_times.0.type', 'weekday');
    }

    public function test_index_returns_empty_array_when_no_brands(): void
    {
        $this->getJson('/api/brands')->assertOk()->assertExactJson([]);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_brand_with_relations(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson("/api/brands/{$brand->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Acme')
            ->assertJsonStructure(['id', 'name', 'slug', 'serving_times', 'venues']);
    }

    public function test_show_returns_404_for_missing_brand(): void
    {
        $this->getJson('/api/brands/999')->assertNotFound();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_brand_and_returns_201(): void
    {
        $this->postJson('/api/brands', ['name' => 'New Brand', 'timezone' => 'America/New_York'])
            ->assertCreated()
            ->assertJsonPath('name', 'New Brand')
            ->assertJsonPath('slug', 'new-brand');
    }

    public function test_store_auto_generates_slug_from_name(): void
    {
        $this->postJson('/api/brands', ['name' => 'Hello World Brand'])
            ->assertCreated()
            ->assertJsonPath('slug', 'hello-world-brand');
    }

    public function test_store_accepts_explicit_slug(): void
    {
        $this->postJson('/api/brands', ['name' => 'Test', 'slug' => 'my-custom-slug'])
            ->assertCreated()
            ->assertJsonPath('slug', 'my-custom-slug');
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        Brand::create(['name' => 'First', 'slug' => 'taken']);

        $this->postJson('/api/brands', ['name' => 'Second', 'slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_requires_name(): void
    {
        $this->postJson('/api/brands', [])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_brand(): void
    {
        $brand = Brand::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->putJson("/api/brands/{$brand->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');
    }

    public function test_update_allows_same_slug_on_own_brand(): void
    {
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->putJson("/api/brands/{$brand->id}", ['slug' => 'acme'])->assertOk();
    }

    public function test_update_rejects_slug_taken_by_another_brand(): void
    {
        Brand::create(['name' => 'Other', 'slug' => 'taken']);
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->putJson("/api/brands/{$brand->id}", ['slug' => 'taken'])
            ->assertUnprocessable();
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_brand_and_returns_204(): void
    {
        $brand = Brand::create(['name' => 'Gone', 'slug' => 'gone']);

        $this->deleteJson("/api/brands/{$brand->id}")->assertNoContent();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_destroy_returns_404_for_missing_brand(): void
    {
        $this->deleteJson('/api/brands/999')->assertNotFound();
    }
}
