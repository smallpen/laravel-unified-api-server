<?php

namespace App\Services;

use App\Contracts\TokenValidatorInterface;
use App\Contracts\TokenManagerInterface;
use App\Models\User;
use App\Models\ApiToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Token 統一服務
 * 
 * 整合 Token 驗證和管理功能，提供統一的 Token 操作介面
 */
class TokenService
{
    /**
     * Token 驗證器
     *
     * @var \App\Contracts\TokenValidatorInterface
     */
    protected TokenValidatorInterface $validator;

    /**
     * Token 管理器
     *
     * @var \App\Contracts\TokenManagerInterface
     */
    protected TokenManagerInterface $manager;

    /**
     * 建構函式
     *
     * @param \App\Contracts\TokenValidatorInterface $validator
     * @param \App\Contracts\TokenManagerInterface $manager
     */
    public function __construct(TokenValidatorInterface $validator, TokenManagerInterface $manager)
    {
        $this->validator = $validator;
        $this->manager = $manager;
    }

    // ========== 驗證相關方法 ==========

    /**
     * 驗證 Token 並回傳對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null
     */
    public function validateToken(string $token): ?User
    {
        return $this->validator->validate($token);
    }

    /**
     * 檢查 Token 是否已過期
     *
     * @param string $token 原始 Token 字串
     * @return bool
     */
    public function isTokenExpired(string $token): bool
    {
        return $this->validator->isExpired($token);
    }

    /**
     * 從 Token 取得對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null
     */
    public function getUserFromToken(string $token): ?User
    {
        return $this->validator->getUserFromToken($token);
    }

    /**
     * 檢查 Token 是否有特定權限
     *
     * @param string $token 原始 Token 字串
     * @param string $permission 權限名稱
     * @return bool
     */
    public function hasPermission(string $token, string $permission): bool
    {
        return $this->validator->hasPermission($token, $permission);
    }

    /**
     * 更新 Token 的最後使用時間
     *
     * @param string $token 原始 Token 字串
     * @return bool
     */
    public function updateTokenLastUsed(string $token): bool
    {
        return $this->validator->updateLastUsed($token);
    }

    // ========== 管理相關方法 ==========

    /**
     * 為使用者建立新的 API Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @param array $permissions 權限陣列
     * @param \Carbon\Carbon|null $expiresAt 過期時間
     * @return array 包含 token 字串和 ApiToken 模型的陣列
     */
    public function createToken(User $user, string $name, array $permissions = [], ?Carbon $expiresAt = null): array
    {
        return $this->manager->createToken($user, $name, $permissions, $expiresAt);
    }

    /**
     * 撤銷指定的 Token
     *
     * @param string $token 原始 Token 字串
     * @return bool
     */
    public function revokeToken(string $token): bool
    {
        return $this->manager->revokeToken($token);
    }

    /**
     * 撤銷使用者的所有 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return int 撤銷的 Token 數量
     */
    public function revokeAllUserTokens(User $user): int
    {
        return $this->manager->revokeAllUserTokens($user);
    }

    /**
     * 撤銷指定名稱的 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @return int 撤銷的 Token 數量
     */
    public function revokeTokensByName(User $user, string $name): int
    {
        return $this->manager->revokeTokensByName($user, $name);
    }

    /**
     * 取得使用者的所有有效 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTokens(User $user): Collection
    {
        return $this->manager->getUserTokens($user);
    }

    /**
     * 清理過期的 Token
     *
     * @return int 清理的 Token 數量
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->manager->cleanupExpiredTokens();
    }

    /**
     * 檢查 Token 是否存在且有效
     *
     * @param string $token 原始 Token 字串
     * @return bool
     */
    public function isTokenValid(string $token): bool
    {
        return $this->manager->isTokenValid($token);
    }

    /**
     * 取得 Token 的詳細資訊
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\ApiToken|null
     */
    public function getTokenInfo(string $token): ?ApiToken
    {
        return $this->manager->getTokenInfo($token);
    }

    // ========== 便利方法 ==========

    /**
     * 建立具有完整權限的管理員 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @param \Carbon\Carbon|null $expiresAt 過期時間
     * @return array
     */
    public function createAdminToken(User $user, string $name = 'Admin Token', ?Carbon $expiresAt = null): array
    {
        return $this->createToken($user, $name, ['*'], $expiresAt);
    }

    /**
     * 建立具有特定權限的 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @param string $name Token 名稱
     * @param array $permissions 權限陣列
     * @param int $daysToExpire 幾天後過期（預設 30 天）
     * @return array
     */
    public function createTokenWithExpiry(User $user, string $name, array $permissions = [], int $daysToExpire = 30): array
    {
        $expiresAt = Carbon::now()->addDays($daysToExpire);
        return $this->createToken($user, $name, $permissions, $expiresAt);
    }

    /**
     * 檢查 Token 是否即將過期（7天內）
     *
     * @param string $token 原始 Token 字串
     * @return bool
     */
    public function isTokenExpiringSoon(string $token): bool
    {
        $tokenInfo = $this->getTokenInfo($token);
        
        if (!$tokenInfo || !$tokenInfo->expires_at) {
            return false;
        }

        return $tokenInfo->expires_at->diffInDays(Carbon::now()) <= 7;
    }

    /**
     * 取得 Token 的剩餘有效天數
     *
     * @param string $token 原始 Token 字串
     * @return int|null 剩餘天數，null 表示永不過期或 Token 無效
     */
    public function getTokenRemainingDays(string $token): ?int
    {
        $tokenInfo = $this->getTokenInfo($token);
        
        if (!$tokenInfo || !$tokenInfo->expires_at) {
            return null;
        }

        $now = Carbon::now();
        $expiresAt = $tokenInfo->expires_at;
        
        // 如果已經過期，回傳0
        if ($expiresAt->isPast()) {
            return 0;
        }
        
        // 計算剩餘天數
        return $now->diffInDays($expiresAt, false);
    }
}