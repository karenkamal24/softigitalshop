<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'address' => '123 Test Street, Cairo, Egypt',
        ]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;
    }

    public function test_authenticated_user_can_place_order(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'price_in_cents' => 1500,
            'stock' => 10,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'total_amount_cents',
                    'total_quantity',
                    'status',
                    'items',
                ],
            ])
            ->assertJsonPath('data.total_amount_cents', 3000)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount_cents' => 3000,
        ]);

        $product->refresh();
        $this->assertEquals(8, $product->stock);
    }

    public function test_order_with_multiple_products(): void
    {
        Queue::fake();

        $productA = Product::factory()->create(['price_in_cents' => 1000, 'stock' => 10]);
        $productB = Product::factory()->create(['price_in_cents' => 2000, 'stock' => 5]);

        $response = $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 3],
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount_cents', 5000)
            ->assertJsonPath('data.total_quantity', 4);
    }

    public function test_order_fails_with_insufficient_stock(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'price_in_cents' => 1500,
            'stock' => 2,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_order_fails_with_inactive_product(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'is_active' => false,
            'stock' => 100,
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_order_validation_fails_with_empty_items(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_order_dispatches_fulfillment_job(): void
    {
        Queue::fake();

        $product = Product::factory()->create(['price_in_cents' => 1000, 'stock' => 10]);

        $this->withToken($this->token)->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        Queue::assertPushed(\App\Jobs\NotifyFulfillmentServiceJob::class);
    }
}




