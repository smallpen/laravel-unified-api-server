<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Token 驗證器介面
 * 
 * 定義 Token 驗證和管理的標準方法
 */
interface TokenValidatorInterface
{
    /**
     * 驗證 Token 並回傳對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null 驗證成功回傳使用者，失敗回傳 null
     */
    public function validate(string $token): ?User;

    /**
     * 檢查 Token 是否已過期
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示已過期，false 表示未過期
     */
    public function isExpired(string $token): bool;

    /**
     * 從 Token 取得對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null 成功回傳使用者，失敗回傳 null
     */
    public function getUserFromToken(string $token): ?User;

    /**
     * 檢查 Token 是否有特定權限
     *
     * @param string $token 原始 Token 字串
     * @param string $permission 權限名稱
     * @return bool true 表示有權限，false 表示無權限
     */
    public function hasPermission(string $token, string $permission): bool;

    /**
     * 撤銷指定的 Token
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示撤銷成功，false 表示撤銷失敗
     */
    public function revokeToken(string $token): bool;

    /**
     * 更新 Token 的最後使用時間
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示更新成功，false 表示更新失敗
     */
    public function updateLastUsed(string $token): bool;
}