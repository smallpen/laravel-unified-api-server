<?php

namespace App\Console\Commands;

use App\Services\ActionRegistry;
use Illuminate\Console\Command;

/**
 * Action清單管理指令
 * 
 * 提供Action的查看、統計和管理功能
 */
class ActionListCommand extends Command
{
    /**
     * 指令名稱和參數
     *
     * @var string
     */
    protected $signature = 'action:list 
                            {--stats : 顯示統計資訊}
                            {--details : 顯示詳細資訊}
                            {--enabled : 只顯示啟用的Action}
                            {--disabled : 只顯示停用的Action}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '顯示已註冊的Action清單和統計資訊';

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
        $this->info('Action註冊系統管理');
        $this->line('');

        // 顯示統計資訊
        if ($this->option('stats')) {
            $this->displayStatistics();
            return Command::SUCCESS;
        }

        // 顯示Action清單
        $this->displayActionList();

        return Command::SUCCESS;
    }

    /**
     * 顯示統計資訊
     */
    protected function displayStatistics(): void
    {
        $stats = $this->actionRegistry->getStatistics();

        $this->info('=== Action統計資訊 ===');
        $this->line('');

        $this->table(
            ['項目', '數量'],
            [
                ['總Action數量', $stats['total_actions']],
                ['啟用的Action', $stats['enabled_actions']],
                ['停用的Action', $stats['disabled_actions']],
                ['快取的實例', $stats['cached_instances']],
            ]
        );

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

    /**
     * 顯示Action清單
     */
    protected function displayActionList(): void
    {
        $actions = $this->actionRegistry->getAllActions();

        if (empty($actions)) {
            $this->warn('沒有找到已註冊的Action');
            return;
        }

        $this->info('=== 已註冊的Action清單 ===');
        $this->line('');

        $tableData = [];
        $enabledFilter = $this->option('enabled');
        $disabledFilter = $this->option('disabled');
        $showDetails = $this->option('details');

        foreach ($actions as $actionType => $actionClass) {
            try {
                $action = $this->actionRegistry->resolve($actionType);
                $isEnabled = $action->isEnabled();

                // 套用過濾器
                if ($enabledFilter && !$isEnabled) {
                    continue;
                }
                if ($disabledFilter && $isEnabled) {
                    continue;
                }

                $row = [
                    'Action類型' => $actionType,
                    '類別名稱' => class_basename($actionClass),
                    '版本' => $action->getVersion(),
                    '狀態' => $isEnabled ? '<info>啟用</info>' : '<comment>停用</comment>',
                ];

                if ($showDetails) {
                    $permissions = $action->getRequiredPermissions();
                    $row['權限'] = empty($permissions) ? '無' : implode(', ', $permissions);
                }

                $tableData[] = $row;

            } catch (\Exception $e) {
                $tableData[] = [
                    'Action類型' => $actionType,
                    '類別名稱' => class_basename($actionClass),
                    '版本' => '<error>錯誤</error>',
                    '狀態' => '<error>無法載入</error>',
                ];

                if ($showDetails) {
                    $tableData[count($tableData) - 1]['權限'] = '<error>' . $e->getMessage() . '</error>';
                }
            }
        }

        if (empty($tableData)) {
            $this->warn('沒有符合條件的Action');
            return;
        }

        $headers = array_keys($tableData[0]);
        $this->table($headers, $tableData);

        $this->line('');
        $this->info('總計：' . count($tableData) . ' 個Action');
    }
}
