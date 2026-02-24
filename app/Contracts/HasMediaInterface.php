<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasMediaInterface
{
    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany;
}




