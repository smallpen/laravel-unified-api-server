<?php

namespace App\Exceptions;

use App\Contracts\ResponseFormatterInterface;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * 全域例外處理器
 * 
 * 處理應用程式中的所有例外情況，提供統一的錯誤回應格式
 * 確保敏感資訊不會洩漏到錯誤回應中
 */
class Handler extends ExceptionHandler
{
    /**
     * 不應該被報告的例外類型清單
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        NotFoundException::class,
        RateLimitException::class,
    ];

    /**
     * 不應該被閃存到session的輸入清單
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'bearer_token',
    ];

    /**
     * 註冊例外處理回呼
     */
    public function register(): void
    {
        // 報告例外時過濾敏感資訊
        $this->reportable(function (Throwable $e) {
            // 記錄例外但過濾敏感資訊
            $this->logException($e);
        });

        // 渲染例外回應
        $this->renderable(function (Throwable $e, Request $request) {
            // 只處理 API 請求
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->renderApiException($e, $request);
            }
        });
    }

    /**
     * 取得回應格式化器
     * 
     * @return ResponseFormatterInterface
     */
    protected function getResponseFormatter(): ResponseFormatterInterface
    {
        try {
            return app(ResponseFormatterInterface::class);
        } catch (\Throwable $e) {
            // 如果無法從容器中解析，則建立一個新的實例
            return new \App\Services\ResponseFormatter();
        }
    }

    /**
     * 渲染 API 例外回應
     * 
     * @param Throwable $e 例外物件
     * @param Request $request 請求物件
     * @return JsonResponse
     */
    protected function renderApiException(Throwable $e, Request $request): JsonResponse
    {
        // 處理自定義 API 例外
        if ($e instanceof ApiException) {
            return $e->toJsonResponse();
        }

        // 處理 Laravel 內建例外
        return $this->handleBuiltInExceptions($e, $request);
    }

    /**
     * 處理 Laravel 內建例外
     * 
     * @param Throwable $e 例外物件
     * @param Request $request 請求物件
     * @return JsonResponse
     */
    protected function handleBuiltInExceptions(Throwable $e, Request $request): JsonResponse
    {
        // Laravel 驗證例外
        if ($e instanceof LaravelValidationException) {
            return response()->json(
                $this->getResponseFormatter()->error(
                    '請求參數驗證失敗',
                    'VALIDATION_ERROR',
                    $e->errors()
                ),
                422
            );
        }

        // Laravel 認證例外
        if ($e instanceof LaravelAuthenticationException) {
            return response()->json(
                $this->getResponseFormatter()->error(
                    '身份驗證失敗',
                    'AUTHENTICATION_ERROR'
                ),
                401
            );
        }

        // 模型未找到例外
        if ($e instanceof ModelNotFoundException) {
            return response()->json(
                $this->getResponseFormatter()->error(
                    '請求的資源不存在',
                    'NOT_FOUND',
                    ['model' => class_basename($e->getModel())]
                ),
                404
            );
        }

        // HTTP 例外
        if ($e instanceof HttpException) {
            return $this->handleHttpException($e);
        }

        // 其他未處理的例外
        return $this->handleGenericException($e, $request);
    }

    /**
     * 處理 HTTP 例外
     * 
     * @param HttpException $e HTTP 例外
     * @return JsonResponse
     */
    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->getDefaultHttpMessage($statusCode);

        // 根據狀態碼決定錯誤代碼
        $errorCode = match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR'
        };

        // 特殊處理某些 HTTP 例外
        if ($e instanceof NotFoundHttpException) {
            $message = '請求的路由不存在';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $message = '不支援的 HTTP 方法';
        } elseif ($e instanceof TooManyRequestsHttpException) {
            $message = '請求頻率過高，請稍後再試';
        }

        return response()->json(
            $this->getResponseFormatter()->error($message, $errorCode),
            $statusCode
        );
    }

    /**
     * 處理一般例外
     * 
     * @param Throwable $e 例外物件
     * @param Request $request 請求物件
     * @return JsonResponse
     */
    protected function handleGenericException(Throwable $e, Request $request): JsonResponse
    {
        // 在生產環境中隱藏詳細錯誤資訊
        if (app()->environment('production')) {
            $message = '系統發生內部錯誤，請稍後再試';
            $details = [];
        } else {
            $message = $e->getMessage() ?: '發生未知錯誤';
            $details = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->sanitizeTrace($e->getTrace())
            ];
        }

        return response()->json(
            $this->getResponseFormatter()->error($message, 'INTERNAL_SERVER_ERROR', $details),
            500
        );
    }

    /**
     * 取得預設的 HTTP 錯誤訊息
     * 
     * @param int $statusCode HTTP 狀態碼
     * @return string
     */
    protected function getDefaultHttpMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => '請求格式錯誤',
            401 => '身份驗證失敗',
            403 => '權限不足',
            404 => '請求的資源不存在',
            405 => '不支援的 HTTP 方法',
            422 => '請求參數無法處理',
            429 => '請求頻率過高',
            500 => '系統內部錯誤',
            502 => '閘道錯誤',
            503 => '服務暫時無法使用',
            default => 'HTTP 錯誤'
        };
    }

    /**
     * 清理例外追蹤資訊，移除敏感資料
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
                    return $arg;
                }, $item['args']);
            }

            return $item;
        }, array_slice($trace, 0, 10)); // 只保留前 10 層追蹤
    }

    /**
     * 記錄例外資訊
     * 
     * @param Throwable $e 例外物件
     */
    protected function logException(Throwable $e): void
    {
        // 建立安全的日誌內容
        $logData = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_id' => $this->getResponseFormatter()->getRequestId(),
            'user_id' => $this->getCurrentUserId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ];

        // 根據例外類型決定日誌等級
        if ($e instanceof ApiException) {
            $level = $e->getHttpStatusCode() >= 500 ? 'error' : 'warning';
        } else {
            $level = 'error';
        }

        logger()->log($level, '例外發生', $logData);
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
     * 判斷例外是否應該被報告
     * 
     * @param Throwable $e 例外物件
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        // API 例外通常不需要報告（已經在日誌中記錄）
        if ($e instanceof ApiException && $e->getHttpStatusCode() < 500) {
            return false;
        }

        return parent::shouldReport($e);
    }
}