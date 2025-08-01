<?php

namespace App\Console\Commands;

use App\Services\ActionRegistry;
use Illuminate\Console\Command;

/**
 * 重新整理Action註冊系統指令
 * 
 * 手動觸發Action自動發現和註冊
 */
class RefreshActionRegistryCommand extends Command
{
    /**
     * 指令名稱和參數
     *
     * @var string
     */
    protected $signature = 'action:refresh 
                            {--clear-cache : 清除Action實例快取}
                            {--force : 強制重新掃描，忽略快取}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '重新整理Action註冊系統，手動觸發自動發現';

    /**
     * Action註冊系統
     *
     * @var ActionRegistry
     */
    protected ActionRegistry $actionRegistry;

    /**
     * 建構函式
     *
     * @param ActionRegistry $actionRegistry
     */
    public function __construct(ActionRegistry $actionRegistry)
    {
        parent::__construct();
        $this->actionRegistry = $actionRegistry;
    }

    /**
     * 執行指令
     */
    public function handle(): int
    {
        $this->info('開始重新整理Action註冊系統...');
        $this->line('');

        // 清除快取（如果指定）
        if ($this->option('clear-cache')) {
            $this->actionRegistry->clearCache();
            $this->info('✓ Action實例快取已清除');
        }

        // 強制清除自動發現快取
        if ($this->option('force')) {
            cache()->forget('action_registry_discovered');
            $this->info('✓ 自動發現快取已清除');
        }

        // 顯示重新整理前的統計
        $beforeStats = $this->actionRegistry->getStatistics();
        $this->info("重新整理前：{$beforeStats['total_actions']} 個Action已註冊");

        try {
            // 執行自動發現
            $this->actionRegistry->autoDiscoverActions();
            
            // 顯示重新整理後的統計
            $afterStats = $this->actionRegistry->getStatistics();
            $newActions = $afterStats['total_actions'] - $beforeStats['total_actions'];
            
            $this->line('');
            $this->info('Action註冊系統重新整理完成！');
            $this->line('');
            
            // 顯示統計資訊
            $this->displayStatistics($afterStats, $newActions);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('重新整理失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 顯示統計資訊
     *
     * @param array $stats 統計資料
     * @param int $newActions 新發現的Action數量
     */
    protected function displayStatistics(array $stats, int $newActions): void
    {
        $this->table(
            ['項目', '數量'],
            [
                ['總Action數量', $stats['total_actions']],
                ['新發現的Action', $newActions > 0 ? "<info>{$newActions}</info>" : '0'],
                ['啟用的Action', $stats['enabled_actions']],
                ['停用的Action', $stats['disabled_actions']],
                ['快取的實例', $stats['cached_instances']],
            ]
        );

        if ($newActions > 0) {
            $this->line('');
            $this->info("發現 {$newActions} 個新的Action並已自動註冊");
        }

        // 顯示版本分佈
        if (!empty($stats['version_distribution'])) {
            $this->line('');
            $this->info('版本分佈：');
            
            $versionData = [];
            foreach ($stats['version_distribution'] as $version => $count) {
                $versionData[] = [$version, $count];
            }
            
            $this->table(['版本', '數量'], $versionData);
        }
    }
}