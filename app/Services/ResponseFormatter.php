<?php

namespace App\Services;

use App\Contracts\ResponseFormatterInterface;
use Illuminate\Support\Str;

/**
 * 回應格式化器
 * 
 * 實作統一的API回應格式標準
 * 提供成功、錯誤、分頁等各種回應格式的標準化處理
 */
class ResponseFormatter implements ResponseFormatterInterface
{
    /**
     * 請求唯一識別碼
     * 
     * @var string|null
     */
    protected ?string $requestId = null;

    /**
     * 時間戳記
     * 
     * @var string|null
     */
    protected ?string $timestamp = null;

    /**
     * 建構函式
     * 
     * 自動設定預設的請求ID和時間戳記
     */
    public function __construct()
    {
        $this->requestId = Str::uuid()->toString();
        $this->timestamp = now()->toISOString();
    }

    /**
     * 格式化成功回應
     * 
     * @param array $data 回應資料
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array 格式化後的回應陣列
     */
    public function success(array $data = [], string $message = '操作成功', array $meta = []): array
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => $this->getTimestamp(),
            'request_id' => $this->getRequestId(),
        ];

        // 如果有額外的元資料，則加入回應中
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * 格式化錯誤回應
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param array $details 詳細錯誤資訊
     * @param array $meta 額外的元資料
     * @return array 格式化後的錯誤回應陣列
     */
    public function error(string $message, string $errorCode, array $details = [], array $meta = []): array
    {
        $response = [
            'status' => 'error',
            'message' => $message,
            'error_code' => $errorCode,
            'details' => $details,
            'timestamp' => $this->getTimestamp(),
            'request_id' => $this->getRequestId(),
        ];

        // 如果有額外的元資料，則加入回應中
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * 格式化分頁回應
     * 
     * @param array $data 分頁資料
     * @param array $pagination 分頁資訊
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array 格式化後的分頁回應陣列
     */
    public function paginated(array $data, array $pagination, string $message = '資料取得成功', array $meta = []): array
    {
        // 驗證分頁資訊必要欄位
        $requiredPaginationFields = ['current_page', 'per_page', 'total', 'last_page'];
        foreach ($requiredPaginationFields as $field) {
            if (!isset($pagination[$field])) {
                throw new \InvalidArgumentException("分頁資訊缺少必要欄位: {$field}");
            }
        }

        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'current_page' => (int) $pagination['current_page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => (int) $pagination['total'],
                'last_page' => (int) $pagination['last_page'],
                'from' => isset($pagination['from']) ? (int) $pagination['from'] : null,
                'to' => isset($pagination['to']) ? (int) $pagination['to'] : null,
                'has_more_pages' => $pagination['current_page'] < $pagination['last_page'],
            ],
            'timestamp' => $this->getTimestamp(),
            'request_id' => $this->getRequestId(),
        ];

        // 如果有額外的元資料，則加入回應中
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * 格式化驗證錯誤回應
     * 
     * @param array $errors 驗證錯誤陣列
     * @param string $message 錯誤訊息
     * @return array 格式化後的驗證錯誤回應陣列
     */
    public function validationError(array $errors, string $message = '請求參數驗證失敗'): array
    {
        return $this->error(
            $message,
            'VALIDATION_ERROR',
            $errors
        );
    }

    /**
     * 設定回應的請求ID
     * 
     * @param string $requestId 請求唯一識別碼
     * @return self
     */
    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * 設定回應的時間戳記
     * 
     * @param string $timestamp 時間戳記
     * @return self
     */
    public function setTimestamp(string $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * 取得目前設定的請求ID
     * 
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * 取得目前設定的時間戳記
     * 
     * @return string|null
     */
    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    /**
     * 建立新的ResponseFormatter實例
     * 
     * 提供靜態方法來快速建立實例
     * 
     * @return static
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * 快速建立成功回應
     * 
     * @param array $data 回應資料
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array
     */
    public static function makeSuccess(array $data = [], string $message = '操作成功', array $meta = []): array
    {
        return static::make()->success($data, $message, $meta);
    }

    /**
     * 快速建立錯誤回應
     * 
     * @param string $message 錯誤訊息
     * @param string $errorCode 錯誤代碼
     * @param array $details 詳細錯誤資訊
     * @param array $meta 額外的元資料
     * @return array
     */
    public static function makeError(string $message, string $errorCode, array $details = [], array $meta = []): array
    {
        return static::make()->error($message, $errorCode, $details, $meta);
    }

    /**
     * 快速建立分頁回應
     * 
     * @param array $data 分頁資料
     * @param array $pagination 分頁資訊
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @return array
     */
    public static function makePaginated(array $data, array $pagination, string $message = '資料取得成功', array $meta = []): array
    {
        return static::make()->paginated($data, $pagination, $message, $meta);
    }

    /**
     * 快速建立驗證錯誤回應
     * 
     * @param array $errors 驗證錯誤陣列
     * @param string $message 錯誤訊息
     * @return array
     */
    public static function makeValidationError(array $errors, string $message = '請求參數驗證失敗'): array
    {
        return static::make()->validationError($errors, $message);
    }

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
    public function largeDataResponse(array $data, string $message = '資料取得成功', array $meta = [], int $maxSize = 1048576): array
    {
        $dataSize = strlen(json_encode($data));
        
        // 如果資料量超過限制，在 meta 中加入壓縮建議
        if ($dataSize > $maxSize) {
            $meta['data_size'] = $dataSize;
            $meta['compression_recommended'] = true;
            $meta['suggestion'] = '資料量較大，建議使用分頁或啟用 HTTP 壓縮';
        }

        return $this->success($data, $message, $meta);
    }

    /**
     * 快速建立大量資料回應
     * 
     * @param array $data 回應資料
     * @param string $message 回應訊息
     * @param array $meta 額外的元資料
     * @param int $maxSize 最大資料大小限制（位元組）
     * @return array
     */
    public static function makeLargeDataResponse(array $data, string $message = '資料取得成功', array $meta = [], int $maxSize = 1048576): array
    {
        return static::make()->largeDataResponse($data, $message, $meta, $maxSize);
    }
}