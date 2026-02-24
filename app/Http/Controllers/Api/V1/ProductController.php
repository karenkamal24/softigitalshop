<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(): JsonResponse
    {
        $perPage = request()->query('per_page', 15);
        $page = request()->query('page', 1);
        
        $products = $this->productService->list((int)$perPage, (int)$page);

        return ApiResponse::success('Products retrieved successfully', $products, ProductResource::class);
    }
}



