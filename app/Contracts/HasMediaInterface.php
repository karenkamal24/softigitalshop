<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasMediaInterface
{
    /** @return MorphMany<Media, Model> */
    public function media(): MorphMany;

    /** @return mixed */
    public function getKey();
}




