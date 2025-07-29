<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PermissionCheckerInterface;
use App\Services\PermissionChecker;

/**
 * 權限服務提供者
 * 
 * 註冊權限相關的服務和綁定
 */
class PermissionServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 綁定權限檢查服務
        $this->app->bind(PermissionCheckerInterface::class, PermissionChecker::class);

        // 註冊為單例服務
        $this->app->singleton('permission.checker', function ($app) {
            return $app->make(PermissionChecker::class);
        });
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        //
    }

    /**
     * 取得提供者提供的服務
     * 
     * @return array
     */
    public function provides(): array
    {
        return [
            PermissionCheckerInterface::class,
            'permission.checker',
        ];
    }
}
