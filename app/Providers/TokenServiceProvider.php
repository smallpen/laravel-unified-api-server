<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\TokenValidatorInterface;
use App\Contracts\TokenManagerInterface;
use App\Services\TokenValidator;
use App\Services\TokenManager;
use App\Services\TokenService;

/**
 * Token 服務提供者
 * 
 * 註冊 Token 相關服務到 Laravel 服務容器
 */
class TokenServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     *
     * @return void
     */
    public function register(): void
    {
        // 註冊 TokenValidator 介面和實作
        $this->app->bind(TokenValidatorInterface::class, TokenValidator::class);

        // 註冊 TokenManager 介面和實作
        $this->app->bind(TokenManagerInterface::class, TokenManager::class);

        // 註冊 TokenService 為單例
        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(
                $app->make(TokenValidatorInterface::class),
                $app->make(TokenManagerInterface::class)
            );
        });
    }

    /**
     * 啟動服務
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * 取得提供者提供的服務
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            TokenValidatorInterface::class,
            TokenManagerInterface::class,
            TokenService::class,
        ];
    }
}