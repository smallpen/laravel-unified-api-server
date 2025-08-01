<?php

namespace App\Actions\System;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * 取得伺服器狀態Action
 * 
 * 提供伺服器資源使用狀況和效能指標的查詢功能
 */
class GetServerStatusAction extends BaseAction
{
    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'system.server_status';
    }

    /**
     * 執行取得伺服器狀態的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 伺服器狀態陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        $includeDetails = $request->input('include_details', false);

        // 基本狀態資訊
        $status = [
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => $this->getLoadAverage(),
            'timestamp' => now()->toISOString()
        ];

        // 如果需要詳細資訊
        if ($includeDetails) {
            $status['details'] = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'database_status' => $this->getDatabaseStatus(),
                'cache_status' => $this->getCacheStatus(),
                'queue_status' => $this->getQueueStatus()
            ];
        }

        $this->logInfo('伺服器狀態查詢完成', [
            'user_id' => $user->id,
            'include_details' => $includeDetails,
        ]);

        return [
            'server_status' => $status
        ];
    }

    /**
     * 取得系統運行時間
     * 
     * @return array 運行時間資訊
     */
    private function getUptime(): array
    {
        try {
            // 使用 PHP 內建的伺服器資訊
            $serverInfo = [];
            
            // 嘗試從 $_SERVER 取得伺服器啟動時間相關資訊
            if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $requestTime = $_SERVER['REQUEST_TIME_FLOAT'];
                $serverInfo['request_time'] = date('Y-m-d H:i:s', (int)$requestTime);
            }
            
            // 使用 PHP 程序啟動時間作為參考
            if (function_exists('getmypid')) {
                $pid = getmypid();
                $serverInfo['php_pid'] = $pid;
            }
            
            // 取得 PHP 版本和運行資訊
            $phpInfo = [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS_FAMILY,
                'memory_limit' => ini_get('memory_limit')
            ];
            
            // 組合可用的系統資訊
            $uptimeInfo = sprintf(
                'PHP %s (%s) on %s - Memory: %s',
                $phpInfo['version'],
                $phpInfo['sapi'],
                $phpInfo['os'],
                $phpInfo['memory_limit']
            );
            
            return [
                'raw' => $uptimeInfo,
                'php_info' => $phpInfo,
                'server_info' => $serverInfo,
                'status' => 'available'
            ];
            
        } catch (\Exception $e) {
            return [
                'raw' => 'System information not available in restricted environment',
                'status' => 'restricted',
                'note' => 'Running in shared hosting with open_basedir restrictions'
            ];
        }
    }

    /**
     * 取得記憶體使用狀況
     * 
     * @return array 記憶體使用資訊
     */
    private function getMemoryUsage(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        return [
            'current' => $this->formatBytes($memoryUsage),
            'peak' => $this->formatBytes($memoryPeak),
            'limit' => $memoryLimit,
            'current_bytes' => $memoryUsage,
            'peak_bytes' => $memoryPeak
        ];
    }

    /**
     * 取得磁碟使用狀況
     * 
     * @return array 磁碟使用資訊
     */
    private function getDiskUsage(): array
    {
        try {
            $path = storage_path();
            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);
            $usedBytes = $totalBytes - $freeBytes;
            $usagePercent = round(($usedBytes / $totalBytes) * 100, 2);

            return [
                'total' => $this->formatBytes($totalBytes),
                'used' => $this->formatBytes($usedBytes),
                'free' => $this->formatBytes($freeBytes),
                'usage_percent' => $usagePercent,
                'status' => $usagePercent > 90 ? 'warning' : 'normal'
            ];
        } catch (\Exception $e) {
            return [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'usage_percent' => 0,
                'status' => 'error'
            ];
        }
    }

    /**
     * 取得系統負載平均值
     * 
     * @return array 負載平均值資訊
     */
    private function getLoadAverage(): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return [
                    '1min' => round($load[0], 2),
                    '5min' => round($load[1], 2),
                    '15min' => round($load[2], 2),
                    'status' => 'available'
                ];
            }
            
            return [
                '1min' => 0,
                '5min' => 0,
                '15min' => 0,
                'status' => 'unavailable'
            ];
        } catch (\Exception $e) {
            return [
                '1min' => 0,
                '5min' => 0,
                '15min' => 0,
                'status' => 'error'
            ];
        }
    }

    /**
     * 取得資料庫狀態
     * 
     * @return array 資料庫狀態資訊
     */
    private function getDatabaseStatus(): array
    {
        try {
            $startTime = microtime(true);
            \DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'connected',
                'response_time_ms' => $responseTime,
                'driver' => config('database.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'response_time_ms' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 取得快取狀態
     * 
     * @return array 快取狀態資訊
     */
    private function getCacheStatus(): array
    {
        try {
            $startTime = microtime(true);
            cache()->put('status_check', 'test', 1);
            $value = cache()->get('status_check');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => $value === 'test' ? 'working' : 'error',
                'response_time_ms' => $responseTime,
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'response_time_ms' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 取得佇列狀態
     * 
     * @return array 佇列狀態資訊
     */
    private function getQueueStatus(): array
    {
        try {
            return [
                'status' => 'configured',
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.' . config('queue.default'))
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 格式化位元組數為可讀格式
     * 
     * @param int $bytes 位元組數
     * @return string 格式化後的字串
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['system.server_status'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'include_details' => 'sometimes|boolean'
        ];
    }

    /**
     * 取得驗證錯誤訊息
     * 
     * @return array 錯誤訊息陣列
     */
    protected function getValidationMessages(): array
    {
        return [
            'include_details.boolean' => '包含詳細資訊參數必須為布林值'
        ];
    }

    /**
     * 取得參數文件
     * 
     * @return array 參數文件陣列
     */
    protected function getParameterDocumentation(): array
    {
        return [
            'include_details' => [
                'type' => 'boolean',
                'required' => false,
                'description' => '是否包含詳細的系統資訊',
                'example' => true,
                'default' => false
            ]
        ];
    }

    /**
     * 取得回應文件
     * 
     * @return array 回應文件陣列
     */
    protected function getResponseDocumentation(): array
    {
        return [
            'success' => [
                'status' => 'success',
                'data' => [
                    'server_status' => [
                        'uptime' => [
                            'raw' => 'up 5 days, 3:42',
                            'status' => 'available'
                        ],
                        'memory_usage' => [
                            'current' => '128.5 MB',
                            'peak' => '256.2 MB',
                            'limit' => '512M'
                        ],
                        'disk_usage' => [
                            'total' => '100 GB',
                            'used' => '45.2 GB',
                            'free' => '54.8 GB',
                            'usage_percent' => 45.2,
                            'status' => 'normal'
                        ],
                        'load_average' => [
                            '1min' => 0.5,
                            '5min' => 0.3,
                            '15min' => 0.2,
                            'status' => 'available'
                        ],
                        'timestamp' => '2024-01-01T00:00:00.000000Z'
                    ]
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '無法取得伺服器狀態',
                'error_code' => 'SERVER_STATUS_ERROR'
            ]
        ];
    }

    /**
     * 取得使用範例
     * 
     * @return array 使用範例陣列
     */
    protected function getExamples(): array
    {
        return [
            [
                'title' => '取得基本伺服器狀態',
                'request' => [
                    'action_type' => 'system.server_status'
                ]
            ],
            [
                'title' => '取得詳細伺服器狀態',
                'request' => [
                    'action_type' => 'system.server_status',
                    'include_details' => true
                ]
            ]
        ];
    }

    /**
     * 取得Action的文件資訊
     * 
     * @return array 文件資訊陣列
     */
    public function getDocumentation(): array
    {
        return array_merge(parent::getDocumentation(), [
            'name' => '取得伺服器狀態',
            'description' => '取得伺服器資源使用狀況和效能指標',
        ]);
    }
}