<?php

namespace App\Services;

use App\Models\ActionPermission;
use App\Models\User;
use App\Contracts\ActionInterface;
use App\Contracts\PermissionCheckerInterface;
use Illuminate\Support\Facades\Log;

/**
 * 權限檢查服務
 * 
 * 負責檢查使用者是否具有執行特定Action的權限
 */
class PermissionChecker implements PermissionCheckerInterface
{
    /**
     * 檢查使用者是否有權限執行指定的Action
     * 
     * @param \App\Models\User $user 使用者
     * @param \App\Contracts\ActionInterface $action Action實例
     * @return bool 是否有權限
     */
    public function canExecuteAction(User $user, ActionInterface $action): bool
    {
        $actionType = $action->getActionType();

        // 記錄權限檢查開始
        Log::debug('開始權限檢查', [
            'user_id' => $user->id,
            'action_type' => $actionType,
        ]);

        // 檢查Action是否啟用
        if (!$action->isEnabled()) {
            Log::warning('Action已停用', [
                'user_id' => $user->id,
                'action_type' => $actionType,
            ]);
            return false;
        }

        // 從資料庫取得權限配置
        $permissionConfig = ActionPermission::findByActionType($actionType);
        
        // 如果沒有配置，使用Action本身定義的權限
        $requiredPermissions = $permissionConfig 
            ? $permissionConfig->getPermissions()
            : $action->getRequiredPermissions();

        // 如果沒有權限要求，允許執行
        if (empty($requiredPermissions)) {
            Log::debug('Action無權限要求，允許執行', [
                'user_id' => $user->id,
                'action_type' => $actionType,
            ]);
            return true;
        }

        // 檢查使用者是否具有所需權限
        $hasPermission = $this->userHasPermissions($user, $requiredPermissions);

        Log::info('權限檢查完成', [
            'user_id' => $user->id,
            'action_type' => $actionType,
            'required_permissions' => $requiredPermissions,
            'has_permission' => $hasPermission,
        ]);

        return $hasPermission;
    }

    /**
     * 檢查使用者是否具有指定的權限
     * 
     * @param \App\Models\User $user 使用者
     * @param array $requiredPermissions 所需權限陣列
     * @return bool 是否具有權限
     */
    public function userHasPermissions(User $user, array $requiredPermissions): bool
    {
        // 取得使用者權限
        $userPermissions = $this->getUserPermissions($user);

        // 檢查是否具有所有必要權限
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                Log::debug('使用者缺少權限', [
                    'user_id' => $user->id,
                    'missing_permission' => $permission,
                    'user_permissions' => $userPermissions,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * 取得使用者的權限清單
     * 
     * 這是一個簡化的實作，實際專案中可能需要整合角色權限系統
     * 
     * @param \App\Models\User $user 使用者
     * @return array 權限陣列
     */
    protected function getUserPermissions(User $user): array
    {
        // 這裡是一個簡化的實作
        // 實際專案中，您可能需要：
        // 1. 從使用者角色表查詢權限
        // 2. 從權限快取中取得權限
        // 3. 整合第三方權限系統

        // 暫時從使用者模型的permissions屬性取得權限
        if (method_exists($user, 'getPermissions')) {
            return $user->getPermissions();
        }

        // 如果使用者模型有permissions屬性
        if (isset($user->permissions) && is_array($user->permissions)) {
            return $user->permissions;
        }

        // 預設權限（可以根據需求調整）
        $defaultPermissions = [
            'user.read',
            'user.update',
            'user.change_password',
        ];

        // 如果是管理員，給予更多權限
        if ($this->isAdmin($user)) {
            $defaultPermissions = array_merge($defaultPermissions, [
                'user.list',
                'system.read',
                'system.server_status',
                'admin.read',
                'admin.write',
            ]);
        }

        return $defaultPermissions;
    }

    /**
     * 檢查使用者是否為管理員
     * 
     * @param \App\Models\User $user 使用者
     * @return bool 是否為管理員
     */
    protected function isAdmin(User $user): bool
    {
        // 這裡是一個簡化的實作
        // 實際專案中，您可能需要檢查：
        // 1. 使用者角色
        // 2. 特定的權限標記
        // 3. 使用者群組

        // 暫時使用email判斷（僅供測試）
        if (method_exists($user, 'isAdmin')) {
            return $user->isAdmin();
        }

        // 檢查是否有admin屬性
        if (isset($user->is_admin)) {
            return (bool) $user->is_admin;
        }

        // 預設所有使用者都不是管理員
        return false;
    }

    /**
     * 取得Action的權限配置
     * 
     * @param string $actionType Action類型
     * @return array|null 權限配置
     */
    public function getActionPermissionConfig(string $actionType): ?array
    {
        $permissionConfig = ActionPermission::findByActionType($actionType);

        if (!$permissionConfig) {
            return null;
        }

        return [
            'action_type' => $permissionConfig->action_type,
            'required_permissions' => $permissionConfig->getPermissions(),
            'is_active' => $permissionConfig->isActive(),
            'description' => $permissionConfig->description,
            'created_at' => $permissionConfig->created_at,
            'updated_at' => $permissionConfig->updated_at,
        ];
    }

    /**
     * 設定Action的權限配置
     * 
     * @param string $actionType Action類型
     * @param array $permissions 權限陣列
     * @param string|null $description 描述
     * @return \App\Models\ActionPermission 權限配置實例
     */
    public function setActionPermissions(string $actionType, array $permissions, ?string $description = null): ActionPermission
    {
        $permissionConfig = ActionPermission::createOrUpdate($actionType, $permissions, $description);

        Log::info('Action權限配置已更新', [
            'action_type' => $actionType,
            'permissions' => $permissions,
            'description' => $description,
        ]);

        return $permissionConfig;
    }

    /**
     * 移除Action的權限配置
     * 
     * @param string $actionType Action類型
     * @return bool 是否成功移除
     */
    public function removeActionPermissions(string $actionType): bool
    {
        $deleted = ActionPermission::where('action_type', $actionType)->delete();

        if ($deleted > 0) {
            Log::info('Action權限配置已移除', [
                'action_type' => $actionType,
            ]);
        }

        return $deleted > 0;
    }

    /**
     * 取得所有Action的權限配置
     * 
     * @return array 權限配置陣列
     */
    public function getAllActionPermissions(): array
    {
        return ActionPermission::getAllActive()
            ->map(function ($config) {
                return [
                    'action_type' => $config->action_type,
                    'required_permissions' => $config->getPermissions(),
                    'is_active' => $config->isActive(),
                    'description' => $config->description,
                    'created_at' => $config->created_at,
                    'updated_at' => $config->updated_at,
                ];
            })
            ->keyBy('action_type')
            ->toArray();
    }

    /**
     * 批量同步Action權限配置
     * 
     * @param array $actionPermissions Action權限配置陣列
     * @return int 同步的配置數量
     */
    public function syncActionPermissions(array $actionPermissions): int
    {
        $syncCount = ActionPermission::syncPermissions($actionPermissions);

        Log::info('Action權限配置批量同步完成', [
            'sync_count' => $syncCount,
        ]);

        return $syncCount;
    }

    /**
     * 檢查並記錄權限拒絕
     * 
     * @param \App\Models\User $user 使用者
     * @param string $actionType Action類型
     * @param array $requiredPermissions 所需權限
     * @return void
     */
    public function logPermissionDenied(User $user, string $actionType, array $requiredPermissions): void
    {
        Log::warning('權限檢查失敗', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'action_type' => $actionType,
            'required_permissions' => $requiredPermissions,
            'user_permissions' => $this->getUserPermissions($user),
            'timestamp' => now()->toISOString(),
        ]);
    }
}