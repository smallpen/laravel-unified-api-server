<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActionPermission;
use App\Services\ActionRegistry;
use App\Contracts\PermissionCheckerInterface;

/**
 * Action權限管理指令
 * 
 * 提供命令列介面來管理Action層級的權限配置
 */
class ManageActionPermissionsCommand extends Command
{
    /**
     * 指令名稱和簽名
     *
     * @var string
     */
    protected $signature = 'action:permissions 
                            {operation : 操作類型 (list|show|set|remove|sync)}
                            {action_type? : Action類型 (用於show、set、remove操作)}
                            {--permissions=* : 權限清單 (用於set操作)}
                            {--description= : 權限描述 (用於set操作)}
                            {--active=true : 是否啟用 (用於set操作)}
                            {--file= : 同步用的JSON檔案路徑 (用於sync操作)}';

    /**
     * 指令描述
     *
     * @var string
     */
    protected $description = '管理Action層級的權限配置';

    /**
     * Action註冊系統
     * 
     * @var ActionRegistry
     */
    protected ActionRegistry $actionRegistry;

    /**
     * 權限檢查器
     * 
     * @var PermissionCheckerInterface
     */
    protected PermissionCheckerInterface $permissionChecker;

    /**
     * 建構函式
     * 
     * @param ActionRegistry $actionRegistry
     * @param PermissionCheckerInterface $permissionChecker
     */
    public function __construct(ActionRegistry $actionRegistry, PermissionCheckerInterface $permissionChecker)
    {
        parent::__construct();
        $this->actionRegistry = $actionRegistry;
        $this->permissionChecker = $permissionChecker;
    }

    /**
     * 執行指令
     *
     * @return int
     */
    public function handle(): int
    {
        $operation = $this->argument('operation');

        try {
            switch ($operation) {
                case 'list':
                    return $this->listPermissions();
                case 'show':
                    return $this->showPermission();
                case 'set':
                    return $this->setPermission();
                case 'remove':
                    return $this->removePermission();
                case 'sync':
                    return $this->syncPermissions();
                default:
                    $this->error("不支援的操作: {$operation}");
                    $this->info('支援的操作: list, show, set, remove, sync');
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("執行失敗: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * 列出所有Action權限配置
     * 
     * @return int
     */
    protected function listPermissions(): int
    {
        $permissions = $this->permissionChecker->getAllActionPermissions();

        if (empty($permissions)) {
            $this->info('目前沒有任何Action權限配置');
            return 0;
        }

        $this->info('Action權限配置清單:');
        $this->line('');

        $headers = ['Action類型', '所需權限', '狀態', '描述', '更新時間'];
        $rows = [];

        foreach ($permissions as $actionType => $config) {
            $rows[] = [
                $actionType,
                implode(', ', $config['required_permissions']),
                $config['is_active'] ? '啟用' : '停用',
                $config['description'] ?? '-',
                $config['updated_at'] ?? '-',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * 顯示特定Action的權限配置
     * 
     * @return int
     */
    protected function showPermission(): int
    {
        $actionType = $this->argument('action_type');

        if (!$actionType) {
            $this->error('請指定Action類型');
            return 1;
        }

        $config = $this->permissionChecker->getActionPermissionConfig($actionType);

        if (!$config) {
            $this->warn("找不到Action '{$actionType}' 的權限配置");
            
            // 檢查Action是否存在
            if ($this->actionRegistry->hasAction($actionType)) {
                $action = $this->actionRegistry->resolve($actionType);
                $defaultPermissions = $action->getRequiredPermissions();
                
                $this->info('Action存在，使用預設權限配置:');
                $this->line("所需權限: " . (empty($defaultPermissions) ? '無' : implode(', ', $defaultPermissions)));
            } else {
                $this->error("Action '{$actionType}' 不存在");
            }
            
            return 1;
        }

        $this->info("Action權限配置: {$actionType}");
        $this->line('');
        $this->line("所需權限: " . implode(', ', $config['required_permissions']));
        $this->line("狀態: " . ($config['is_active'] ? '啟用' : '停用'));
        $this->line("描述: " . ($config['description'] ?? '-'));
        $this->line("建立時間: " . $config['created_at']);
        $this->line("更新時間: " . $config['updated_at']);

        return 0;
    }

    /**
     * 設定Action權限配置
     * 
     * @return int
     */
    protected function setPermission(): int
    {
        $actionType = $this->argument('action_type');

        if (!$actionType) {
            $this->error('請指定Action類型');
            return 1;
        }

        $permissions = $this->option('permissions');
        $description = $this->option('description');
        $active = $this->option('active') === 'true';

        if (empty($permissions)) {
            $this->error('請指定至少一個權限');
            return 1;
        }

        // 檢查Action是否存在
        if (!$this->actionRegistry->hasAction($actionType)) {
            if (!$this->confirm("Action '{$actionType}' 不存在，是否仍要建立權限配置?")) {
                return 1;
            }
        }

        $permissionConfig = $this->permissionChecker->setActionPermissions(
            $actionType,
            $permissions,
            $description
        );

        if (!$active) {
            $permissionConfig->deactivate();
            $permissionConfig->save();
        }

        $this->info("成功設定Action '{$actionType}' 的權限配置");
        $this->line("所需權限: " . implode(', ', $permissions));
        $this->line("狀態: " . ($active ? '啟用' : '停用'));
        if ($description) {
            $this->line("描述: {$description}");
        }

        return 0;
    }

    /**
     * 移除Action權限配置
     * 
     * @return int
     */
    protected function removePermission(): int
    {
        $actionType = $this->argument('action_type');

        if (!$actionType) {
            $this->error('請指定Action類型');
            return 1;
        }

        $config = $this->permissionChecker->getActionPermissionConfig($actionType);

        if (!$config) {
            $this->warn("找不到Action '{$actionType}' 的權限配置");
            return 1;
        }

        if (!$this->confirm("確定要移除Action '{$actionType}' 的權限配置嗎?")) {
            $this->info('操作已取消');
            return 0;
        }

        $result = $this->permissionChecker->removeActionPermissions($actionType);

        if ($result) {
            $this->info("成功移除Action '{$actionType}' 的權限配置");
        } else {
            $this->error("移除失敗");
            return 1;
        }

        return 0;
    }

    /**
     * 從檔案同步權限配置
     * 
     * @return int
     */
    protected function syncPermissions(): int
    {
        $filePath = $this->option('file');

        if (!$filePath) {
            $this->error('請指定同步檔案路徑 (--file)');
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("檔案不存在: {$filePath}");
            return 1;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON檔案格式錯誤: ' . json_last_error_msg());
            return 1;
        }

        if (!is_array($data)) {
            $this->error('JSON檔案內容必須是物件格式');
            return 1;
        }

        $this->info('開始同步權限配置...');
        $this->line('');

        $syncCount = $this->permissionChecker->syncActionPermissions($data);

        $this->info("同步完成，共處理 {$syncCount} 個Action權限配置");

        // 顯示同步結果
        $this->line('');
        $this->info('同步的配置:');
        foreach ($data as $actionType => $config) {
            $permissions = $config['permissions'] ?? [];
            $isActive = $config['is_active'] ?? true;
            $status = $isActive ? '啟用' : '停用';
            
            $this->line("- {$actionType}: [" . implode(', ', $permissions) . "] ({$status})");
        }

        return 0;
    }
}