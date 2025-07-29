<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 預設檔案系統磁碟
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以指定預設的檔案系統磁碟，該磁碟應該用於
    | Laravel的檔案系統。「local」磁碟以及各種雲端
    | 磁碟可供您的應用程式使用。
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | 檔案系統磁碟
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以配置任意數量的檔案系統「磁碟」，並且您
    | 甚至可以配置同一驅動程式的多個磁碟。預設值已經
    | 為每個可用的檔案系統驅動程式設定了範例。
    |
    | 支援的驅動程式："local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 符號連結
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以配置符號連結，這些連結將在執行
    | "storage:link" Artisan命令時建立。陣列鍵應該是
    | 連結的路徑，值應該是它們的目標。
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];