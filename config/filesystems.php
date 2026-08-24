<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ],

        'google' => [
            'driver' => 'google',
            // Service accounts have no storage quota and cannot write files into a
            // personal (non-Workspace) Drive, so this disk authenticates via OAuth
            // as a real Google account instead. Run `php artisan google-drive:authorize`
            // once to obtain GOOGLE_DRIVE_REFRESH_TOKEN.
            'oauth_client_id'     => env('GOOGLE_OAUTH_CLIENT_ID'),
            'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'oauth_refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            // The ID of the folder in the authorized account's Drive to upload into.
            // Get it from: https://drive.google.com/drive/folders/<FOLDER_ID>
            'shared_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', null),
        ],

    ],

];
