<?php

namespace App\Exceptions;

/**
 * 速率限制例外類別
 * 
 * 處理請求頻率過高的情況
 */
class RateLimitException extends ApiException
{
    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細錯誤資訊
     */
    public function __construct(string $message = '請求頻率過高，請稍後再試', array $details = [])
    {
        parent::__construct($message, 'RATE_LIMIT_EXCEEDED', 429, $details);
    }

    /**
     * 建立速率限制例外
     * 
     * @param int $retryAfter 重試等待時間（秒）
     * @param int $maxAttempts 最大嘗試次數
     * @return static
     */
    public static function tooManyAttempts(int $retryAfter, int $maxAttempts): static
    {
        return new static(
            "請求頻率過高，請在 {$retryAfter} 秒後重試",
            [
                'retry_after' => $retryAfter,
                'max_attempts' => $maxAttempts
            ]
        );
    }
}