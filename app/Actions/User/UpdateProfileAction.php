<?php

namespace App\Actions\User;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * 更新使用者個人資料Action
 * 
 * 提供更新使用者姓名、電子郵件等基本資訊的功能
 */
class UpdateProfileAction extends BaseAction
{
    /**
     * 執行更新使用者個人資料的處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 更新結果陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array
    {
        // 驗證請求參數
        $this->validate($request);

        // 取得要更新的資料
        $updateData = [];
        
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }

        if ($request->has('email')) {
            $email = $request->input('email');
            
            // 檢查電子郵件是否已被其他使用者使用
            $existingUser = User::where('email', $email)
                               ->where('id', '!=', $user->id)
                               ->first();
            
            if ($existingUser) {
                throw new \Exception('此電子郵件已被其他使用者使用', 422);
            }
            
            $updateData['email'] = $email;
            // 如果更新電子郵件，需要重新驗證
            $updateData['email_verified_at'] = null;
        }

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->input('password'));
        }

        // 如果沒有要更新的資料
        if (empty($updateData)) {
            throw new \Exception('沒有提供要更新的資料', 422);
        }

        // 更新使用者資料
        $user->fill($updateData);
        $user->save();

        // 重新載入使用者資料
        $user->refresh();

        // 回傳更新後的使用者資訊
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
            'message' => '個人資料更新成功'
        ];
    }

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'user.update';
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return ['user.update'];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:255',
            'email' => 'sometimes|email|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'password_confirmation' => 'sometimes|string|min:8'
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
            'name.string' => '姓名必須為字串',
            'name.min' => '姓名至少需要2個字元',
            'name.max' => '姓名不能超過255個字元',
            'email.email' => '請提供有效的電子郵件地址',
            'email.max' => '電子郵件地址不能超過255個字元',
            'password.string' => '密碼必須為字串',
            'password.min' => '密碼至少需要8個字元',
            'password.confirmed' => '密碼確認不符',
            'password_confirmation.string' => '確認密碼必須為字串',
            'password_confirmation.min' => '確認密碼至少需要8個字元'
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
            'name' => [
                'type' => 'string',
                'required' => false,
                'description' => '使用者姓名',
                'example' => '李四',
                'min_length' => 2,
                'max_length' => 255
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'description' => '電子郵件地址',
                'example' => 'newemail@example.com',
                'format' => 'email'
            ],
            'password' => [
                'type' => 'string',
                'required' => false,
                'description' => '新密碼',
                'min_length' => 8,
                'note' => '密碼將會被加密儲存'
            ],
            'password_confirmation' => [
                'type' => 'string',
                'required' => false,
                'description' => '確認新密碼',
                'note' => '當提供password時必須提供此欄位'
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
                        'name' => '李四',
                        'email' => 'newemail@example.com',
                        'email_verified_at' => null,
                        'updated_at' => '2024-01-01T00:00:00.000000Z'
                    ],
                    'message' => '個人資料更新成功'
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '此電子郵件已被其他使用者使用',
                'error_code' => 'EMAIL_ALREADY_EXISTS'
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
                'title' => '更新姓名',
                'request' => [
                    'action_type' => 'user.update',
                    'name' => '李四'
                ]
            ],
            [
                'title' => '更新電子郵件',
                'request' => [
                    'action_type' => 'user.update',
                    'email' => 'newemail@example.com'
                ]
            ],
            [
                'title' => '更新密碼',
                'request' => [
                    'action_type' => 'user.update',
                    'password' => 'newpassword123',
                    'password_confirmation' => 'newpassword123'
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
            'name' => '更新使用者個人資料',
            'description' => '更新當前使用者的姓名、電子郵件或密碼',
        ]);
    }
}