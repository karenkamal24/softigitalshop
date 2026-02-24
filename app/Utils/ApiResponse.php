<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /** @param array<string, mixed> $errors */
    public static function send(
        int $code = Response::HTTP_OK,
        string $message = 'Success response',
        mixed $data = [],
        ?string $resource = null,
        array $errors = []
    ): JsonResponse {
        $data = self::prepareData($data, $resource);

        $response = [
            'status' => $code,
            'message' => $message,
            'meta' => $data['meta'] ?? null,
            'data' => $data['items'],
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    public static function success(string $message = 'Success response', mixed $data = [], ?string $resource = null): JsonResponse
    {
        return self::send(Response::HTTP_OK, $message, $data, $resource);
    }

    public static function created(string $message = 'Resource created successfully', mixed $data = [], ?string $resource = null): JsonResponse
    {
        return self::send(Response::HTTP_CREATED, $message, $data, $resource);
    }

    /** @param array<string, mixed> $errors */
    public static function badRequest(string $message, array $errors = []): JsonResponse
    {
        return self::send(code: Response::HTTP_BAD_REQUEST, message: $message, errors: $errors);
    }

    public static function forbidden(string $message): JsonResponse
    {
        return self::send(Response::HTTP_FORBIDDEN, $message);
    }

    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::send(Response::HTTP_NOT_FOUND, $message);
    }

    public static function validationError(string $message, mixed $errors = [], ?string $resource = null): JsonResponse
    {
        return self::send(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $errors, $resource);
    }

    public static function error(string $message = 'Internal server error'): JsonResponse
    {
        return self::send(Response::HTTP_INTERNAL_SERVER_ERROR, $message);
    }

    /**
     * @param LengthAwarePaginator<int, mixed> $paginator
     * @return array<string, int>
     */
    protected static function getPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /** @return array<string, mixed> */
    protected static function prepareData(mixed $data, ?string $resource): array
    {
        if ($data instanceof LengthAwarePaginator) {
            // Apply resource mapping if provided (as a string class name)
            if ($resource !== null && is_string($resource)) {
                $items = $resource::collection($data->items());
            } else {
                $items = $data->items();
            }

            return [
                'meta' => self::getPaginationMeta($data),
                'items' => $items,
            ];
        }

        return ['items' => $data];
    }
}
