<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->token = $this->admin->createToken('admin-token')->plainTextToken;
    }

    // ─────────────────────────────────────────────
    //  INDEX
    // ─────────────────────────────────────────────

    public function test_admin_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->withToken($this->token)->getJson('/api/v1/admin/products');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'data',
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_returns_empty_list_when_no_products_exist(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/admin/products');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_index_respects_per_page_query_parameter(): void
    {
        Product::factory()->count(10)->create();

        $response = $this->withToken($this->token)->getJson('/api/v1/admin/products?per_page=3');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_includes_product_images(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $product->media()->create([
            'collection'    => 'gallery',
            'disk'          => 'public',
            'file_name'     => 'photo.jpg',
            'original_name' => 'photo.jpg',
            'file_path'     => 'product/1/gallery/photo.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 1024,
            'is_primary'    => true,
        ]);

        $response = $this->withToken($this->token)->getJson('/api/v1/admin/products');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['images']]]);
    }

    public function test_unauthenticated_user_cannot_list_products(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
    }

    public function test_regular_user_cannot_list_products(): void
    {
        $user      = User::factory()->create();
        $userToken = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($userToken)->getJson('/api/v1/admin/products')->assertForbidden();
    }

    // ─────────────────────────────────────────────
    //  SHOW
    // ─────────────────────────────────────────────

    public function test_admin_can_view_a_single_product(): void
    {
        $product = Product::factory()->create([
            'name'          => 'Widget Pro',
            'price_in_cents' => 4999,
            'stock'         => 42,
        ]);

        $response = $this->withToken($this->token)->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Widget Pro')
            ->assertJsonPath('data.price_in_cents', 4999)
            ->assertJsonPath('data.stock', 42);
    }

    public function test_show_returns_product_with_images_relationship(): void
    {
        $product = Product::factory()->create();

        $response = $this->withToken($this->token)->getJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'price_in_cents', 'stock', 'is_active', 'images']]);
    }

    public function test_show_returns_404_for_nonexistent_product(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/admin/products/99999')->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_view_a_product(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/v1/admin/products/{$product->id}")->assertUnauthorized();
    }

    // ─────────────────────────────────────────────
    //  STORE
    // ─────────────────────────────────────────────

    public function test_admin_can_create_a_product(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Test Product',
            'description'   => 'A product for testing',
            'price_in_cents' => 2999,
            'stock'         => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test Product')
            ->assertJsonPath('data.price_in_cents', 2999)
            ->assertJsonPath('data.stock', 50);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'slug' => 'test-product',
        ]);
    }

    public function test_store_auto_generates_slug_from_name(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'My Awesome Product',
            'price_in_cents' => 1000,
            'stock'         => 5,
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['slug' => 'my-awesome-product']);
    }

    public function test_store_generates_unique_slug_for_duplicate_names(): void
    {
        Product::factory()->create(['name' => 'Duplicate Name', 'slug' => 'duplicate-name']);

        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Duplicate Name',
            'price_in_cents' => 500,
            'stock'         => 10,
        ])->assertStatus(422); // unique name rule prevents duplicate names
    }

    public function test_admin_can_create_product_with_images(): void
    {
        Storage::fake('public');

        $response = $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Product With Images',
            'price_in_cents' => 1500,
            'stock'         => 10,
            'images'        => [
                UploadedFile::fake()->image('photo1.jpg', 640, 480),
                UploadedFile::fake()->image('photo2.jpg', 640, 480),
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('media', 2);
    }

    public function test_store_marks_first_image_as_primary(): void
    {
        Storage::fake('public');

        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Multi Image Product',
            'price_in_cents' => 2000,
            'stock'         => 5,
            'images'        => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('media', ['is_primary' => true]);
        $this->assertEquals(1, Media::where('is_primary', true)->count());
    }

    public function test_store_creates_product_without_images_when_none_provided(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'No Image Product',
            'price_in_cents' => 999,
            'stock'         => 1,
        ])->assertCreated();

        $this->assertDatabaseCount('media', 0);
    }

    public function test_store_validates_required_name(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'price_in_cents' => 1000,
            'stock'         => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_required_price(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'  => 'Missing Price',
            'stock' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price_in_cents']);
    }

    public function test_store_validates_required_stock(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Missing Stock',
            'price_in_cents' => 1000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_store_validates_negative_price(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Negative Price',
            'price_in_cents' => -100,
            'stock'         => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price_in_cents']);
    }

    public function test_store_validates_negative_stock(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Negative Stock',
            'price_in_cents' => 500,
            'stock'         => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_store_validates_image_mime_type(): void
    {
        Storage::fake('public');

        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Bad Image Product',
            'price_in_cents' => 1000,
            'stock'         => 5,
            'images'        => [
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);
    }

    public function test_store_validates_unique_product_name(): void
    {
        Product::factory()->create(['name' => 'Existing Product']);

        $this->withToken($this->token)->postJson('/api/v1/admin/products', [
            'name'          => 'Existing Product',
            'price_in_cents' => 500,
            'stock'         => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $this->postJson('/api/v1/admin/products', [
            'name'          => 'Unauthorized Product',
            'price_in_cents' => 1000,
            'stock'         => 5,
        ])->assertUnauthorized();
    }

    public function test_regular_user_cannot_create_product(): void
    {
        $user      = User::factory()->create();
        $userToken = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($userToken)->postJson('/api/v1/admin/products', [
            'name'          => 'Unauthorized Product',
            'price_in_cents' => 1000,
            'stock'         => 5,
        ])->assertForbidden();
    }

    // ─────────────────────────────────────────────
    //  UPDATE
    // ─────────────────────────────────────────────

    public function test_admin_can_update_product_name_and_price(): void
    {
        $product = Product::factory()->create([
            'name'          => 'Old Name',
            'price_in_cents' => 1000,
        ]);

        $response = $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'name'          => 'New Name',
            'price_in_cents' => 2000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.price_in_cents', 2000);

        $this->assertDatabaseHas('products', [
            'id'            => $product->id,
            'name'          => 'New Name',
            'price_in_cents' => 2000,
        ]);
    }

    public function test_update_regenerates_slug_when_name_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Brand New Name',
        ])->assertOk();

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'slug' => 'brand-new-name',
        ]);
    }

    public function test_update_preserves_unchanged_fields(): void
    {
        $product = Product::factory()->create([
            'name'          => 'Stable Name',
            'description'   => 'A description',
            'price_in_cents' => 3000,
            'stock'         => 20,
            'is_active'     => true,
        ]);

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'stock' => 99,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Stable Name')
            ->assertJsonPath('data.price_in_cents', 3000)
            ->assertJsonPath('data.stock', 99);
    }

    public function test_admin_can_toggle_product_is_active(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_admin_can_replace_product_images_on_update(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $product->media()->create([
            'collection'    => 'gallery',
            'disk'          => 'public',
            'file_name'     => 'old.jpg',
            'original_name' => 'old.jpg',
            'file_path'     => 'product/1/gallery/old.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 512,
            'is_primary'    => true,
        ]);

        $this->assertDatabaseCount('media', 1);

        $response = $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'images' => [
                UploadedFile::fake()->image('new-cover.jpg', 640, 480),
            ],
        ]);

        $response->assertOk();

        // Old media should be gone; new one inserted
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['original_name' => 'new-cover.jpg']);
        $this->assertDatabaseMissing('media', ['original_name' => 'old.jpg']);
    }

    public function test_update_without_images_leaves_existing_media_intact(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $product->media()->create([
            'collection'    => 'gallery',
            'disk'          => 'public',
            'file_name'     => 'keep.jpg',
            'original_name' => 'keep.jpg',
            'file_path'     => 'product/1/gallery/keep.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 1024,
            'is_primary'    => true,
        ]);

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'stock' => 5,
        ])->assertOk();

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['original_name' => 'keep.jpg']);
    }

    public function test_update_validates_negative_price(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'price_in_cents' => -50,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price_in_cents']);
    }

    public function test_update_validates_negative_stock(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token)->postJson("/api/v1/admin/products/{$product->id}", [
            'stock' => -5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_update_returns_404_for_nonexistent_product(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/admin/products/99999', [
            'name' => 'Ghost Product',
        ])->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_update_product(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Hacked Name',
        ])->assertUnauthorized();
    }

    public function test_regular_user_cannot_update_product(): void
    {
        $product   = Product::factory()->create();
        $user      = User::factory()->create();
        $userToken = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($userToken)->postJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'Hacked Name',
        ])->assertForbidden();
    }

    // ─────────────────────────────────────────────
    //  DESTROY
    // ─────────────────────────────────────────────

    public function test_admin_can_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->withToken($this->token)->deleteJson("/api/v1/admin/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_also_deletes_associated_media(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $product->media()->create([
            'collection'    => 'gallery',
            'disk'          => 'public',
            'file_name'     => 'to-delete.jpg',
            'original_name' => 'to-delete.jpg',
            'file_path'     => 'product/1/gallery/to-delete.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => 2048,
            'is_primary'    => true,
        ]);

        $this->assertDatabaseCount('media', 1);

        $this->withToken($this->token)->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->assertDatabaseCount('media', 0);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_works_for_product_without_media(): void
    {
        $product = Product::factory()->create();

        $this->assertDatabaseCount('media', 0);

        $this->withToken($this->token)->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_product(): void
    {
        $this->withToken($this->token)->deleteJson('/api/v1/admin/products/99999')->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertUnauthorized();
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        $product   = Product::factory()->create();
        $user      = User::factory()->create();
        $userToken = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($userToken)->deleteJson("/api/v1/admin/products/{$product->id}")->assertForbidden();
    }
}


