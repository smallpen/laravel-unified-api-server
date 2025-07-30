<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 範例佇列任務
 * 
 * 這個類別示範如何建立和使用佇列任務
 */
class ExampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任務資料
     */
    protected $data;

    /**
     * 建立新的任務實例
     *
     * @param mixed $data 要處理的資料
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * 執行任務
     *
     * @return void
     */
    public function handle()
    {
        // 記錄任務開始執行
        Log::info('範例佇列任務開始執行', [
            'data' => $this->data,
            'job_id' => $this->job->getJobId()
        ]);

        // 模擬一些處理時間
        sleep(2);

        // 在這裡執行實際的業務邏輯
        // 例如：發送郵件、處理圖片、生成報告等

        // 記錄任務完成
        Log::info('範例佇列任務執行完成', [
            'data' => $this->data,
            'job_id' => $this->job->getJobId()
        ]);
    }

    /**
     * 任務失敗時的處理
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('範例佇列任務執行失敗', [
            'data' => $this->data,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}