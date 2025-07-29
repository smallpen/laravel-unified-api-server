<?php

namespace App\Exceptions;

/**
 * 認證例外類別
 * 
 * 處理身份驗證失敗的情況
 */
class AuthenticationException extends ApiException
{
    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細錯誤資訊
     */
    public function __construct(string $message = '身份驗證失敗', array $details = [])
    {
        parent::__construct($message, 'AUTHENTICATION_ERROR', 401, $details);
    }

    /**
     * Token 無效例外
     * 
     * @return static
     */
    public static function invalidToken(): static
    {
        return new static('提供的 Token 無效或已過期');
    }

    /**
     * Token 缺失例外
     * 
     * @return static
     */
    public static function missingToken(): static
    {
        return new static('請求標頭中缺少 Bearer Token');
    }

    /**
     * Token 過期例外
     * 
     * @return static
     */
    public static function expiredToken(): static
    {
        return new static('Token 已過期，請重新取得授權');
    }
}