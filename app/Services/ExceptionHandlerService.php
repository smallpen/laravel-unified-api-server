<?php

namespace App\Services;

use App\Contracts\ResponseFormatterInterface;
use App\Exceptions\ApiException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * 例外處理服務
 * 
 * 提供統一的例外處理方法和工具
 */
class ExceptionHandlerService
{
    /**
     * 回應格式化器
     * 
     * @var ResponseFormatterInterface
     */
    protected ResponseFormatterInterface $responseFormatter;

    /**
     * 建構函式
     * 
     * @param ResponseFormatterInterface $responseFormatter
     */
    public function __construct(ResponseFormatterInterface $responseFormatter)
    {
        $this->responseFormatter = $responseFormatter;
    }

    /**
     * 處理驗證錯誤
     * 
     * @param array $errors 驗證錯誤陣列
     * @param string $message 自定義錯誤訊息
     * @throws ValidationException
     */
    public function throwValidationError(array $errors, string $message = '請求參數驗證失敗'): void
    {
        throw new ValidationException($message, $errors);
    }

    /**
     * 處理認證錯誤
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細資訊
     * @throws AuthenticationException
     */
    public function throwAuthenticationError(string $message = '身份驗證失敗', array $details = []): void
    {
        throw new AuthenticationException($message, $details);
    }

    /**
     * 處理授權錯誤
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細資訊
     * @throws AuthorizationException
     */
    public function throwAuthorizationError(string $message = '權限不足', array $details = []): void
    {
        throw new AuthorizationException($message, $details);
    }

    /**
     * 處理資源未找到錯誤
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細資訊
     * @throws NotFoundException
     */
    public function throwNotFoundError(string $message = '請求的資源不存在', array $details = []): void
    {
        throw new NotFoundException($message, $details);
    }

    /**
     * 處理速率限制錯誤
     * 
     * @param string $message 錯誤訊息
     * @param array $details 詳細資訊
     * @throws RateLimitException
     */
    public function throwRateLimitError(string $message = '請求頻率過高', array $details = []): void
    {
        throw new RateLimitException($message, $details);
    }

    /**
     * 處理一般 API 錯誤
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param int $httpStatusCode HTTP 狀態碼
     * @param array $details 詳細資訊
     * @throws ApiException
     */
    public function throwApiError(
        string $message,
        string $errorCode = 'API_ERROR',
        int $httpStatusCode = 500,
        array $details = []
    ): void {
        throw new ApiException($message, $errorCode, $httpStatusCode, $details);
    }

    /**
     * 安全地處理例外並回傳 JSON 回應
     * 
     * @param Throwable $e 例外物件
     * @param bool $includeTrace 是否包含追蹤資訊（僅開發環境）
     * @return JsonResponse
     */
    public function handleExceptionSafely(Throwable $e, bool $includeTrace = false): JsonResponse
    {
        // 如果是自定義 API 例外，直接回傳
        if ($e instanceof ApiException) {
            return $e->toJsonResponse();
        }

        // 在生產環境中隱藏敏感資訊
        if (app()->environment('production')) {
            $message = '系統發生內部錯誤，請稍後再試';
            $details = [];
        } else {
            $message = $e->getMessage() ?: '發生未知錯誤';
            $details = [];

            if ($includeTrace) {
                $details = [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $this->sanitizeTrace($e->getTrace())
                ];
            }
        }

        return response()->json(
            $this->responseFormatter->error($message, 'INTERNAL_SERVER_ERROR', $details),
            500
        );
    }

    /**
     * 清理例外追蹤資訊
     * 
     * @param array $trace 原始追蹤資訊
     * @return array 清理後的追蹤資訊
     */
    protected function sanitizeTrace(array $trace): array
    {
        return array_map(function ($item) {
            // 移除敏感的參數資訊
            if (isset($item['args'])) {
                $item['args'] = array_map(function ($arg) {
                    if (is_string($arg) && strlen($arg) > 100) {
                        return '[長字串已隱藏]';
                    }
                    if (is_array($arg) && count($arg) > 10) {
                        return '[大陣列已隱藏]';
                    }
                    if (is_object($arg)) {
                        return '[物件已隱藏: ' . get_class($arg) . ']';
                    }
                    return $arg;
                }, $item['args']);
            }

            return $item;
        }, array_slice($trace, 0, 10)); // 只保留前 10 層追蹤
    }

    /**
     * 記錄例外資訊到日誌
     * 
     * @param Throwable $e 例外物件
     * @param array $context 額外的上下文資訊
     */
    public function logException(Throwable $e, array $context = []): void
    {
        $logData = array_merge([
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_id' => $this->responseFormatter->getRequestId(),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ], $context);

        // 根據例外類型決定日誌等級
        if ($e instanceof ApiException) {
            $level = $e->getHttpStatusCode() >= 500 ? 'error' : 'warning';
        } else {
            $level = 'error';
        }

        logger()->log($level, '例外發生', $logData);
    }

    /**
     * 檢查例外是否為敏感例外
     * 
     * @param Throwable $e 例外物件
     * @return bool
     */
    public function isSensitiveException(Throwable $e): bool
    {
        $sensitiveExceptions = [
            \PDOException::class,
            \Illuminate\Database\QueryException::class,
            \Illuminate\Encryption\DecryptException::class,
        ];

        foreach ($sensitiveExceptions as $sensitiveException) {
            if ($e instanceof $sensitiveException) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取得目前使用者 ID
     * 
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        try {
            return auth()->id();
        } catch (\Exception $e) {
            // 在測試環境或認證系統未配置時，回傳 null
            return null;
        }
    }

    /**
     * 建立標準化的錯誤回應
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param int $httpStatusCode HTTP 狀態碼
     * @param array $details 詳細資訊
     * @return JsonResponse
     */
    public function createErrorResponse(
        string $message,
        string $errorCode,
        int $httpStatusCode = 500,
        array $details = []
    ): JsonResponse {
        return response()->json(
            $this->responseFormatter->error($message, $errorCode, $details),
            $httpStatusCode
        );
    }
}