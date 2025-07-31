<?php

namespace App\Providers;

use App\Services\ExceptionHandlerService;
use Illuminate\Support\ServiceProvider;

/**
 * 例外處理服務提供者
 * 
 * 註冊例外處理相關的服務
 */
class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandlerService::class, function ($app) {
            // 延遲解析 ResponseFormatterInterface 以避免循環依賴
            return new ExceptionHandlerService(
                $app->make(\App\Contracts\ResponseFormatterInterface::class)
            );
        });
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        //
    }
}