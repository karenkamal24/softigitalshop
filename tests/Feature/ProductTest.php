<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_browse_products(): void
    {
        Product::factory(5)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'data',
            ]);
    }

    public function test_only_active_products_are_listed(): void
    {
        Product::factory(3)->create(['is_active' => true]);
        Product::factory(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_products_are_paginated(): void
    {
        Product::factory(20)->create();

        $response = $this->getJson('/api/v1/products?page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 20);
    }
}




