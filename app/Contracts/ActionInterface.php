<?php

namespace App\Contracts;

use Illuminate\Http\Request;
use App\Models\User;

/**
 * Action處理器介面
 * 
 * 所有Action處理器都必須實作此介面
 * 定義了Action執行、權限檢查、文件生成和驗證的標準方法
 */
interface ActionInterface
{
    /**
     * 執行Action處理邏輯
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @param \App\Models\User $user 已驗證的使用者
     * @return array 處理結果陣列
     * @throws \Exception 當處理過程發生錯誤時拋出例外
     */
    public function execute(Request $request, User $user): array;

    /**
     * 取得此Action所需的權限清單
     * 
     * @return array 權限名稱陣列
     */
    public function getRequiredPermissions(): array;

    /**
     * 取得Action的文件資訊
     * 
     * 用於自動生成API文件
     * 
     * @return array 包含以下鍵值的陣列：
     *               - name: Action名稱
     *               - description: Action描述
     *               - parameters: 參數規格陣列
     *               - responses: 回應格式陣列
     *               - examples: 使用範例陣列
     */
    public function getDocumentation(): array;

    /**
     * 驗證請求參數
     * 
     * @param \Illuminate\Http\Request $request 請求物件
     * @return bool 驗證是否通過
     * @throws \Illuminate\Validation\ValidationException 當驗證失敗時拋出例外
     */
    public function validate(Request $request): bool;

    /**
     * 取得Action的唯一識別碼
     * 
     * @return string Action類型識別碼（如：user.info, user.update）
     */
    public function getActionType(): string;

    /**
     * 檢查Action是否啟用
     * 
     * @return bool 是否啟用
     */
    public function isEnabled(): bool;

    /**
     * 取得Action的版本資訊
     * 
     * @return string 版本號（如：1.0.0）
     */
    public function getVersion(): string;
}