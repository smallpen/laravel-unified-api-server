<?php

namespace App\Providers;

use App\Events\ActionRegistryUpdated;
use App\Listeners\ClearDocumentationCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * 事件服務提供者
 * 
 * 註冊應用程式的事件監聽器
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * 應用程式的事件監聽器對應
     * 
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ActionRegistryUpdated::class => [
            ClearDocumentationCache::class,
        ],
    ];

    /**
     * 註冊任何事件
     */
    public function boot(): void
    {
        //
    }

    /**
     * 判斷事件和監聽器是否應該自動發現
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}