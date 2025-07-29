# 全域錯誤處理系統使用指南

## 概述

本系統提供了統一的API錯誤處理機制，確保所有錯誤回應都遵循標準格式，並且敏感資訊不會洩漏到錯誤回應中。

## 主要功能

### 1. 統一的錯誤回應格式

所有API錯誤都會回傳以下標準格式：

```json
{
    "status": "error",
    "message": "錯誤描述",
    "error_code": "ERROR_CODE",
    "details": {},
    "timestamp": "2024-01-01T00:00:00Z",
    "request_id": "unique-request-id"
}
```

### 2. 自定義例外類別

系統提供了多種預定義的例外類別：

#### ApiException
基礎API例外類別，所有其他例外都繼承自此類別。

```php
use App\Exceptions\ApiException;

throw new ApiException('自定義錯誤訊息', 'CUSTOM_ERROR', 400, ['field' => 'value']);
```

#### ValidationException
處理請求參數驗證失敗的情況。

```php
use App\Exceptions\ValidationException;

// 基本用法
throw new ValidationException('驗證失敗', ['email' => ['電子郵件格式不正確']]);

// 從 Laravel 驗證器建立
$exception = ValidationException::fromValidator($validator);
```

#### AuthenticationException
處理身份驗證失敗的情況。

```php
use App\Exceptions\AuthenticationException;

// 基本用法
throw new AuthenticationException('認證失敗');

// 預定義方法
throw AuthenticationException::invalidToken();
throw AuthenticationException::missingToken();
throw AuthenticationException::expiredToken();
```

#### AuthorizationException
處理權限不足的情況。

```php
use App\Exceptions\AuthorizationException;

// 基本用法
throw new AuthorizationException('權限不足');

// Action 權限不足
throw AuthorizationException::insufficientPermissions('getUserInfo', ['read:user']);
```

#### NotFoundException
處理請求的資源不存在的情況。

```php
use App\Exceptions\NotFoundException;

// 基本用法
throw new NotFoundException('資源未找到');

// Action 不存在
throw NotFoundException::actionNotFound('invalidAction');

// 路由不存在
throw NotFoundException::routeNotFound('/api/invalid');
```

#### RateLimitException
處理請求頻率過高的情況。

```php
use App\Exceptions\RateLimitException;

// 基本用法
throw new RateLimitException('請求頻率過高');

// 指定重試時間
throw RateLimitException::tooManyAttempts(60, 100);
```

### 3. 例外處理服務

`ExceptionHandlerService` 提供了便利的方法來拋出各種例外：

```php
use App\Services\ExceptionHandlerService;

class SomeController extends Controller
{
    protected ExceptionHandlerService $exceptionHandler;

    public function __construct(ExceptionHandlerService $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    public function someMethod()
    {
        // 拋出驗證錯誤
        $this->exceptionHandler->throwValidationError(['email' => ['必填欄位']]);

        // 拋出認證錯誤
        $this->exceptionHandler->throwAuthenticationError('Token 無效');

        // 拋出授權錯誤
        $this->exceptionHandler->throwAuthorizationError('權限不足');

        // 拋出未找到錯誤
        $this->exceptionHandler->throwNotFoundError('使用者不存在');

        // 拋出一般 API 錯誤
        $this->exceptionHandler->throwApiError('自定義錯誤', 'CUSTOM_ERROR', 400);
    }
}
```

### 4. 安全處理

系統會自動處理敏感資訊的洩漏問題：

- **生產環境**：隱藏詳細的錯誤資訊和堆疊追蹤
- **開發環境**：顯示完整的錯誤資訊以便除錯
- **敏感例外**：自動識別並隱藏敏感例外（如資料庫錯誤）

### 5. 日誌記錄

所有例外都會被記錄到日誌中，包含以下資訊：

- 例外類型和訊息
- 檔案位置和行號
- 請求 ID 和使用者 ID
- IP 位址和 User Agent
- 請求 URL 和方法

### 6. Laravel 內建例外處理

系統會自動處理 Laravel 的內建例外：

- `ValidationException` → 422 狀態碼
- `AuthenticationException` → 401 狀態碼
- `ModelNotFoundException` → 404 狀態碼
- `HttpException` → 對應的 HTTP 狀態碼

## 使用範例

### 在 Action 中使用

```php
use App\Contracts\ActionInterface;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthorizationException;
use Illuminate\Http\Request;

class GetUserInfoAction implements ActionInterface
{
    public function execute(Request $request, User $user): array
    {
        // 驗證參數
        if (!$request->has('user_id')) {
            throw new ValidationException('缺少必要參數', ['user_id' => ['此欄位為必填']]);
        }

        // 檢查權限
        if (!$user->can('read:user')) {
            throw AuthorizationException::insufficientPermissions('getUserInfo', ['read:user']);
        }

        // 業務邏輯...
        return ['user' => $userData];
    }
}
```

### 在控制器中使用

```php
use App\Http\Controllers\Controller;
use App\Services\ExceptionHandlerService;

class ApiController extends Controller
{
    protected ExceptionHandlerService $exceptionHandler;

    public function __construct(ExceptionHandlerService $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    public function handleRequest(Request $request)
    {
        try {
            // 處理請求邏輯
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            // 使用服務安全地處理例外
            return $this->exceptionHandler->handleExceptionSafely($e);
        }
    }
}
```

## 測試

系統包含完整的測試套件：

```bash
# 執行所有例外處理測試
php artisan test tests/Unit/Exceptions/ tests/Unit/Services/ExceptionHandlerServiceTest.php tests/Feature/ExceptionHandlingTest.php

# 執行單一測試類別
php artisan test tests/Unit/Exceptions/ApiExceptionTest.php
```

## 配置

例外處理系統會自動註冊，無需額外配置。如需自定義行為，可以：

1. 修改 `app/Exceptions/Handler.php` 中的處理邏輯
2. 擴展 `ExceptionHandlerService` 類別
3. 建立新的自定義例外類別

## 最佳實踐

1. **使用適當的例外類別**：根據錯誤類型選擇合適的例外類別
2. **提供清晰的錯誤訊息**：錯誤訊息應該對使用者友善且具有指導性
3. **包含相關的詳細資訊**：在 details 欄位中提供有助於除錯的資訊
4. **避免洩漏敏感資訊**：不要在錯誤訊息中包含密碼、Token 等敏感資料
5. **使用適當的 HTTP 狀態碼**：確保狀態碼與錯誤類型相符

## 錯誤代碼對照表

| 錯誤代碼 | 描述 | HTTP 狀態碼 |
|---------|------|------------|
| VALIDATION_ERROR | 請求參數驗證失敗 | 400/422 |
| AUTHENTICATION_ERROR | 身份驗證失敗 | 401 |
| AUTHORIZATION_ERROR | 權限不足 | 403 |
| NOT_FOUND | 資源不存在 | 404 |
| METHOD_NOT_ALLOWED | HTTP 方法不允許 | 405 |
| RATE_LIMIT_EXCEEDED | 請求頻率過高 | 429 |
| INTERNAL_SERVER_ERROR | 系統內部錯誤 | 500 |