<?php

namespace App\Console\Commands;

use App\Jobs\TestQueueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

/**
 * 測試佇列系統的 Artisan 指令
 */
class TestQueue extends Command
{
    /**
     * 指令名稱和簽名
     *
     * @var string
     */
    protected $signature = 'queue:test {--count=1 : 要建立的測試任務數量}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '建立測試佇列任務來驗證佇列系統是否正常運作';

    /**
     * 執行指令
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');

        $this->info("正在建立 {$count} 個測試佇列任務...");

        for ($i = 1; $i <= $count; $i++) {
            TestQueueJob::dispatch("測試任務 #{$i}");
            $this->line("✓ 已建立測試任務 #{$i}");
        }

        $this->info('');
        $this->info('測試任務已加入佇列！');
        $this->info('您可以使用以下指令監控佇列狀態：');
        $this->line('  php artisan queue:work --verbose');
        $this->line('  php artisan queue:monitor');
        
        // 顯示佇列統計
        $this->info('');
        $this->info('目前佇列統計：');
        
        try {
            $size = Queue::size();
            $this->line("  待處理任務數量: {$size}");
        } catch (\Exception $e) {
            $this->error("  無法取得佇列大小: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }
}