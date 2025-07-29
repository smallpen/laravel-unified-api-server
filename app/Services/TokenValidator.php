<?php

namespace App\Services;

use App\Contracts\TokenValidatorInterface;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Log;

/**
 * Token 驗證器服務
 * 
 * 實作 Token 驗證和權限檢查功能
 */
class TokenValidator implements TokenValidatorInterface
{
    /**
     * 驗證 Token 並回傳對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null 驗證成功回傳使用者，失敗回傳 null
     */
    public function validate(string $token): ?User
    {
        try {
            $apiToken = $this->findTokenModel($token);

            if (!$apiToken) {
                return null;
            }

            // 檢查 Token 是否有效（未過期且啟用）
            if (!$apiToken->isValid()) {
                return null;
            }

            // 載入使用者
            $user = $apiToken->user;
            
            // 將Token的權限設定到使用者上
            if ($user && $apiToken->permissions) {
                $user->permissions = $apiToken->permissions;
            }
            
            return $user;
        } catch (\Exception $e) {
            Log::error('Token 驗證錯誤', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return null;
        }
    }

    /**
     * 檢查 Token 是否已過期
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示已過期，false 表示未過期
     */
    public function isExpired(string $token): bool
    {
        try {
            $apiToken = $this->findTokenModel($token);

            if (!$apiToken) {
                return true; // Token 不存在視為過期
            }

            return $apiToken->isExpired();
        } catch (\Exception $e) {
            Log::error('檢查 Token 過期狀態錯誤', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return true; // 發生錯誤時視為過期
        }
    }

    /**
     * 從 Token 取得對應的使用者
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\User|null 成功回傳使用者，失敗回傳 null
     */
    public function getUserFromToken(string $token): ?User
    {
        return $this->validate($token);
    }

    /**
     * 檢查 Token 是否有特定權限
     *
     * @param string $token 原始 Token 字串
     * @param string $permission 權限名稱
     * @return bool true 表示有權限，false 表示無權限
     */
    public function hasPermission(string $token, string $permission): bool
    {
        try {
            $apiToken = $this->findTokenModel($token);

            if (!$apiToken) {
                return false;
            }

            // 檢查 Token 是否有效
            if (!$apiToken->isValid()) {
                return false;
            }

            return $apiToken->hasPermission($permission);
        } catch (\Exception $e) {
            Log::error('檢查 Token 權限錯誤', [
                'error' => $e->getMessage(),
                'permission' => $permission,
                'token_hash' => hash('sha256', $token)
            ]);
            return false;
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
            $apiToken = $this->findTokenModel($token);

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
            Log::error('撤銷 Token 錯誤', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return false;
        }
    }

    /**
     * 更新 Token 的最後使用時間
     *
     * @param string $token 原始 Token 字串
     * @return bool true 表示更新成功，false 表示更新失敗
     */
    public function updateLastUsed(string $token): bool
    {
        try {
            $apiToken = $this->findTokenModel($token);

            if (!$apiToken) {
                return false;
            }

            $apiToken->updateLastUsed();
            return true;
        } catch (\Exception $e) {
            Log::warning('更新 Token 最後使用時間失敗', [
                'error' => $e->getMessage(),
                'token_hash' => hash('sha256', $token)
            ]);
            return false;
        }
    }

    /**
     * 根據 Token 字串查找對應的 ApiToken 模型
     *
     * @param string $token 原始 Token 字串
     * @return \App\Models\ApiToken|null
     */
    private function findTokenModel(string $token): ?ApiToken
    {
        return ApiToken::findByToken($token);
    }
}