<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * 健康檢查控制器
 * 
 * 提供系統健康狀態檢查端點，用於監控和端到端測試
 */
class HealthController extends Controller
{
    /**
     * 基本健康檢查
     * 
     * @return JsonResponse
     */
    public function basic(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'message' => '系統運行正常'
        ]);
    }

    /**
     * 詳細健康檢查
     * 
     * @return JsonResponse
     */
    public function detailed(): JsonResponse
    {
        $healthData = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => $this->checkServices(),
            'system' => $this->getSystemInfo()
        ];

        // 檢查是否有任何服務不健康
        $allServicesHealthy = true;
        $healthyStatuses = ['connected', 'healthy', 'configured', 'sync_mode'];
        foreach ($healthData['services'] as $service => $status) {
            if (!in_array($status, $healthyStatuses)) {
                $allServicesHealthy = false;
                break;
            }
        }

        if (!$allServicesHealthy) {
            $healthData['status'] = 'unhealthy';
        }

        $httpStatus = $healthData['status'] === 'healthy' ? 200 : 503;

        return response()->json($healthData, $httpStatus);
    }

    /**
     * 檢查各項服務狀態
     * 
     * @return array
     */
    private function checkServices(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue()
        ];
    }

    /**
     * 檢查資料庫連線
     * 
     * @return string
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            
            // 執行簡單查詢測試
            $result = DB::select('SELECT 1 as test');
            
            if (!empty($result) && $result[0]->test == 1) {
                return 'connected';
            } else {
                return 'query_failed';
            }
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    /**
     * 檢查快取連線
     * 
     * @return string
     */
    private function checkCache(): string
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . uniqid();
            
            // 寫入測試
            Cache::put($testKey, $testValue, 60);
            
            // 讀取測試
            $retrievedValue = Cache::get($testKey);
            
            // 清理測試資料
            Cache::forget($testKey);
            
            if ($retrievedValue === $testValue) {
                return 'connected';
            } else {
                return 'read_write_failed';
            }
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    /**
     * 檢查儲存系統
     * 
     * @return string
     */
    private function checkStorage(): string
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'health check test content';
            
            // 寫入測試
            Storage::put($testFile, $testContent);
            
            // 讀取測試
            $retrievedContent = Storage::get($testFile);
            
            // 清理測試檔案
            Storage::delete($testFile);
            
            if ($retrievedContent === $testContent) {
                return 'healthy';
            } else {
                return 'read_write_failed';
            }
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * 檢查佇列系統
     * 
     * @return string
     */
    private function checkQueue(): string
    {
        try {
            // 檢查佇列配置
            $queueDriver = config('queue.default');
            
            if ($queueDriver === 'sync') {
                return 'sync_mode';
            }
            
            // 對於其他佇列驅動，可以添加更詳細的檢查
            return 'configured';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    /**
     * 獲取系統資訊
     * 
     * @return array
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'disk_usage' => $this->getDiskUsage(),
            'uptime' => $this->getUptime(),
            'timezone' => config('app.timezone'),
            'environment' => app()->environment()
        ];
    }

    /**
     * 格式化位元組數
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 獲取磁碟使用情況
     * 
     * @return array
     */
    private function getDiskUsage(): array
    {
        try {
            $path = base_path();
            $totalBytes = disk_total_space($path);
            $freeBytes = disk_free_space($path);
            $usedBytes = $totalBytes - $freeBytes;
            
            return [
                'total' => $this->formatBytes($totalBytes),
                'used' => $this->formatBytes($usedBytes),
                'free' => $this->formatBytes($freeBytes),
                'usage_percentage' => round(($usedBytes / $totalBytes) * 100, 2)
            ];
        } catch (\Exception $e) {
            return [
                'error' => '無法獲取磁碟使用情況'
            ];
        }
    }

    /**
     * 獲取系統運行時間
     * 
     * @return string
     */
    private function getUptime(): string
    {
        try {
            // 嘗試從 /proc/uptime 讀取（Linux系統）
            if (file_exists('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                $uptimeSeconds = (float) explode(' ', $uptime)[0];
                
                $days = floor($uptimeSeconds / 86400);
                $hours = floor(($uptimeSeconds % 86400) / 3600);
                $minutes = floor(($uptimeSeconds % 3600) / 60);
                
                return "{$days}天 {$hours}小時 {$minutes}分鐘";
            }
            
            // 如果無法獲取系統運行時間，返回應用程式啟動時間
            return '應用程式運行中';
        } catch (\Exception $e) {
            return '無法獲取運行時間';
        }
    }

    /**
     * API端點健康檢查
     * 
     * @return JsonResponse
     */
    public function api(): JsonResponse
    {
        try {
            // 檢查路由是否正確載入
            $routeCount = count(app('router')->getRoutes());
            
            // 檢查中介軟體是否正常
            $middlewareCount = count(app('router')->getMiddleware());
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'api_info' => [
                    'routes_loaded' => $routeCount,
                    'middleware_loaded' => $middlewareCount,
                    'endpoint' => '/api',
                    'methods' => ['POST'],
                    'authentication' => 'Bearer Token'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => 'API端點檢查失敗',
                'message' => $e->getMessage()
            ], 503);
        }
    }

    /**
     * 資料庫健康檢查
     * 
     * @return JsonResponse
     */
    public function database(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            
            // 檢查資料庫連線
            DB::connection()->getPdo();
            
            // 檢查主要資料表是否存在
            $tables = [
                'users',
                'api_tokens',
                'api_logs'
            ];
            
            $tableStatus = [];
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $tableStatus[$table] = [
                        'exists' => true,
                        'record_count' => $count
                    ];
                } catch (\Exception $e) {
                    $tableStatus[$table] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'database_info' => [
                    'connection' => 'connected',
                    'driver' => config('database.default'),
                    'response_time_ms' => $responseTime,
                    'tables' => $tableStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => '資料庫連線失敗',
                'message' => $e->getMessage()
            ], 503);
        }
    }
}