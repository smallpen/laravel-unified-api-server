<?php

namespace App\Actions\System;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * 系統Ping測試Action
 * 
 * 用於測試API系統是否正常運作
 */
class PingAction extends BaseAction
{
    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'system.ping';
    }

    /**
     * 執行Action處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 處理結果陣列
     */
    public function execute(Request $request, User $user): array
    {
        $this->logInfo('系統Ping測試執行', [
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
        ]);

        return [
            'message' => 'pong',
            'timestamp' => now()->toISOString(),
            'server_time' => now()->format('Y-m-d H:i:s'),
            'user_id' => $user->id,
            'system_status' => 'healthy',
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
            'name' => '系統Ping測試',
            'description' => '測試API系統是否正常運作，回傳系統狀態資訊',
            'parameters' => [],
            'responses' => [
                'success' => [
                    'status' => 'success',
                    'message' => '操作成功',
                    'data' => [
                        'message' => 'pong',
                        'timestamp' => '2024-01-01T00:00:00.000000Z',
                        'server_time' => '2024-01-01 08:00:00',
                        'user_id' => 1,
                        'system_status' => 'healthy',
                    ],
                ],
            ],
            'examples' => [
                [
                    'title' => '基本Ping測試',
                    'request' => [
                        'action_type' => 'system.ping',
                    ],
                    'response' => [
                        'status' => 'success',
                        'message' => '操作成功',
                        'data' => [
                            'message' => 'pong',
                            'timestamp' => '2024-01-01T00:00:00.000000Z',
                            'server_time' => '2024-01-01 08:00:00',
                            'user_id' => 1,
                            'system_status' => 'healthy',
                        ],
                    ],
                ],
            ],
        ]);
    }
}