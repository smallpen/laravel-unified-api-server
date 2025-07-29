<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\DocumentationGeneratorInterface;
use App\Services\DocumentationGenerator;
use App\Services\ActionRegistry;

/**
 * 文件生成服務提供者
 * 
 * 註冊API文件生成相關的服務
 */
class DocumentationServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 註冊DocumentationGenerator為單例
        $this->app->singleton(DocumentationGeneratorInterface::class, function ($app) {
            return new DocumentationGenerator($app->make(ActionRegistry::class));
        });

        // 註冊別名
        $this->app->alias(DocumentationGeneratorInterface::class, 'documentation.generator');
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        // 可以在這裡添加啟動邏輯，例如發布配置檔案等
    }

    /**
     * 取得提供的服務
     * 
     * @return array
     */
    public function provides(): array
    {
        return [
            DocumentationGeneratorInterface::class,
            'documentation.generator',
        ];
    }
}