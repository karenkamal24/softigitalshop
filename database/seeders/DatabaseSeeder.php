<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@softigital.com',
        ]);

        Admin::factory()->create([
            'name' => 'Store Admin',
            'email' => 'admin@softigital.com',
        ]);

        Product::factory(20)->create();
    }
}
