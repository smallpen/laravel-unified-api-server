<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\ApiToken;
use Carbon\Carbon;

/**
 * Token 管理器介面
 * 
 * 定義 Token 生成、撤銷和管理的標準方法
 */
interface TokenManagerInterface
{
    /**
     * 為使用者建立新的 API Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @param array $permissions 權限陣列
     * @param \Carbon\Carbon|null $expiresAt 過期時間
     * @return array 包含 token 字串和 ApiToken 模型的陣列
     */
    public function createToken(User $user, string $name, array $permissions = [], ?Carbon $expiresAt = null): array;

    /**
     * 撤銷指定的 Token
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示撤銷成功，false 表示撤銷失敗
     */
    public function revokeToken(string $token): bool;

    /**
     * 撤銷使用者的所有 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return int 撤銷的 Token 數量
     */
    public function revokeAllUserTokens(User $user): int;

    /**
     * 撤銷指定名稱的 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @return int 撤銷的 Token 數量
     */
    public function revokeTokensByName(User $user, string $name): int;

    /**
     * 取得使用者的所有有效 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTokens(User $user);

    /**
     * 清理過期的 Token
     *
     * @return int 清理的 Token 數量
     */
    public function cleanupExpiredTokens(): int;

    /**
     * 檢查 Token 是否存在且有效
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示 Token 有效，false 表示無效
     */
    public function isTokenValid(string $token): bool;

    /**
     * 取得 Token 的詳細資訊
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\ApiToken|null Token 模型實例或 null
     */
    public function getTokenInfo(string $token): ?ApiToken;
}