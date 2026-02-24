<?php

declare(strict_types=1);

namespace App\Traits;

use App\Contracts\HasMediaInterface;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

trait HasMedia
{
    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /**
     * Add media to the model.
     *
     * @param  string  $collection  e.g. 'profile_picture', 'cover_photo', 'gallery'
     * @param  string|null  $disk  e.g. 'public', 's3' (null uses config default)
     */
    public function addMedia(
        UploadedFile $file,
        string $collection = 'default',
        ?string $disk = null
    ): Media {
        return app(MediaService::class)->upload($this, $file, $collection, $disk);
    }

    /**
     * Get all media for the model, optionally filtered by collection.
     *
     * @return Collection<int, Media>
     */
    public function getMedia(?string $collection = null): Collection
    {
        $query = $this->media();

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get the first media item, optionally filtered by collection.
     */
    public function getFirstMedia(?string $collection = null): ?Media
    {
        $query = $this->media();

        if ($collection !== null) {
            $query->where('collection', $collection);
        }

        return $query->orderByDesc('created_at')->first();
    }
}

