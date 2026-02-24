<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mediaService = app(MediaService::class);

        return [
            'id' => $this->id,
            'url' => $mediaService->getUrl($this->resource),
            'thumbnail' => $mediaService->getThumbnailUrl($this->resource),
            'is_primary' => $this->is_primary,
        ];
    }
}
