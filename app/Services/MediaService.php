<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasMediaInterface;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaService
{
    /**
     * Upload a file and attach it to a model.
     */
    public function upload(
        HasMediaInterface $model,
        UploadedFile $file,
        string $collection = 'default',
        ?string $disk = null
    ): Media {
        $disk = $disk ?? Config::get('media.default_disk', 'public');


        $path = $file->store($this->getFolderForModel($model, $collection), $disk);

        if ($path === false) {
            throw new RuntimeException("Failed to store file [{$file->getClientOriginalName()}] on disk [{$disk}].");
        }

        // Create thumbnail for image files
        $this->createThumbnail($file, $path, $disk);

        return $model->media()->create([
            'collection' => $collection,
            'disk'       => $disk,
            'file_name'  => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'file_path'  => $path,
            'mime_type'  => $file->getMimeType(),
            'size'       => $file->getSize(),
        ]);
    }

    /**
     * Delete media and remove file from storage.
     */
    public function delete(Media $media): bool
    {
        $disk = Storage::disk($media->disk);

        if ($disk->exists($media->file_path)) {
            $disk->delete($media->file_path);
        }

        // Delete thumbnail if exists
        $thumbPath = $this->getThumbnailPath($media->file_path);
        if ($disk->exists($thumbPath)) {
            $disk->delete($thumbPath);
        }

        return (bool) $media->delete();
    }


    public function getUrl(Media $media): string
    {
        return Storage::disk($media->disk)->url($media->file_path);
    }

    /**
     * Get thumbnail URL for media.
     */
    public function getThumbnailUrl(Media $media): string
    {
        $thumbPath = $this->getThumbnailPath($media->file_path);

        if (Storage::disk($media->disk)->exists($thumbPath)) {
            return Storage::disk($media->disk)->url($thumbPath);
        }

        // If thumb doesn't exist, return original URL
        return $this->getUrl($media);
    }

    /**
     * Upload multiple files and attach them to a model.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function uploadMultiple(
        HasMediaInterface $model,
        array $files,
        string $collection = 'default',
        ?string $disk = null
    ): Collection {
        $media = collect();
        $isFirst = true;

        foreach ($files as $file) {
            $uploadedMedia = $this->upload($model, $file, $collection, $disk);

            // Mark first image as primary
            if ($isFirst) {
                $uploadedMedia->update(['is_primary' => true]);
                $isFirst = false;
            }

            $media->push($uploadedMedia);
        }

        return $media;
    }

    /**
     * Get the folder path for storing media based on model and collection.
     */
    private function getFolderForModel(HasMediaInterface $model, string $collection): string
    {
        $modelFolder = strtolower(class_basename($model));
        $modelId     = $model->getKey();

        return "{$modelFolder}/{$modelId}/{$collection}";
    }

    /**
     * Get thumbnail path from original file path.
     */
    private function getThumbnailPath(string $filePath): string
    {
        $parts = explode('/', $filePath);
        $filename = array_pop($parts);
        return implode('/', $parts) . '/thumbs/' . $filename;
    }

    /**
     * Create thumbnail for uploaded image using GD.
     */
    private function createThumbnail(UploadedFile $file, string $filePath, string $disk): void
    {
        try {
            // Only create thumbnails for image files
            $mimeType = $file->getMimeType();
            if (!str_starts_with($mimeType, 'image/')) {
                return;
            }

            $sourcePath = $file->getPathname();

            // Load image based on mime type
            $image = match($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($sourcePath),
                'image/png' => imagecreatefrompng($sourcePath),
                'image/gif' => imagecreatefromgif($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                default => null,
            };

            if (!$image) {
                return;
            }

            // Get original dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate new dimensions (maintain aspect ratio, max 300x300)
            $newSize = 300;
            if ($width > $height) {
                $newWidth = $newSize;
                $newHeight = (int)($newSize * $height / $width);
            } else {
                $newHeight = $newSize;
                $newWidth = (int)($newSize * $width / $height);
            }

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }

            imagecopyresampled(
                $thumbnail,
                $image,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );

            // Get thumbnail path
            $thumbPath = $this->getThumbnailPath($filePath);
            $thumbFullPath = Storage::disk($disk)->path($thumbPath);

            // Create thumbs directory if not exists
            $thumbDir = dirname($thumbFullPath);
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            // Save thumbnail
            match($mimeType) {
                'image/jpeg' => imagejpeg($thumbnail, $thumbFullPath, 85),
                'image/png' => imagepng($thumbnail, $thumbFullPath, 9),
                'image/gif' => imagegif($thumbnail, $thumbFullPath),
                'image/webp' => imagewebp($thumbnail, $thumbFullPath, 85),
                default => null,
            };

            // Clean up
            imagedestroy($image);
            imagedestroy($thumbnail);
        } catch (\Exception $e) {
            // Log error but don't fail the upload if thumbnail creation fails
            \Log::warning("Failed to create thumbnail for {$filePath}: " . $e->getMessage());
        }
    }
}
