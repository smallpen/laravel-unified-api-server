<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\ResponseFormatterInterface;
use App\Services\ResponseFormatter;

/**
 * 回應格式化器服務提供者
 * 
 * 註冊ResponseFormatter服務到Laravel容器中
 * 提供依賴注入和單例模式支援
 */
class ResponseFormatterServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     * 
     * @return void
     */
    public function register(): void
    {
        // 將ResponseFormatterInterface綁定到ResponseFormatter實作
        $this->app->bind(ResponseFormatterInterface::class, ResponseFormatter::class);

        // 註冊ResponseFormatter為單例服務
        $this->app->singleton('response.formatter', function ($app) {
            return new ResponseFormatter();
        });

        // 提供別名方便使用
        $this->app->alias('response.formatter', ResponseFormatter::class);
        $this->app->alias('response.formatter', ResponseFormatterInterface::class);
    }

    /**
     * 啟動服務
     * 
     * @return void
     */
    public function boot(): void
    {
        // 這裡可以加入任何需要在服務啟動時執行的邏輯
    }

    /**
     * 取得服務提供者提供的服務
     * 
     * @return array
     */
    public function provides(): array
    {
        return [
            ResponseFormatterInterface::class,
            ResponseFormatter::class,
            'response.formatter',
        ];
    }
}