<?php

namespace App\Services;

use App\Contracts\TokenManagerInterface;
use App\Models\User;
use App\Models\ApiToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

/**
 * Token 管理器服務
 * 
 * 實作 Token 生成、撤銷和管理功能
 */
class TokenManager implements TokenManagerInterface
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
    public function createToken(User $user, string $name, array $permissions = [], ?Carbon $expiresAt = null): array
    {
        try {
            $result = ApiToken::createToken($user->id, $name, $permissions, $expiresAt);

            Log::info('新 API Token 已建立', [
                'user_id' => $user->id,
                'token_name' => $name,
                'permissions' => $permissions,
                'expires_at' => $expiresAt?->toISOString(),
                'token_id' => $result['model']->id
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('建立 API Token 失敗', [
                'user_id' => $user->id,
                'token_name' => $name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 撤銷指定的 Token
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示撤銷成功，false 表示撤銷失敗
     */
    public function revokeToken(string $token): bool
    {
        try {
            $apiToken = ApiToken::findByToken($token);

            if (!$apiToken) {
                return false;
            }

            $apiToken->revoke();

            Log::info('Token 已撤銷', [
                'token_id' => $apiToken->id,
                'user_id' => $apiToken->user_id,
                'token_name' => $apiToken->name
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('撤銷 Token 失敗', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return false;
        }
    }

    /**
     * 撤銷使用者的所有 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return int 撤銷的 Token 數量
     */
    public function revokeAllUserTokens(User $user): int
    {
        try {
            $count = ApiToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            Log::info('使用者所有 Token 已撤銷', [
                'user_id' => $user->id,
                'revoked_count' => $count
            ]);

            return $count;
        } catch (\Exception $e) {
            Log::error('撤銷使用者所有 Token 失敗', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
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
        try {
            $count = ApiToken::where('user_id', $user->id)
                ->where('name', $name)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            Log::info('指定名稱的 Token 已撤銷', [
                'user_id' => $user->id,
                'token_name' => $name,
                'revoked_count' => $count
            ]);

            return $count;
        } catch (\Exception $e) {
            Log::error('撤銷指定名稱的 Token 失敗', [
                'user_id' => $user->id,
                'token_name' => $name,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 取得使用者的所有有效 Token
     *
     * @param \App\Models\User $user 使用者實例
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTokens(User $user): Collection
    {
        try {
            return ApiToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('取得使用者 Token 失敗', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * 清理過期的 Token
     *
     * @return int 清理的 Token 數量
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $count = ApiToken::cleanupExpiredTokens();

            if ($count > 0) {
                Log::info('過期 Token 已清理', [
                    'cleaned_count' => $count
                ]);
            }

            return $count;
        } catch (\Exception $e) {
            Log::error('清理過期 Token 失敗', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 檢查 Token 是否存在且有效
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示 Token 有效，false 表示無效
     */
    public function isTokenValid(string $token): bool
    {
        try {
            $apiToken = ApiToken::findByToken($token);

            if (!$apiToken) {
                return false;
            }

            return $apiToken->isValid();
        } catch (\Exception $e) {
            Log::error('檢查 Token 有效性失敗', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return false;
        }
    }

    /**
     * 取得 Token 的詳細資訊
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\ApiToken|null Token 模型實例或 null
     */
    public function getTokenInfo(string $token): ?ApiToken
    {
        try {
            return ApiToken::findByToken($token);
        } catch (\Exception $e) {
            Log::error('取得 Token 資訊失敗', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return null;
        }
    }
}