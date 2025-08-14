<?php

namespace App\Actions;

use App\Actions\BaseAction;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GetSampleDataAction
 * 
 * TODO: 請在此處添加Action的描述
 */
class GetSampleDataAction extends BaseAction
{
    /**
     * 執行Action的處理邏輯
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

        // TODO: 在此處實作您的業務邏輯
        $user_id = $request->input('user_id');
        $ssid = $request->input('ssid');

        $result = DB::connection('another_db')->select('CALL sp_query_user(?, ?)', [
            $user_id,
            $ssid
        ]);


        // 記錄執行日誌
        $this->logInfo('Action執行成功', [
            'user_id' => $user->id,
            'request_data' => $request->all()
        ]);

        // 回傳處理結果
        return [
            'message' => '操作成功',
            // TODO: 回傳您的資料
            'data' => $result[0]
        ];
    }

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼
     */
    public function getActionType(): string
    {
        return 'sample.info';
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return [];
    }

    /**
     * 取得驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [
            // TODO: 定義您的驗證規則
            // 'field_name' => 'required|string|max:255',
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
            // TODO: 自訂驗證錯誤訊息
            // 'field_name.required' => '欄位名稱為必填項目',
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
            // TODO: 定義參數文件
            // 'field_name' => [
            //     'type' => 'string',
            //     'required' => true,
            //     'description' => '欄位描述',
            //     'example' => '範例值'
            // ]
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
                    'message' => '操作成功',
                    'data' => [
                        // TODO: 定義成功回應的資料結構
                    ]
                ]
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息',
                'error_code' => 'ERROR_CODE'
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
                'title' => '基本使用範例',
                'request' => [
                    'action_type' => 'sample.info',
                    // TODO: 添加範例參數
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
            'name' => 'GetSampleDataAction',
            'description' => 'TODO: 請在此處添加Action的詳細描述',
        ]);
    }
}