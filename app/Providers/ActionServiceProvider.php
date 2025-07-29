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
        // 在應用程式啟動時執行Action自動發現
        if ($this->app->environment('local', 'testing')) {
            // 在開發和測試環境中每次都執行自動發現
            $this->performAutoDiscovery();
        } else {
            // 在生產環境中可以考慮快取機制或手動觸發
            $this->performAutoDiscovery();
        }
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