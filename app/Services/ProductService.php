<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * 
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function list(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $version  = $this->getCacheVersion();
        $cacheKey = "products:v{$version}:page:{$page}:perPage:{$perPage}";

        /** @var LengthAwarePaginator<int, Product> */
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $page) {
            return Product::where('is_active', true)
                ->with('media')
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);
        });
    }

    /** @param array<string, mixed> $data */
    public function store(array $data): Product
    {
        $data['slug'] = $this->generateUniqueSlug((string) $data['name']);

        $product = Product::create($data);

        $this->clearCache();

        return $product->load('media');
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['name'])) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name'], $product->id);
        }

        $product->update($data);

        $this->clearCache();

        return $product;
    }


    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        return DB::transaction(function () use ($name, $excludeId) {
            $slug         = Str::slug($name);
            $originalSlug = $slug;
            $counter      = 1;

            while (true) {
                $exists = Product::where('slug', $slug)
                    ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                    ->lockForUpdate()
                    ->exists();

                if (! $exists) {
                    break;
                }

                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            return $slug;
        });
    }

    private function getCacheVersion(): int
    {
        return (int) Cache::get('products:cache_version', 1);
    }

    private function clearCache(): void
    {
        Cache::increment('products:cache_version');
    }
}
