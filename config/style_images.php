<?php

return [

    // Flysystem disk the image files are written to. 'google' = Google Drive (see
    // config/filesystems.php); set STYLE_IMAGES_DISK=public/local to work without Drive.
    'disk' => env('STYLE_IMAGES_DISK', 'google'),

    // Top-level folder inside the Drive folder identified by GOOGLE_DRIVE_FOLDER_ID.
    'root_folder' => 'StyleImages',

    'max_file_kb' => (int) env('STYLE_IMAGE_MAX_KB', 5120),
    'max_files_per_request' => 10,
    'max_files_per_style' => 30,
    'allowed_mimes' => ['jpeg', 'jpg', 'png', 'webp'],

    // Gallery thumbnails: longest side in px. Keeps the grid from downloading
    // multi-MB phone photos from Drive for every tile.
    'thumbnail_max_side' => 480,
    'thumbnail_quality' => 80,

    // Browser cache lifetime for served files. Safe to be long: file names are random
    // UUIDs, so a given URL's bytes never change.
    'cache_seconds' => 86400,
];
