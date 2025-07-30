<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 預設佇列連線名稱
    |--------------------------------------------------------------------------
    |
    | Laravel 的佇列 API 支援各種後端服務來儲存和處理佇列任務。
    | 在這裡您可以設定預設的佇列連線。
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | 佇列連線
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以配置每個佇列後端的連線資訊，並設定您希望
    | 用於各種佇列任務的佇列和連線。
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 批次處理
    |--------------------------------------------------------------------------
    |
    | 以下選項配置批次處理的資料庫和表格，用於儲存
    | 關於您的批次任務的中繼資料。
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | 失敗的佇列任務
    |--------------------------------------------------------------------------
    |
    | 這些選項配置失敗佇列任務記錄的行為，讓您可以控制
    | 失敗任務的儲存方式和位置。
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];