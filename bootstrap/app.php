<?php

/*
|--------------------------------------------------------------------------
| 建立應用程式實例
|--------------------------------------------------------------------------
|
| 這裡是Laravel應用程式的啟動點，我們將建立一個新的Laravel應用程式實例
| 並將其返回給呼叫者。
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| 綁定重要介面
|--------------------------------------------------------------------------
|
| 接下來，我們需要綁定一些重要的介面到容器中，這樣它們就可以在需要時
| 被解析出來。這些介面是Laravel框架的核心部分。
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| 返回應用程式實例
|--------------------------------------------------------------------------
|
| 這個腳本返回應用程式實例。這個實例將被用於處理進入應用程式的請求
| 並將回應發送回客戶端的瀏覽器。
|
*/

return $app;