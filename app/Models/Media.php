<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',
        'disk',
        'file_name',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
