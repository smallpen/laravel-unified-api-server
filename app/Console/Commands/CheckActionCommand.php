<?php

namespace App\Console\Commands;

use App\Services\ActionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 檢查Action狀態指令
 * 
 * 檢查特定Action的註冊狀態和相關資訊
 */
class CheckActionCommand extends Command
{
    /**
     * 指令名稱和參數
     *
     * @var string
     */
    protected $signature = 'action:check 
                            {action? : Action類型或類別名稱}
                            {--class= : 指定要檢查的Action類別}
                            {--file= : 指定要檢查的Action檔案路徑}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '檢查Action的註冊狀態和相關資訊';

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
        $action = $this->argument('action');
        $className = $this->option('class');
        $filePath = $this->option('file');

        if (!$action && !$className && !$filePath) {
            $this->error('請指定要檢查的Action類型、類別名稱或檔案路徑');
            return Command::FAILURE;
        }

        try {
            if ($filePath) {
                return $this->checkActionFile($filePath);
            } elseif ($className) {
                return $this->checkActionClass($className);
            } else {
                return $this->checkActionType($action);
            }
        } catch (\Exception $e) {
            $this->error('檢查失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 檢查Action類型
     *
     * @param string $actionType
     * @return int
     */
    protected function checkActionType(string $actionType): int
    {
        $this->info("檢查Action類型: {$actionType}");
        $this->line('');

        if ($this->actionRegistry->hasAction($actionType)) {
            $this->info('✓ Action已註冊');
            
            try {
                $instance = $this->actionRegistry->resolve($actionType);
                $this->displayActionInfo($instance, $actionType);
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error('✗ Action註冊但無法實例化：' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->warn('✗ Action未註冊');
            $this->line('');
            $this->info('可能的原因：');
            $this->line('1. Action類別不存在');
            $this->line('2. Action類別未實作ActionInterface');
            $this->line('3. Action類別有語法錯誤');
            $this->line('4. 需要執行 php artisan action:refresh');
            
            return Command::FAILURE;
        }
    }

    /**
     * 檢查Action類別
     *
     * @param string $className
     * @return int
     */
    protected function checkActionClass(string $className): int
    {
        $this->info("檢查Action類別: {$className}");
        $this->line('');

        if (!class_exists($className)) {
            $this->error('✗ 類別不存在');
            return Command::FAILURE;
        }

        $this->info('✓ 類別存在');

        try {
            $reflection = new \ReflectionClass($className);
            
            // 檢查是否實作ActionInterface
            if (!$reflection->implementsInterface(\App\Contracts\ActionInterface::class)) {
                $this->error('✗ 類別未實作ActionInterface');
                return Command::FAILURE;
            }
            $this->info('✓ 已實作ActionInterface');

            // 檢查是否為抽象類別
            if ($reflection->isAbstract()) {
                $this->error('✗ 類別為抽象類別');
                return Command::FAILURE;
            }
            $this->info('✓ 非抽象類別');

            // 檢查是否可實例化
            if (!$reflection->isInstantiable()) {
                $this->error('✗ 類別無法實例化');
                return Command::FAILURE;
            }
            $this->info('✓ 可以實例化');

            // 嘗試建立實例
            $instance = new $className();
            $actionType = $instance->getActionType();
            
            $this->line('');
            $this->info("Action類型: {$actionType}");

            // 檢查是否已註冊
            if ($this->actionRegistry->hasAction($actionType)) {
                $this->info('✓ 已在註冊系統中');
            } else {
                $this->warn('✗ 未在註冊系統中');
                $this->line('執行 php artisan action:refresh 來註冊此Action');
            }

            $this->displayActionInfo($instance, $actionType);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ 檢查過程發生錯誤：' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 檢查Action檔案
     *
     * @param string $filePath
     * @return int
     */
    protected function checkActionFile(string $filePath): int
    {
        $this->info("檢查Action檔案: {$filePath}");
        $this->line('');

        // 轉換為絕對路徑
        if (!str_starts_with($filePath, '/')) {
            $filePath = base_path($filePath);
        }

        if (!File::exists($filePath)) {
            $this->error('✗ 檔案不存在');
            return Command::FAILURE;
        }
        $this->info('✓ 檔案存在');

        // 嘗試從檔案路徑推斷類別名稱
        $className = $this->guessClassNameFromFile($filePath);
        
        if (!$className) {
            $this->error('✗ 無法從檔案路徑推斷類別名稱');
            return Command::FAILURE;
        }

        $this->info("推斷的類別名稱: {$className}");
        
        return $this->checkActionClass($className);
    }

    /**
     * 從檔案路徑推斷類別名稱
     *
     * @param string $filePath
     * @return string|null
     */
    protected function guessClassNameFromFile(string $filePath): ?string
    {
        // 取得相對於app目錄的路徑
        $appPath = app_path();
        if (!str_starts_with($filePath, $appPath)) {
            return null;
        }

        $relativePath = str_replace($appPath . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace('.php', '', $relativePath);
        $namespacePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        
        return 'App\\' . $namespacePath;
    }

    /**
     * 顯示Action詳細資訊
     *
     * @param \App\Contracts\ActionInterface $instance
     * @param string $actionType
     */
    protected function displayActionInfo($instance, string $actionType): void
    {
        $this->line('');
        $this->info('=== Action詳細資訊 ===');
        
        $this->table(
            ['屬性', '值'],
            [
                ['Action類型', $actionType],
                ['類別名稱', get_class($instance)],
                ['版本', $instance->getVersion()],
                ['狀態', $instance->isEnabled() ? '啟用' : '停用'],
                ['所需權限', empty($instance->getRequiredPermissions()) ? '無' : implode(', ', $instance->getRequiredPermissions())],
            ]
        );

        // 顯示文件資訊
        try {
            $documentation = $instance->getDocumentation();
            if (!empty($documentation['description']) && $documentation['description'] !== 'TODO: 請在此處添加Action的詳細描述') {
                $this->line('');
                $this->info('描述：' . $documentation['description']);
            }
        } catch (\Exception $e) {
            // 忽略文件取得錯誤
        }
    }
}