<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowroomApiTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create([
            'name' => 'Test Supplier',
            'markup_default' => 1.5,
        ]);

        $this->category = Category::create([
            'name' => 'Divani',
            'slug' => 'divani',
        ]);

        $this->product = Product::create([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'name' => 'Test Divano',
            'is_active' => true,
            'cost' => 100.00,
        ]);
    }

    // --- categories() ---

    public function test_categories_returns_list_with_active_product_count(): void
    {
        $response = $this->getJson('/api/showroom/categories');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'slug' => 'divani',
                'product_count' => 1,
            ]);
    }

    public function test_categories_excludes_empty_categories(): void
    {
        Category::create(['name' => 'Vuota', 'slug' => 'vuota']);

        $response = $this->getJson('/api/showroom/categories');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_categories_excludes_inactive_products_from_count(): void
    {
        Product::create([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'name' => 'Bozza',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/showroom/categories');

        $data = $response->json();
        $divani = collect($data)->firstWhere('slug', 'divani');
        $this->assertEquals(1, $divani['product_count']);
    }

    // --- products() ---

    public function test_products_returns_active_products(): void
    {
        $response = $this->getJson('/api/showroom/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Test Divano']);
    }

    public function test_products_excludes_inactive(): void
    {
        Product::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Inattivo',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/showroom/products');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_products_filters_by_category_slug(): void
    {
        $other = Category::create(['name' => 'Sedie', 'slug' => 'sedie']);
        Product::create([
            'supplier_id' => $this->supplier->id,
            'category_id' => $other->id,
            'name' => 'Test Sedia',
            'is_active' => true,
            'cost' => 50.00,
        ]);

        $response = $this->getJson('/api/showroom/products?category=divani');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Test Divano']);
    }

    public function test_products_calculates_price_from_cost_and_markup(): void
    {
        $response = $this->getJson('/api/showroom/products');

        $response->assertOk()
            ->assertJsonFragment(['price' => 150.0]); // 100 * 1.5
    }

    public function test_products_returns_null_price_when_cost_is_null(): void
    {
        Product::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'No Cost',
            'is_active' => true,
            'cost' => null,
        ]);

        $response = $this->getJson('/api/showroom/products');

        $data = $response->json('data');
        $noCost = collect($data)->firstWhere('name', 'No Cost');
        $this->assertNull($noCost['price']);
    }

    public function test_products_does_not_expose_private_fields(): void
    {
        $response = $this->getJson('/api/showroom/products');

        $item = $response->json('data.0');
        foreach (['cost', 'markup_override', 'price_list', 'notes', 'tags', 'source_url', 'source_file', 'sku'] as $field) {
            $this->assertArrayNotHasKey($field, $item, "Field '{$field}' must not be exposed.");
        }
    }

    // --- product() ---

    public function test_product_detail_returns_product_by_code(): void
    {
        $code = $this->product->product_code;

        $response = $this->getJson("/api/showroom/products/{$code}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test Divano', 'product_code' => $code]);
    }

    public function test_product_detail_returns_404_for_inactive(): void
    {
        $inactive = Product::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Inattivo',
            'is_active' => false,
        ]);

        $this->getJson("/api/showroom/products/{$inactive->product_code}")
            ->assertNotFound();
    }

    public function test_product_detail_returns_404_for_unknown_code(): void
    {
        $this->getJson('/api/showroom/products/P9999')->assertNotFound();
    }

    public function test_product_detail_does_not_expose_private_fields(): void
    {
        $code = $this->product->product_code;
        $response = $this->getJson("/api/showroom/products/{$code}");

        $item = $response->json();
        foreach (['cost', 'markup_override', 'price_list', 'notes', 'tags', 'source_url', 'source_file', 'sku'] as $field) {
            $this->assertArrayNotHasKey($field, $item, "Field '{$field}' must not be exposed.");
        }
    }

    public function test_products_returns_empty_for_unknown_category(): void
    {
        $response = $this->getJson('/api/showroom/products?category=doesnotexist');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
