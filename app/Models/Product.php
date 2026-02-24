<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\HasMediaInterface;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements HasMediaInterface
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_in_cents',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_in_cents' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
