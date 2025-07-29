<?php

namespace App\Actions\System;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * 取得系統資訊Action
 * 
 * 提供系統基本資訊和狀態的查詢功能
 */
class GetSystemInfoAction extends BaseAction
{
    /**
     * 執行取得系統資訊的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 系統資訊陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        // 取得要查詢的資訊類型
        $infoType = $request->input('type', 'basic');

        $systemInfo = [];

        switch ($infoType) {
            case 'basic':
                $systemInfo = $this->getBasicInfo();
                break;
            case 'stats':
                $systemInfo = $this->getStatsInfo();
                break;
            case 'health':
                $systemInfo = $this->getHealthInfo();
                break;
            default:
                // 這個情況不應該發生，因為驗證已經檢查過了
                throw new \Exception('不支援的資訊類型', 422);
        }

        return [
            'system_info' => $systemInfo,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 取得基本系統資訊
     * 
     * @return array 基本系統資訊
     */
    private function getBasicInfo(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale')
        ];
    }

    /**
     * 取得統計資訊
     * 
     * @return array 統計資訊
     */
    private function getStatsInfo(): array
    {
        return [
            'total_users' => User::count(),
            'active_users_today' => User::whereDate('updated_at', today())->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)
                                         ->whereYear('created_at', now()->year)
                                         ->count(),
            'database_size' => $this->getDatabaseSize()
        ];
    }

    /**
     * 取得健康檢查資訊
     * 
     * @return array 健康檢查資訊
     */
    private function getHealthInfo(): array
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth()
        ];

        $overallStatus = collect($health)->every(fn($status) => $status === 'healthy') 
                        ? 'healthy' 
                        : 'unhealthy';

        return [
            'overall_status' => $overallStatus,
            'checks' => $health
        ];
    }

    /**
     * 檢查資料庫健康狀態
     * 
     * @return string 健康狀態
     */
    private function checkDatabaseHealth(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * 檢查快取健康狀態
     * 
     * @return string 健康狀態
     */
    private function checkCacheHealth(): string
    {
        try {
            cache()->put('health_check', 'test', 1);
            $value = cache()->get('health_check');
            return $value === 'test' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * 檢查儲存空間健康狀態
     * 
     * @return string 健康狀態
     */
    private function checkStorageHealth(): string
    {
        try {
            $freeSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            return $usagePercent < 90 ? 'healthy' : 'warning';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * 取得資料庫大小（簡化版本）
     * 
     * @return string 資料庫大小
     */
    private function getDatabaseSize(): string
    {
        try {
            // 這是一個簡化的實作，實際可能需要根據資料庫類型調整
            return 'N/A';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'system.info';
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['system.read'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'type' => 'sometimes|string|in:basic,stats,health'
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
            'type.string' => '資訊類型必須為字串',
            'type.in' => '資訊類型必須為：basic、stats 或 health'
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
            'type' => [
                'type' => 'string',
                'required' => false,
                'description' => '資訊類型：basic（基本資訊）、stats（統計資料）、health（健康檢查）',
                'example' => 'basic',
                'default' => 'basic',
                'enum' => ['basic', 'stats', 'health']
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
            'success_basic' => [
                'status' => 'success',
                'data' => [
                    'system_info' => [
                        'app_name' => 'Laravel API Server',
                        'app_version' => '1.0.0',
                        'laravel_version' => '10.x',
                        'php_version' => '8.2.0',
                        'environment' => 'production',
                        'timezone' => 'Asia/Taipei',
                        'locale' => 'zh_TW'
                    ],
                    'timestamp' => '2024-01-01T00:00:00.000000Z'
                ]
            ],
            'success_stats' => [
                'status' => 'success',
                'data' => [
                    'system_info' => [
                        'total_users' => 1250,
                        'active_users_today' => 45,
                        'new_users_this_month' => 123,
                        'database_size' => '2.5 GB'
                    ],
                    'timestamp' => '2024-01-01T00:00:00.000000Z'
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '不支援的資訊類型',
                'error_code' => 'INVALID_INFO_TYPE'
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
                'title' => '取得基本系統資訊',
                'request' => [
                    'action_type' => 'system.info',
                    'type' => 'basic'
                ]
            ],
            [
                'title' => '取得統計資料',
                'request' => [
                    'action_type' => 'system.info',
                    'type' => 'stats'
                ]
            ],
            [
                'title' => '健康檢查',
                'request' => [
                    'action_type' => 'system.info',
                    'type' => 'health'
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
            'name' => '取得系統資訊',
            'description' => '取得系統基本資訊、統計資料或健康檢查結果',
        ]);
    }
}