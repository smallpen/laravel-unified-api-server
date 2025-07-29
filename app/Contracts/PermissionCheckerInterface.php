<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\ActionPermission;

/**
 * 權限檢查服務介面
 * 
 * 定義權限檢查相關的標準方法
 */
interface PermissionCheckerInterface
{
    /**
     * 檢查使用者是否有權限執行指定的Action
     * 
     * @param \App\Models\User $user 使用者
     * @param \App\Contracts\ActionInterface $action Action實例
     * @return bool 是否有權限
     */
    public function canExecuteAction(User $user, ActionInterface $action): bool;

    /**
     * 檢查使用者是否具有指定的權限
     * 
     * @param \App\Models\User $user 使用者
     * @param array $requiredPermissions 所需權限陣列
     * @return bool 是否具有權限
     */
    public function userHasPermissions(User $user, array $requiredPermissions): bool;

    /**
     * 取得Action的權限配置
     * 
     * @param string $actionType Action類型
     * @return array|null 權限配置
     */
    public function getActionPermissionConfig(string $actionType): ?array;

    /**
     * 設定Action的權限配置
     * 
     * @param string $actionType Action類型
     * @param array $permissions 權限陣列
     * @param string|null $description 描述
     * @return \App\Models\ActionPermission 權限配置實例
     */
    public function setActionPermissions(string $actionType, array $permissions, ?string $description = null): ActionPermission;

    /**
     * 移除Action的權限配置
     * 
     * @param string $actionType Action類型
     * @return bool 是否成功移除
     */
    public function removeActionPermissions(string $actionType): bool;

    /**
     * 取得所有Action的權限配置
     * 
     * @return array 權限配置陣列
     */
    public function getAllActionPermissions(): array;

    /**
     * 批量同步Action權限配置
     * 
     * @param array $actionPermissions Action權限配置陣列
     * @return int 同步的配置數量
     */
    public function syncActionPermissions(array $actionPermissions): int;

    /**
     * 檢查並記錄權限拒絕
     * 
     * @param \App\Models\User $user 使用者
     * @param string $actionType Action類型
     * @param array $requiredPermissions 所需權限
     * @return void
     */
    public function logPermissionDenied(User $user, string $actionType, array $requiredPermissions): void;
}