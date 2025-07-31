<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | 應用程式名稱
    |--------------------------------------------------------------------------
    |
    | 此值是應用程式的名稱。此值用於通知和
    | 您需要將應用程式名稱放置在其他地方的任何其他位置。
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | 應用程式環境
    |--------------------------------------------------------------------------
    |
    | 此值決定應用程式目前執行的「環境」。這可能
    | 決定您希望如何為各種服務配置應用程式。
    | 在您的 .env 檔案中設定此項。
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | 應用程式除錯模式
    |--------------------------------------------------------------------------
    |
    | 當您的應用程式處於除錯模式時，詳細的錯誤訊息與
    | 堆疊追蹤將顯示在每個錯誤上。如果停用，
    | 將向使用者顯示簡單的通用錯誤頁面。
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | 應用程式URL
    |--------------------------------------------------------------------------
    |
    | 此URL由控制台用來正確產生URL
    | 在使用Artisan命令列工具時。您應該將此設定為
    | 應用程式的根目錄，以便在建立URL時使用。
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | 應用程式時區
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以指定應用程式的預設時區，這將
    | 由PHP日期和日期時間函數使用。我們已經設定
    | 這個為您提供合理的預設值。
    |
    */

    'timezone' => 'Asia/Taipei',

    /*
    |--------------------------------------------------------------------------
    | 應用程式語言環境配置
    |--------------------------------------------------------------------------
    |
    | 應用程式語言環境決定將由翻譯使用的預設語言環境
    | 服務提供者。您可以自由地將此值設定為任何
    | 應用程式支援的語言環境。
    |
    */

    'locale' => 'zh_TW',

    /*
    |--------------------------------------------------------------------------
    | 應用程式備用語言環境
    |--------------------------------------------------------------------------
    |
    | 備用語言環境決定當目前語言環境不可用時將使用的語言環境。
    | 您可以將其變更為對應於任何資料夾
    | 語言資料夾。
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker語言環境
    |--------------------------------------------------------------------------
    |
    | 此語言環境將由Faker PHP程式庫使用，當它
    | 為您的資料庫種子產生假資料。例如，這將
    | 用於取得本地化的電話號碼、街道地址資訊等。
    |
    */

    'faker_locale' => 'zh_TW',

    /*
    |--------------------------------------------------------------------------
    | 加密金鑰
    |--------------------------------------------------------------------------
    |
    | 此金鑰由Illuminate加密服務使用，應該設定
    | 為32個字元的隨機字串，否則這些加密字串
    | 將不安全。請在部署應用程式之前執行此操作！
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | 維護模式驅動程式
    |--------------------------------------------------------------------------
    |
    | 這些配置選項決定用於Laravel的
    | 「維護模式」功能的驅動程式。「cache」驅動程式將
    | 允許在多台機器上控制維護模式。
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | 自動載入的服務提供者
    |--------------------------------------------------------------------------
    |
    | 此處列出的服務提供者將由Laravel自動載入
    | 對您的應用程式的請求。隨時新增您自己的服務到
    | 此陣列以授予應用程式擴充功能。
    |
    */

    'providers' => [
        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\ResponseFormatterServiceProvider::class,
        App\Providers\TokenServiceProvider::class,
        App\Providers\ActionServiceProvider::class,
        App\Providers\PermissionServiceProvider::class,
        App\Providers\DocumentationServiceProvider::class,
        App\Providers\ExceptionServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 類別別名
    |--------------------------------------------------------------------------
    |
    | 此陣列的類別別名將在
    | 應用程式啟動時註冊。但是，請隨時註冊
    | 您需要的任意數量，因為別名是「延遲」載入的，所以它們不會
    | 阻礙效能。
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

];