<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Admin;
use App\Models\Media;
use App\Services\MediaService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $mediableType = $request->validated('mediable_type');
        $mediable = $mediableType::findOrFail($request->validated('mediable_id'));

        if (! $this->authorizeMediaUpload($mediable)) {
            return ApiResponse::forbidden('You are not authorized to add media to this resource.');
        }

        $media = $this->mediaService->upload(
            $mediable,
            $request->file('file'),
            $request->validated('collection'),
            $request->validated('disk') ?? config('media.default_disk', 'public')
        );

        return ApiResponse::created('Media uploaded successfully', new MediaResource($media));
    }

    public function destroy(Media $media): JsonResponse
    {
        if (! $this->authorizeMediaDeletion($media)) {
            return ApiResponse::forbidden('You are not authorized to delete this media.');
        }

        $this->mediaService->delete($media);

        return ApiResponse::success('Media deleted successfully');
    }

    private function authorizeMediaUpload(object $mediable): bool
    {
        if ($mediable instanceof \App\Models\User) {
            return auth()->check() && auth()->id() === $mediable->id;
        }

        if ($mediable instanceof \App\Models\Product) {
            return auth()->check() && auth()->user() instanceof Admin;
        }

        return false;
    }

    private function authorizeMediaDeletion(Media $media): bool
    {
        $mediable = $media->mediable;

        if ($mediable instanceof \App\Models\User) {
            return auth()->check() && auth()->id() === $mediable->id;
        }

        if ($mediable instanceof \App\Models\Product) {
            return auth()->check() && auth()->user() instanceof Admin;
        }

        return false;
    }
}

