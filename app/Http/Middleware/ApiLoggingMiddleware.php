<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * API 請求日誌記錄中介軟體
 * 
 * 記錄所有 API 請求的詳細資訊，包括：
 * - 使用者資訊
 * - 動作類型
 * - 請求和回應資料
 * - 回應時間
 * - IP 位址和使用者代理
 */
class ApiLoggingMiddleware
{
    /**
     * 處理傳入的請求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 記錄開始時間
        $startTime = microtime(true);
        
        // 生成唯一的請求 ID
        $requestId = Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);
        
        // 除錯：記錄中介軟體被執行
        Log::info('ApiLoggingMiddleware 被執行', [
            'path' => $request->path(),
            'method' => $request->method(),
            'request_id' => $requestId,
        ]);
        
        // 執行請求
        $response = $next($request);
        
        // 計算回應時間（毫秒）
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        // 記錄日誌（異步處理以避免影響回應時間）
        $this->logApiRequest($request, $response, $responseTime, $requestId);
        
        return $response;
    }

    /**
     * 記錄 API 請求日誌
     */
    private function logApiRequest(Request $request, Response $response, float $responseTime, string $requestId): void
    {
        try {
            // 準備基本日誌資料
            $logData = [
                'user_id' => $request->user()?->id ?? Auth::id(),
                'action_type' => $request->input('action_type', 'unknown'),
                'request_data' => $this->sanitizeRequestData($request),
                'response_data' => $this->sanitizeResponseData($response),
                'response_time' => round($responseTime, 3),
                'ip_address' => $this->getClientIpAddress($request),
                'user_agent' => $request->userAgent() ?? 'Unknown',
                'status_code' => $response->getStatusCode(),
                'request_id' => $requestId,
            ];

            // 建立日誌記錄
            ApiLog::create($logData);
            
        } catch (Throwable $e) {
            // 日誌記錄失敗時記錄到系統日誌
            Log::error('API 日誌記錄失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $requestId,
            ]);
        }
    }

    /**
     * 清理請求資料，移除敏感資訊
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();
        
        // 移除敏感欄位
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'private_key',
            'credit_card',
            'ssn',
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        // 限制資料大小以避免資料庫儲存問題
        $jsonData = json_encode($data);
        if (strlen($jsonData) > 65535) { // TEXT 欄位限制
            return ['error' => '請求資料過大，已省略'];
        }
        
        return $data;
    }

    /**
     * 清理回應資料，移除敏感資訊
     */
    private function sanitizeResponseData(Response $response): ?array
    {
        try {
            $content = $response->getContent();
            
            // 只記錄 JSON 回應
            if (!$this->isJsonResponse($response)) {
                return ['type' => 'non-json', 'size' => strlen($content)];
            }
            
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'JSON 解析失敗'];
            }
            
            // 移除敏感資訊
            if (is_array($data)) {
                $data = $this->removeSensitiveData($data);
            }
            
            // 限制資料大小
            $jsonData = json_encode($data);
            if (strlen($jsonData) > 65535) {
                return ['error' => '回應資料過大，已省略'];
            }
            
            return $data;
            
        } catch (Throwable $e) {
            return ['error' => '回應資料處理失敗'];
        }
    }

    /**
     * 遞迴移除敏感資料
     */
    private function removeSensitiveData(array $data): array
    {
        $sensitiveFields = [
            'password',
            'token',
            'api_key',
            'secret',
            'private_key',
            'access_token',
            'refresh_token',
        ];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->removeSensitiveData($value);
            }
        }
        
        return $data;
    }

    /**
     * 檢查是否為 JSON 回應
     */
    private function isJsonResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * 取得客戶端真實 IP 位址
     */
    private function getClientIpAddress(Request $request): string
    {
        // 檢查各種可能的 IP 標頭
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // 代理伺服器
            'HTTP_X_FORWARDED',          // 代理伺服器
            'HTTP_X_CLUSTER_CLIENT_IP',  // 叢集
            'HTTP_FORWARDED_FOR',        // 代理伺服器
            'HTTP_FORWARDED',            // 代理伺服器
            'HTTP_CLIENT_IP',            // 代理伺服器
            'REMOTE_ADDR',               // 標準
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = $request->server($header);
            if (!empty($ip) && $ip !== 'unknown') {
                // 處理多個 IP 的情況（取第一個）
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // 驗證 IP 格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // 回退到預設 IP
        return $request->ip() ?? '127.0.0.1';
    }
}
