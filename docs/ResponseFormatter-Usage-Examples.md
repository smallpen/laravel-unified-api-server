# ResponseFormatter 使用範例

## 概述

ResponseFormatter 是統一API回應格式的核心服務，確保所有API回應都遵循相同的結構和格式。

## 基本使用方法

### 依賴注入

```php
use App\Contracts\ResponseFormatterInterface;

class YourController extends Controller
{
    public function __construct(
        private ResponseFormatterInterface $responseFormatter
    ) {}
}
```

### 成功回應

```php
// 基本成功回應
$response = $this->responseFormatter->success(['user_id' => 123]);

// 自訂訊息的成功回應
$response = $this->responseFormatter->success(
    ['user_id' => 123], 
    '使用者資料取得成功'
);

// 包含額外元資料的成功回應
$response = $this->responseFormatter->success(
    ['user_id' => 123], 
    '使用者資料取得成功',
    ['version' => '1.0', 'cache' => true]
);
```

回應格式：
```json
{
    "status": "success",
    "message": "使用者資料取得成功",
    "data": {
        "user_id": 123
    },
    "meta": {
        "version": "1.0",
        "cache": true
    },
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

### 錯誤回應

```php
// 基本錯誤回應
$response = $this->responseFormatter->error(
    '使用者不存在', 
    'USER_NOT_FOUND'
);

// 包含詳細錯誤資訊的回應
$response = $this->responseFormatter->error(
    '驗證失敗', 
    'VALIDATION_ERROR',
    ['email' => ['電子郵件格式不正確']]
);
```

回應格式：
```json
{
    "status": "error",
    "message": "驗證失敗",
    "error_code": "VALIDATION_ERROR",
    "details": {
        "email": ["電子郵件格式不正確"]
    },
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

### 分頁回應

```php
$data = [
    ['id' => 1, 'name' => '項目1'],
    ['id' => 2, 'name' => '項目2'],
];

$pagination = [
    'current_page' => 1,
    'per_page' => 10,
    'total' => 25,
    'last_page' => 3,
    'from' => 1,
    'to' => 10,
];

$response = $this->responseFormatter->paginated($data, $pagination);
```

回應格式：
```json
{
    "status": "success",
    "message": "資料取得成功",
    "data": [
        {"id": 1, "name": "項目1"},
        {"id": 2, "name": "項目2"}
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 25,
        "last_page": 3,
        "from": 1,
        "to": 10,
        "has_more_pages": true
    },
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

### 驗證錯誤回應

```php
$errors = [
    'email' => ['電子郵件為必填欄位', '電子郵件格式不正確'],
    'password' => ['密碼長度至少8個字元'],
];

$response = $this->responseFormatter->validationError($errors);
```

### 大量資料回應

```php
// 當資料量較大時，系統會自動提供壓縮建議
$largeData = array_fill(0, 1000, ['id' => 1, 'data' => str_repeat('x', 1000)]);

$response = $this->responseFormatter->largeDataResponse($largeData);
```

當資料量超過限制時，回應會包含額外的 meta 資訊：
```json
{
    "status": "success",
    "message": "資料取得成功",
    "data": [...],
    "meta": {
        "data_size": 1048576,
        "compression_recommended": true,
        "suggestion": "資料量較大，建議使用分頁或啟用 HTTP 壓縮"
    },
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

## 靜態方法使用

如果不需要依賴注入，可以使用靜態方法：

```php
use App\Services\ResponseFormatter;

// 快速建立成功回應
$response = ResponseFormatter::makeSuccess(['data' => 'value']);

// 快速建立錯誤回應
$response = ResponseFormatter::makeError('錯誤訊息', 'ERROR_CODE');

// 快速建立分頁回應
$response = ResponseFormatter::makePaginated($data, $pagination);

// 快速建立驗證錯誤回應
$response = ResponseFormatter::makeValidationError($errors);

// 快速建立大量資料回應
$response = ResponseFormatter::makeLargeDataResponse($largeData);
```

## 自訂請求ID和時間戳記

```php
$formatter = new ResponseFormatter();

// 設定自訂請求ID
$formatter->setRequestId('custom-request-id-123');

// 設定自訂時間戳記
$formatter->setTimestamp('2024-01-01T12:00:00Z');

// 使用自訂設定建立回應
$response = $formatter->success(['data' => 'value']);
```

## 在控制器中的完整範例

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Contracts\ResponseFormatterInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExampleController extends Controller
{
    public function __construct(
        private ResponseFormatterInterface $responseFormatter
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            // 取得資料
            $data = ['users' => [['id' => 1, 'name' => '使用者1']]];
            
            // 回傳成功回應
            return response()->json(
                $this->responseFormatter->success($data, '資料取得成功')
            );
            
        } catch (\Exception $e) {
            // 回傳錯誤回應
            return response()->json(
                $this->responseFormatter->error('系統錯誤', 'SYSTEM_ERROR'),
                500
            );
        }
    }
}
```

## 最佳實踐

1. **一致性**：始終使用 ResponseFormatter 來格式化API回應
2. **錯誤碼**：使用有意義的錯誤代碼，如 `USER_NOT_FOUND`、`VALIDATION_ERROR`
3. **訊息**：提供清晰、使用者友善的錯誤訊息
4. **分頁**：對於大量資料，優先使用分頁而非一次性回傳所有資料
5. **日誌**：在錯誤回應時記錄詳細的日誌資訊
6. **測試**：為所有使用 ResponseFormatter 的控制器撰寫測試

## 錯誤處理建議

```php
// 在 Action 中使用
public function execute(Request $request, User $user): array
{
    try {
        // 業務邏輯
        $result = $this->processData($request->all());
        
        return $result; // ResponseFormatter 會在控制器層處理格式化
        
    } catch (ValidationException $e) {
        // 讓控制器處理驗證錯誤
        throw $e;
    } catch (\Exception $e) {
        // 記錄錯誤並重新拋出
        $this->logError('Action執行失敗', [
            'error' => $e->getMessage(),
            'user_id' => $user->id,
        ]);
        
        throw $e;
    }
}
```