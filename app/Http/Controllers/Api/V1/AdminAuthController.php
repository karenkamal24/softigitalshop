<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->authService->adminLogin($request->validated());

        return ApiResponse::success('Admin login successful', [
            'admin' => new UserResource($result['admin']),
            'token' => $result['token'],
        ]);
    }
}




