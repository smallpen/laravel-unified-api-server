<?php

/**
 * Laravel統一API系統 - PHP客戶端範例
 * 
 * 這個檔案提供了完整的PHP API客戶端實作範例，
 * 包含錯誤處理、重試機制、快取等功能。
 */

class LaravelApiClient
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;
    private bool $cacheEnabled;
    private int $cacheTTL;
    private array $cache = [];
    private array $requestInterceptors = [];
    private array $responseInterceptors = [];

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['baseUrl'] ?? 'http://localhost:8000';
        $this->token = $config['token'] ?? null;
        $this->timeout = $config['timeout'] ?? 10;
        $this->retryAttempts = $config['retryAttempts'] ?? 3;
        $this->retryDelay = $config['retryDelay'] ?? 1;
        $this->cacheEnabled = $config['cacheEnabled'] ?? false;
        $this->cacheTTL = $config['cacheTTL'] ?? 300; // 5分鐘
    }

    /**
     * 設定Bearer Token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * 添加請求攔截器
     */
    public function addRequestInterceptor(callable $interceptor): void
    {
        $this->requestInterceptors[] = $interceptor;
    }

    /**
     * 添加回應攔截器
     */
    public function addResponseInterceptor(callable $interceptor): void
    {
        $this->responseInterceptors[] = $interceptor;
    }

    /**
     * 生成快取鍵
     */
    private function getCacheKey(string $actionType, array $data): string
    {
        return $actionType . ':' . md5(json_encode($data));
    }

    /**
     * 檢查快取
     */
    private function getFromCache(string $key): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        if (isset($this->cache[$key])) {
            $cached = $this->cache[$key];
            if (time() - $cached['timestamp'] < $this->cacheTTL) {
                return $cached['data'];
            }
            
            // 清除過期快取
            unset($this->cache[$key]);
        }

        return null;
    }

    /**
     * 設定快取
     */
    private function setCache(string $key, array $data): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        $this->cache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * 清除快取
     */
    public function clearCache(?string $pattern = null): void
    {
        if ($pattern) {
            foreach (array_keys($this->cache) as $key) {
                if (strpos($key, $pattern) !== false) {
                    unset($this->cache[$key]);
                }
            }
        } else {
            $this->cache = [];
        }
    }

    /**
     * 執行請求攔截器
     */
    private function executeRequestInterceptors(array $requestData): array
    {
        $data = $requestData;
        foreach ($this->requestInterceptors as $interceptor) {
            $data = $interceptor($data);
        }
        return $data;
    }

    /**
     * 執行回應攔截器
     */
    private function executeResponseInterceptors(array $response): array
    {
        $data = $response;
        foreach ($this->responseInterceptors as $interceptor) {
            $data = $interceptor($data);
        }
        return $data;
    }

    /**
     * 發送API請求
     */
    public function call(string $actionType, array $data = [], array $options = []): array
    {
        // 檢查快取
        $cacheKey = $this->getCacheKey($actionType, $data);
        $cachedResult = $this->getFromCache($cacheKey);
        if ($cachedResult) {
            error_log("從快取取得結果: {$actionType}");
            return $cachedResult;
        }

        // 準備請求資料
        $requestData = array_merge(['action_type' => $actionType], $data);
        $requestData = $this->executeRequestInterceptors($requestData);

        // 執行請求（含重試機制）
        $lastException = null;
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                error_log("發送API請求 (嘗試 {$attempt}/{$this->retryAttempts}): {$actionType}");
                
                $result = $this->makeHttpRequest($requestData, $options);
                $result = $this->executeResponseInterceptors($result);

                // 檢查API回應狀態
                if ($result['status'] === 'error') {
                    throw new ApiException(
                        $result['message'],
                        $result['error_code'] ?? 'UNKNOWN_ERROR',
                        $result['details'] ?? null
                    );
                }

                // 快取成功結果
                $this->setCache($cacheKey, $result);
                
                error_log("API請求成功: {$actionType}");
                return $result;

            } catch (Exception $e) {
                $lastException = $e;
                error_log("API請求失敗 (嘗試 {$attempt}/{$this->retryAttempts}): " . $e->getMessage());

                // 如果是最後一次嘗試或不可重試的錯誤，直接拋出
                if ($attempt === $this->retryAttempts || !$this->shouldRetry($e)) {
                    break;
                }

                // 等待後重試（指數退避）
                sleep($this->retryDelay * pow(2, $attempt - 1));
            }
        }

        throw $lastException;
    }

    /**
     * 執行HTTP請求
     */
    private function makeHttpRequest(array $requestData, array $options): array
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
        ];
        
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // 開發環境可設為false，生產環境應為true
            CURLOPT_USERAGENT => 'Laravel-API-Client/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new NetworkException("cURL錯誤: {$error}");
        }

        if ($httpCode >= 400) {
            throw new HttpException("HTTP錯誤: {$httpCode}", $httpCode);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new NetworkException("JSON解析錯誤: " . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * 判斷是否應該重試
     */
    private function shouldRetry(Exception $e): bool
    {
        // API錯誤通常不需要重試
        if ($e instanceof ApiException) {
            return false;
        }
        
        // HTTP 4xx錯誤不重試
        if ($e instanceof HttpException && $e->getCode() >= 400 && $e->getCode() < 500) {
            return false;
        }

        // 網路錯誤和5xx錯誤可以重試
        return true;
    }

    /**
     * 批次API呼叫
     */
    public function batchCall(array $calls, array $options = []): array
    {
        $results = [];
        $concurrency = $options['concurrency'] ?? 5;
        
        $chunks = array_chunk($calls, $concurrency);
        
        foreach ($chunks as $chunk) {
            $chunkResults = [];
            
            foreach ($chunk as $call) {
                try {
                    $result = $this->call(
                        $call['actionType'],
                        $call['data'] ?? [],
                        $call['options'] ?? []
                    );
                    $chunkResults[] = ['success' => true, 'result' => $result];
                } catch (Exception $e) {
                    $chunkResults[] = ['success' => false, 'error' => $e];
                }
            }
            
            $results = array_merge($results, $chunkResults);
        }
        
        return $results;
    }

    /**
     * 上傳檔案
     */
    public function uploadFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("檔案不存在: {$filePath}");
        }

        $fileContent = file_get_contents($filePath);
        $base64Data = base64_encode($fileContent);
        
        $fileName = $options['fileName'] ?? basename($filePath);
        $fileType = $options['fileType'] ?? mime_content_type($filePath);
        $folder = $options['folder'] ?? 'uploads';

        return $this->call('file.upload', [
            'file_data' => $base64Data,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'folder' => $folder
        ]);
    }

    /**
     * 分頁資料載入器
     */
    public function loadPages(string $actionType, array $baseData = [], array $options = []): Generator
    {
        $page = 1;
        $hasMore = true;
        $perPage = $options['perPage'] ?? 20;
        $maxPages = $options['maxPages'] ?? null;

        while ($hasMore) {
            $result = $this->call($actionType, array_merge($baseData, [
                'page' => $page,
                'per_page' => $perPage
            ]));

            yield $result['data'];

            $hasMore = $result['data']['pagination']['has_more_pages'] ?? false;
            $page++;

            if ($maxPages && $page > $maxPages) {
                break;
            }
        }
    }

    /**
     * 健康檢查
     */
    public function healthCheck(): bool
    {
        try {
            $result = $this->call('system.health');
            return $result['data']['status'] === 'healthy';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 取得API統計資訊
     */
    public function getStats(): array
    {
        return [
            'cache_size' => count($this->cache),
            'cache_enabled' => $this->cacheEnabled,
            'base_url' => $this->baseUrl,
            'has_token' => !empty($this->token),
            'retry_attempts' => $this->retryAttempts,
        ];
    }
}

/**
 * API例外類別
 */
class ApiException extends Exception
{
    private string $errorCode;
    private ?array $details;

    public function __construct(string $message, string $errorCode, ?array $details = null)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }
}

/**
 * 網路例外類別
 */
class NetworkException extends Exception {}

/**
 * HTTP例外類別
 */
class HttpException extends Exception {}

/**
 * 使用範例
 */

// 基本使用
$apiClient = new LaravelApiClient([
    'baseUrl' => 'https://api.example.com',
    'token' => 'your-bearer-token',
    'cacheEnabled' => true,
    'retryAttempts' => 3
]);

// 添加請求日誌攔截器
$apiClient->addRequestInterceptor(function ($request) {
    error_log('發送請求: ' . json_encode($request));
    return $request;
});

// 添加回應處理攔截器
$apiClient->addResponseInterceptor(function ($response) {
    if ($response['status'] === 'error') {
        error_log('API錯誤: ' . $response['message']);
    }
    return $response;
});

try {
    // 1. 基本API呼叫
    $userInfo = $apiClient->call('user.info', ['user_id' => 123]);
    echo "使用者資訊: " . json_encode($userInfo['data']) . "\n";

    // 2. 建立使用者
    $newUser = $apiClient->call('user.create', [
        'name' => '新使用者',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ]);
    echo "新使用者: " . json_encode($newUser['data']) . "\n";

    // 3. 分頁資料載入
    foreach ($apiClient->loadPages('user.list', ['search' => '張']) as $pageData) {
        echo "頁面資料: " . json_encode($pageData) . "\n";
    }

    // 4. 批次API呼叫
    $batchCalls = [
        ['actionType' => 'user.info', 'data' => ['user_id' => 1]],
        ['actionType' => 'user.info', 'data' => ['user_id' => 2]],
        ['actionType' => 'user.info', 'data' => ['user_id' => 3]]
    ];
    $batchResults = $apiClient->batchCall($batchCalls);
    echo "批次結果: " . json_encode($batchResults) . "\n";

    // 5. 檔案上傳
    if (file_exists('/path/to/your/file.pdf')) {
        $uploadResult = $apiClient->uploadFile('/path/to/your/file.pdf', [
            'folder' => 'documents'
        ]);
        echo "上傳結果: " . json_encode($uploadResult['data']) . "\n";
    }

    // 6. 健康檢查
    $isHealthy = $apiClient->healthCheck();
    echo "系統健康狀態: " . ($isHealthy ? '正常' : '異常') . "\n";

    // 7. 取得統計資訊
    $stats = $apiClient->getStats();
    echo "客戶端統計: " . json_encode($stats) . "\n";

} catch (ApiException $e) {
    echo "API錯誤: " . $e->getMessage() . " (錯誤碼: " . $e->getErrorCode() . ")\n";
    if ($e->getDetails()) {
        echo "詳細資訊: " . json_encode($e->getDetails()) . "\n";
    }
} catch (NetworkException $e) {
    echo "網路錯誤: " . $e->getMessage() . "\n";
} catch (HttpException $e) {
    echo "HTTP錯誤: " . $e->getMessage() . " (狀態碼: " . $e->getCode() . ")\n";
} catch (Exception $e) {
    echo "未知錯誤: " . $e->getMessage() . "\n";
}