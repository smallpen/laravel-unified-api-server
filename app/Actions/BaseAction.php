<?php

namespace App\Actions;

use App\Contracts\ActionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\User;

/**
 * Action基礎抽象類別
 * 
 * 提供Action的基本實作和通用功能
 * 開發者可以繼承此類別來快速建立新的Action
 */
abstract class BaseAction implements ActionInterface
{
    /**
     * Action版本號
     * 
     * @var string
     */
    protected string $version = '1.0.0';

    /**
     * Action是否啟用
     * 
     * @var bool
     */
    protected bool $enabled = true;

    /**
     * 驗證請求參數
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @return bool 驗證是否通過
     * @throws \Illuminate\Validation\ValidationException 當驗證失敗時拋出例外
     */
    public function validate(Request $request): bool
    {
        $rules = $this->getValidationRules();
        $messages = $this->getValidationMessages();

        if (empty($rules)) {
            return true;
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * 取得Action的版本資訊
     * 
     * @return string 版本號
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * 檢查Action是否啟用
     * 
     * @return bool 是否啟用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 取得此Action所需的權限清單
     * 
     * 預設不需要特殊權限，子類別可以覆寫此方法
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array
    {
        return [];
    }

    /**
     * 取得Action的文件資訊
     * 
     * 提供預設的文件結構，子類別應該覆寫此方法
     * 
     * @return array 文件資訊陣列
     */
    public function getDocumentation(): array
    {
        return [
            'name' => $this->getActionType(),
            'description' => '此Action尚未提供描述',
            'version' => $this->getVersion(),
            'enabled' => $this->isEnabled(),
            'required_permissions' => $this->getRequiredPermissions(),
            'parameters' => $this->getParameterDocumentation(),
            'responses' => $this->getResponseDocumentation(),
            'examples' => $this->getExamples(),
        ];
    }

    /**
     * 取得驗證規則
     * 
     * 子類別應該覆寫此方法來定義參數驗證規則
     * 
     * @return array 驗證規則陣列
     */
    protected function getValidationRules(): array
    {
        return [];
    }

    /**
     * 取得驗證錯誤訊息
     * 
     * 子類別可以覆寫此方法來自訂驗證錯誤訊息
     * 
     * @return array 錯誤訊息陣列
     */
    protected function getValidationMessages(): array
    {
        return [];
    }

    /**
     * 取得參數文件
     * 
     * 子類別應該覆寫此方法來提供參數說明
     * 
     * @return array 參數文件陣列
     */
    protected function getParameterDocumentation(): array
    {
        return [];
    }

    /**
     * 取得回應文件
     * 
     * 子類別應該覆寫此方法來提供回應格式說明
     * 
     * @return array 回應文件陣列
     */
    protected function getResponseDocumentation(): array
    {
        return [
            'success' => [
                'status' => 'success',
                'message' => '操作成功',
                'data' => '具體資料內容',
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息',
                'error_code' => '錯誤代碼',
            ],
        ];
    }

    /**
     * 取得使用範例
     * 
     * 子類別應該覆寫此方法來提供使用範例
     * 
     * @return array 使用範例陣列
     */
    protected function getExamples(): array
    {
        return [];
    }

    /**
     * 記錄Action執行日誌
     * 
     * @param string $level 日誌等級
     * @param string $message 日誌訊息
     * @param array $context 上下文資料
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $context['action_type'] = $this->getActionType();
        $context['action_version'] = $this->getVersion();

        \Log::log($level, $message, $context);
    }

    /**
     * 記錄資訊日誌
     * 
     * @param string $message 日誌訊息
     * @param array $context 上下文資料
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * 記錄錯誤日誌
     * 
     * @param string $message 日誌訊息
     * @param array $context 上下文資料
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * 記錄警告日誌
     * 
     * @param string $message 日誌訊息
     * @param array $context 上下文資料
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
}