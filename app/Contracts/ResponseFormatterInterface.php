<?php

namespace App\Contracts;

/**
 * 回應格式化器介面
 * 
 * 定義統一的API回應格式標準
 * 確保所有API回應都遵循相同的結構和格式
 */
interface ResponseFormatterInterface
{
    /**
     * 格式化成功回應
     * 
     * @param array $data 回應資料
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array 格式化後的回應陣列
     */
    public function success(array $data = [], string $message = '操作成功', array $meta = []): array;

    /**
     * 格式化錯誤回應
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param array $details 詳細錯誤資訊
     * @param array $meta 額外的元資料
     * @return array 格式化後的錯誤回應陣列
     */
    public function error(string $message, string $errorCode, array $details = [], array $meta = []): array;

    /**
     * 格式化分頁回應
     * 
     * @param array $data 分頁資料
     * @param array $pagination 分頁資訊
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array 格式化後的分頁回應陣列
     */
    public function paginated(array $data, array $pagination, string $message = '資料取得成功', array $meta = []): array;

    /**
     * 格式化驗證錯誤回應
     * 
     * @param array $errors 驗證錯誤陣列
     * @param string $message 錯誤訊息
     * @return array 格式化後的驗證錯誤回應陣列
     */
    public function validationError(array $errors, string $message = '請求參數驗證失敗'): array;

    /**
     * 設定回應的請求ID
     * 
     * @param string $requestId 請求唯一識別碼
     * @return self
     */
    public function setRequestId(string $requestId): self;

    /**
     * 設定回應的時間戳記
     * 
     * @param string $timestamp 時間戳記
     * @return self
     */
    public function setTimestamp(string $timestamp): self;

    /**
     * 取得目前設定的請求ID
     * 
     * @return string|null
     */
    public function getRequestId(): ?string;

    /**
     * 取得目前設定的時間戳記
     * 
     * @return string|null
     */
    public function getTimestamp(): ?string;

    /**
     * 處理大量資料回應
     * 
     * 當資料量過大時，自動建議使用分頁或提供資料壓縮提示
     * 
     * @param array $data 回應資料
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @param int $maxSize 最大資料大小限制（位元組）
     * @return array
     */
    public function largeDataResponse(array $data, string $message = '資料取得成功', array $meta = [], int $maxSize = 1048576): array;
}