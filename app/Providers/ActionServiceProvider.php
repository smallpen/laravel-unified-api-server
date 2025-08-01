<?php

namespace App\Providers;

use App\Services\ActionRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * Action服務提供者
 * 
 * 負責註冊ActionRegistry服務並執行Action自動發現
 */
class ActionServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 註冊ActionRegistry為單例服務
        $this->app->singleton(ActionRegistry::class, function ($app) {
            return new ActionRegistry();
        });

        // 建立別名以便更容易存取
        $this->app->alias(ActionRegistry::class, 'action.registry');
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        // 只在非健康檢查的情況下執行自動發現
        if ($this->shouldPerformAutoDiscovery()) {
            $this->performAutoDiscovery();
        }
    }

    /**
     * 判斷是否應該執行自動發現
     */
    protected function shouldPerformAutoDiscovery(): bool
    {
        // 如果是透過 tinker 執行的健康檢查，跳過自動發現
        if ($this->app->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? '';
            if ($command === 'tinker') {
                return false;
            }
        }

        // 檢查是否已經執行過自動發現（使用快取避免重複執行）
        $cacheKey = 'action_registry_discovered';
        if (cache()->has($cacheKey)) {
            return false;
        }

        // 標記已執行，快取 5 分鐘
        cache()->put($cacheKey, true, 300);
        
        return true;
    }

    /**
     * 執行Action自動發現
     */
    protected function performAutoDiscovery(): void
    {
        try {
            /** @var ActionRegistry $registry */
            $registry = $this->app->make(ActionRegistry::class);
            
            // 執行自動發現
            $registry->autoDiscoverActions();
            
            // 記錄統計資訊
            $stats = $registry->getStatistics();
            Log::info('Action自動發現完成', $stats);
            
        } catch (\Exception $e) {
            Log::error('Action自動發現失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 取得提供的服務
     * 
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            ActionRegistry::class,
            'action.registry',
        ];
    }
}