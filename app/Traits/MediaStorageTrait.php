<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait MediaStorageTrait
{
    /**
     * @param UploadedFile|array<int, UploadedFile> $media
     * @return array<int, string>|string
     */
    public function saveMedia(UploadedFile|array $media, string $folderName): array|string
    {
        if (is_array($media)) {
            $mediaPaths = [];
            foreach ($media as $item) {
                $mediaPaths[] = $item->store($folderName, 'public');
            }
            return $mediaPaths;
        }

        return $media->store($folderName, 'public');
    }

    /** @param string|array<int, string> $media */
    public function deleteMedia(string|array $media): bool
    {
        return Storage::disk('public')->delete($media);
    }
}
