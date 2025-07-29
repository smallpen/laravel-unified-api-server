<?php

namespace App\Exceptions;

/**
 * 授權例外類別
 * 
 * 處理權限不足的情況
 */
class AuthorizationException extends ApiException
{
    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細錯誤資訊
     */
    public function __construct(string $message = '權限不足，無法存取此資源', array $details = [])
    {
        parent::__construct($message, 'AUTHORIZATION_ERROR', 403, $details);
    }

    /**
     * Action 權限不足例外
     * 
     * @param string $actionType Action 類型
     * @param array $requiredPermissions 需要的權限
     * @return static
     */
    public static function insufficientPermissions(string $actionType, array $requiredPermissions = []): static
    {
        return new static(
            "執行 {$actionType} 需要更高的權限",
            [
                'action_type' => $actionType,
                'required_permissions' => $requiredPermissions
            ]
        );
    }
}