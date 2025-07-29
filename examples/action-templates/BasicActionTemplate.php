<?php

namespace App\Actions\Examples;

use App\Actions\BaseAction;
use App\Models\User;

/**
 * 基本Action範本
 * 
 * 這是一個基本的Action範本，展示了Action的標準結構和實作方式。
 * 複製此檔案並修改為您的具體需求。
 */
class BasicActionTemplate extends BaseAction
{
    /**
     * Action名稱
     */
    protected string $name = '基本Action範本';

    /**
     * Action描述
     */
    protected string $description = '這是一個基本的Action範本，用於展示標準實作方式';

    /**
     * Action版本
     */
    protected string $version = '1.0.0';

    /**
     * 驗證規則
     */
    protected array $validationRules = [
        'example_param' => 'required|string|max:255',
        'optional_param' => 'nullable|integer|min:1',
    ];

    /**
     * 驗證錯誤訊息
     */
    protected array $validationMessages = [
        'example_param.required' => '範例參數為必填欄位',
        'example_param.string' => '範例參數必須為字串',
        'example_param.max' => '範例參數長度不能超過255個字元',
        'optional_param.integer' => '可選參數必須為整數',
        'optional_param.min' => '可選參數必須大於0',
    ];

    /**
     * 取得Action所需權限
     */
    public function getRequiredPermissions(): array
    {
        return ['example.read']; // 根據實際需求修改權限
    }

    /**
     * 主要業務邏輯處理
     * 
     * @param array $data 驗證後的輸入資料
     * @param User $user 已驗證的使用者
     * @return array 處理結果
     */
    protected function handle(array $data, User $user): array
    {
        // TODO: 在此實作您的業務邏輯
        
        // 範例：處理輸入資料
        $processedValue = strtoupper($data['example_param']);
        
        // 範例：執行資料庫操作
        // $model = SomeModel::create([...]);
        
        // 範例：呼叫外部服務
        // $result = $this->callExternalService($data);
        
        // 回傳處理結果
        return [
            'processed_value' => $processedValue,
            'optional_value' => $data['optional_param'] ?? null,
            'processed_at' => now()->toISOString(),
            'processed_by' => $user->id,
        ];
    }

    /**
     * 執行前置檢查（可選）
     * 
     * @param array $data 驗證後的資料
     * @param User $user 使用者物件
     * @throws \Exception 如果前置檢查失敗
     */
    protected function beforeExecute(array $data, User $user): void
    {
        // TODO: 在此實作前置檢查邏輯
        
        // 範例：檢查使用者狀態
        // if (!$user->is_active) {
        //     throw new \Exception('使用者帳號已停用');
        // }
        
        // 範例：檢查業務規則
        // if ($data['example_param'] === 'forbidden') {
        //     throw new \Exception('不允許的參數值');
        // }
    }

    /**
     * 執行後置處理（可選）
     * 
     * @param array $result 執行結果
     * @param User $user 使用者物件
     */
    protected function afterExecute(array $result, User $user): void
    {
        // TODO: 在此實作後置處理邏輯
        
        // 範例：記錄操作日誌
        // \App\Models\ActivityLog::create([
        //     'user_id' => $user->id,
        //     'action' => 'basic_action_executed',
        //     'data' => $result,
        // ]);
        
        // 範例：發送通知
        // $user->notify(new ActionCompletedNotification($result));
    }

    /**
     * 取得參數文件
     */
    protected function getParameterDocumentation(): array
    {
        return [
            'example_param' => [
                'type' => 'string',
                'required' => true,
                'description' => '範例參數，用於展示基本用法',
                'example' => 'hello world',
                'max_length' => 255,
            ],
            'optional_param' => [
                'type' => 'integer',
                'required' => false,
                'description' => '可選的整數參數',
                'example' => 42,
                'minimum' => 1,
            ],
        ];
    }

    /**
     * 取得回應文件
     */
    protected function getResponseDocumentation(): array
    {
        return [
            'success' => [
                'status' => 'success',
                'data' => [
                    'processed_value' => 'HELLO WORLD',
                    'optional_value' => 42,
                    'processed_at' => '2024-01-01T12:00:00.000000Z',
                    'processed_by' => 1,
                ],
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息描述',
                'error_code' => 'VALIDATION_ERROR',
                'details' => [
                    'example_param' => ['範例參數為必填欄位'],
                ],
            ],
        ];
    }

    /**
     * 取得使用範例
     */
    protected function getExamples(): array
    {
        return [
            [
                'title' => '基本使用範例',
                'description' => '展示基本的Action呼叫方式',
                'request' => [
                    'action_type' => 'example.basic',
                    'example_param' => 'hello world',
                    'optional_param' => 42,
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Action執行成功',
                    'data' => [
                        'processed_value' => 'HELLO WORLD',
                        'optional_value' => 42,
                        'processed_at' => '2024-01-01T12:00:00.000000Z',
                        'processed_by' => 1,
                    ],
                ],
            ],
            [
                'title' => '僅必填參數範例',
                'description' => '只提供必填參數的呼叫方式',
                'request' => [
                    'action_type' => 'example.basic',
                    'example_param' => 'test',
                ],
                'response' => [
                    'status' => 'success',
                    'message' => 'Action執行成功',
                    'data' => [
                        'processed_value' => 'TEST',
                        'optional_value' => null,
                        'processed_at' => '2024-01-01T12:00:00.000000Z',
                        'processed_by' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * 私有輔助方法範例
     * 
     * @param string $input
     * @return string
     */
    private function processInput(string $input): string
    {
        // 實作輔助邏輯
        return trim(strtoupper($input));
    }
}