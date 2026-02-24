<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\MediaService;
use App\Services\ProductService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly MediaService $mediaService,
    ) {}

    public function index(): JsonResponse
    {
        $perPage = request()->query('per_page', 15);
        $products = Product::with('media')->paginate($perPage);

        return ApiResponse::success('Products retrieved successfully', $products, ProductResource::class);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('media');

        return ApiResponse::success('Product retrieved successfully', new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = $this->productService->store($request->safe()->except('images'));

            if ($request->hasFile('images')) {
                $files = $request->file('images');
                $this->mediaService->uploadMultiple(
                    $product,
                    is_array($files) ? $files : [$files],
                    'gallery'
                );
            }
            return $product;
        });
        $product->load('media');

        return ApiResponse::created('Product created successfully', new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = DB::transaction(function () use ($request, $product) {
            $product = $this->productService->update($product, $request->safe()->except('images'));

            if ($request->hasFile('images')) {
                $existing = $product->media()->where('collection', 'gallery')->get();
                foreach ($existing as $media) {
                    $this->mediaService->delete($media);
                }

                $files = $request->file('images');
                $this->mediaService->uploadMultiple($product, is_array($files) ? $files : [$files], 'gallery');
                $product->load('media');
            }

            return $product;
        });

        return ApiResponse::success('Product updated successfully', new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        // Delete all media associated with product
        foreach ($product->media as $media) {
            $this->mediaService->delete($media);
        }

        $product->delete();

        return ApiResponse::success('Product deleted successfully');
    }
}

