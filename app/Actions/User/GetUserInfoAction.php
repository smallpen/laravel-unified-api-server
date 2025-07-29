<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;

/**
 * 取得使用者資訊Action
 * 
 * 提供取得當前使用者或指定使用者基本資訊的功能
 */
class GetUserInfoAction extends BaseAction
{
    /**
     * 執行取得使用者資訊的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 使用者資訊陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        // 取得目標使用者ID，如果未指定則使用當前使用者
        $targetUserId = $request->input('user_id', $user->id);

        // 如果要查詢其他使用者，需要檢查權限
        if ($targetUserId != $user->id) {
            // 這裡可以加入額外的權限檢查邏輯
            // 例如：只有管理員可以查看其他使用者資訊
        }

        // 查詢目標使用者
        $targetUser = User::find($targetUserId);

        if (!$targetUser) {
            throw new \Exception('找不到指定的使用者', 404);
        }

        // 回傳使用者基本資訊
        return [
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'email_verified_at' => $targetUser->email_verified_at?->toISOString(),
                'created_at' => $targetUser->created_at->toISOString(),
                'updated_at' => $targetUser->updated_at->toISOString(),
            ]
        ];
    }

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'user.info';
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['user.read'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'user_id' => 'sometimes|integer|min:1'
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
            'user_id.integer' => '使用者ID必須為整數',
            'user_id.min' => '使用者ID必須大於0'
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
            'user_id' => [
                'type' => 'integer',
                'required' => false,
                'description' => '要查詢的使用者ID，不提供則查詢當前使用者',
                'example' => 123
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
                    'user' => [
                        'id' => 1,
                        'name' => '張三',
                        'email' => 'user@example.com',
                        'email_verified_at' => '2024-01-01T00:00:00.000000Z',
                        'created_at' => '2024-01-01T00:00:00.000000Z',
                        'updated_at' => '2024-01-01T00:00:00.000000Z'
                    ]
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '找不到指定的使用者',
                'error_code' => 'USER_NOT_FOUND'
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
                'title' => '取得當前使用者資訊',
                'request' => [
                    'action_type' => 'user.info'
                ]
            ],
            [
                'title' => '取得指定使用者資訊',
                'request' => [
                    'action_type' => 'user.info',
                    'user_id' => 123
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
            'name' => '取得使用者資訊',
            'description' => '取得當前使用者或指定使用者的基本資訊',
        ]);
    }
}