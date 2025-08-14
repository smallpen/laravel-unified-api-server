<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * API 例外基礎類別
 * 
 * 提供統一的API例外處理機制
 */
class ApiException extends Exception
{
    /**
     * 錯誤代碼
     * 
     * @var string
     */
    protected string $errorCode;

    /**
     * HTTP 狀態碼
     * 
     * @var int
     */
    protected int $httpStatusCode;

    /**
     * 詳細錯誤資訊
     * 
     * @var array
     */
    protected array $details;

    /**
     * 建構函式
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param int $httpStatusCode HTTP 狀態碼
     * @param array $details 詳細錯誤資訊
     * @param Exception|null $previous 前一個例外
     */
    public function __construct(
        string $message = '發生未知錯誤',
        string $errorCode = 'UNKNOWN_ERROR',
        int $httpStatusCode = 500,
        array $details = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->httpStatusCode = $httpStatusCode;
        $this->details = $details;
    }

    /**
     * 取得錯誤代碼
     * 
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 取得 HTTP 狀態碼
     * 
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * 取得詳細錯誤資訊
     * 
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * 設定詳細錯誤資訊
     * 
     * @param array $details
     * @return self
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
     * 轉換為 JSON 回應
     * 
     * @return JsonResponse
     */
    public function toJsonResponse(): JsonResponse
    {
        try {
            $formatter = app(\App\Contracts\ResponseFormatterInterface::class);
        } catch (\Throwable $e) {
            // 如果無法從容器中解析，則建立一個新的實例
            $formatter = new \App\Services\ResponseFormatter();
        }
        
        return response()->json(
            $formatter->error($this->getMessage(), $this->getErrorCode(), $this->getDetails()),
            $this->getHttpStatusCode()
        );
    }
}