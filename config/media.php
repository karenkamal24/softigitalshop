<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default disk for storing uploaded media files.
    |
    */

    'default_disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | MIME types and extensions allowed for upload. Used for validation.
    |
    */

    'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max File Size (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum upload size in kilobytes. 5120 = 5MB.
    |
    */

    'max_file_size_kb' => (int) env('MEDIA_MAX_FILE_SIZE_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Mediable Models
    |--------------------------------------------------------------------------
    |
    | Models that can have media attached. Add new models here for
    | extensibility (e.g. App\Models\BlogPost, App\Models\Review).
    |
    */

    'mediable_types' => [
        \App\Models\User::class,
        \App\Models\Product::class,
        // Future: \App\Models\BlogPost::class,
        // Future: \App\Models\Review::class,
    ],

];




