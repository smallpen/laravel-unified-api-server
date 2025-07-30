<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 測試佇列任務
 * 用於驗證佇列系統是否正常運作
 */
class TestQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $message;

    /**
     * 建立新的任務實例
     *
     * @param string $message 測試訊息
     */
    public function __construct(string $message = '測試佇列任務')
    {
        $this->message = $message;
    }

    /**
     * 執行任務
     */
    public function handle(): void
    {
        Log::info('佇列任務執行成功', [
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString(),
            'job_id' => $this->job->getJobId(),
        ]);

        // 模擬一些處理時間
        sleep(2);

        Log::info('佇列任務處理完成', [
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * 任務失敗時的處理
     *
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('佇列任務執行失敗', [
            'message' => $this->message,
            'error' => $exception->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}