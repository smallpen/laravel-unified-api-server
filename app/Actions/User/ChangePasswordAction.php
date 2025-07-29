<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * 變更密碼Action
 * 
 * 提供使用者變更密碼的功能，需要驗證舊密碼
 */
class ChangePasswordAction extends BaseAction
{
    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'user.change_password';
    }

    /**
     * 執行變更密碼的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 處理結果陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('new_password');

        // 驗證當前密碼
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \Exception('當前密碼不正確', 422);
        }

        // 檢查新密碼是否與當前密碼相同
        if (Hash::check($newPassword, $user->password)) {
            throw new \Exception('新密碼不能與當前密碼相同', 422);
        }

        // 更新密碼
        $user->password = Hash::make($newPassword);
        $user->save();

        $this->logInfo('使用者密碼變更成功', [
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
        ]);

        return [
            'message' => '密碼變更成功',
            'updated_at' => $user->updated_at->toISOString()
        ];
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['user.change_password'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|min:8'
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
            'current_password.required' => '請提供當前密碼',
            'current_password.string' => '當前密碼必須為字串',
            'new_password.required' => '請提供新密碼',
            'new_password.string' => '新密碼必須為字串',
            'new_password.min' => '新密碼至少需要8個字元',
            'new_password.confirmed' => '新密碼確認不符',
            'new_password_confirmation.required' => '請提供新密碼確認',
            'new_password_confirmation.string' => '新密碼確認必須為字串',
            'new_password_confirmation.min' => '新密碼確認至少需要8個字元'
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
            'current_password' => [
                'type' => 'string',
                'required' => true,
                'description' => '當前密碼',
                'example' => 'oldpassword123'
            ],
            'new_password' => [
                'type' => 'string',
                'required' => true,
                'description' => '新密碼',
                'example' => 'newpassword123',
                'min_length' => 8
            ],
            'new_password_confirmation' => [
                'type' => 'string',
                'required' => true,
                'description' => '新密碼確認',
                'example' => 'newpassword123',
                'min_length' => 8
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
                    'message' => '密碼變更成功',
                    'updated_at' => '2024-01-01T00:00:00.000000Z'
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '當前密碼不正確',
                'error_code' => 'INVALID_CURRENT_PASSWORD'
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
                'title' => '變更密碼',
                'request' => [
                    'action_type' => 'user.change_password',
                    'current_password' => 'oldpassword123',
                    'new_password' => 'newpassword123',
                    'new_password_confirmation' => 'newpassword123'
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
            'name' => '變更密碼',
            'description' => '變更使用者密碼，需要驗證當前密碼',
        ]);
    }
}