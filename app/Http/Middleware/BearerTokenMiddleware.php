<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer Token 驗證中介軟體
 * 
 * 負責驗證 Bearer Token 並設定當前使用者
 */
class BearerTokenMiddleware
{
    /**
     * 處理傳入的請求
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 從請求標頭中提取 Bearer Token
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('缺少 Bearer Token');
        }

        // 驗證 Token 並取得使用者
        $user = $this->validateTokenAndGetUser($token);

        if (!$user) {
            return $this->unauthorizedResponse('無效或過期的 Bearer Token');
        }

        // 將使用者設定到請求中
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // 更新 Token 最後使用時間
        $this->updateTokenLastUsed($token);

        return $next($request);
    }

    /**
     * 從請求標頭中提取 Bearer Token
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    private function extractBearerToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return null;
        }

        // 檢查是否為 Bearer Token 格式
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        // 提取 Token 部分
        $token = substr($authHeader, 7); // 移除 "Bearer " 前綴

        return !empty($token) ? $token : null;
    }

    /**
     * 驗證 Token 並取得對應的使用者
     *
     * @param string $token
     * @return \App\Models\User|null
     */
    private function validateTokenAndGetUser(string $token): ?User
    {
        $tokenService = app(\App\Services\TokenService::class);
        return $tokenService->validateToken($token);
    }

    /**
     * 更新 Token 的最後使用時間
     *
     * @param string $token
     * @return void
     */
    private function updateTokenLastUsed(string $token): void
    {
        $tokenService = app(\App\Services\TokenService::class);
        $tokenService->updateTokenLastUsed($token);
    }

    /**
     * 回傳未授權的 JSON 回應
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
            'timestamp' => now()->toISOString(),
        ], 401);
    }
}