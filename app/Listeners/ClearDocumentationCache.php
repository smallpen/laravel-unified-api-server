<?php

namespace App\Listeners;

use App\Events\ActionRegistryUpdated;
use App\Services\DocumentationGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * 清除文件快取監聽器
 * 
 * 監聽Action註冊系統更新事件，自動清除文件快取
 * 確保API文件始終保持最新狀態
 */
class ClearDocumentationCache implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * 文件生成器實例
     * 
     * @var \App\Services\DocumentationGenerator
     */
    protected DocumentationGenerator $documentationGenerator;

    /**
     * 建構子
     * 
     * @param \App\Services\DocumentationGenerator $documentationGenerator
     */
    public function __construct(DocumentationGenerator $documentationGenerator)
    {
        $this->documentationGenerator = $documentationGenerator;
    }

    /**
     * 處理事件
     * 
     * @param \App\Events\ActionRegistryUpdated $event
     * @return void
     */
    public function handle(ActionRegistryUpdated $event): void
    {
        try {
            // 清除文件快取
            $this->documentationGenerator->clearCache();

            Log::info('文件快取已清除', [
                'update_type' => $event->getUpdateType(),
                'affected_actions' => $event->getAffectedActions(),
                'timestamp' => $event->getTimestamp()->toISOString(),
            ]);

            // 如果是大量更新（如自動發現），預先生成文件
            if ($event->getUpdateType() === 'discover' && count($event->getAffectedActions()) > 5) {
                $this->preGenerateDocumentation();
            }

        } catch (\Exception $e) {
            Log::error('清除文件快取時發生錯誤', [
                'error' => $e->getMessage(),
                'update_type' => $event->getUpdateType(),
                'affected_actions' => $event->getAffectedActions(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 重新拋出異常以便上層處理
            throw $e;
        }
    }

    /**
     * 預先生成文件
     * 
     * 在背景預先生成文件以提升使用者體驗
     * 
     * @return void
     */
    protected function preGenerateDocumentation(): void
    {
        try {
            $startTime = microtime(true);
            $this->documentationGenerator->generateDocumentation();
            $endTime = microtime(true);

            Log::info('文件預先生成完成', [
                'generation_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
            ]);

        } catch (\Exception $e) {
            Log::warning('預先生成文件時發生錯誤', [
                'error' => $e->getMessage(),
            ]);
            // 預先生成失敗不影響主要功能，只記錄警告
        }
    }

    /**
     * 處理失敗的任務
     * 
     * @param \App\Events\ActionRegistryUpdated $event
     * @param \Throwable $exception
     * @return void
     */
    public function failed(ActionRegistryUpdated $event, \Throwable $exception): void
    {
        Log::error('清除文件快取任務失敗', [
            'update_type' => $event->getUpdateType(),
            'affected_actions' => $event->getAffectedActions(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}