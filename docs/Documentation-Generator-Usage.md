# API文件生成器使用指南

## 概述

API文件生成器是一個自動化工具，能夠掃描所有已註冊的Action類別並生成完整的API文件。支援多種輸出格式，包括JSON和OpenAPI 3.0規格。

## 核心功能

### 1. 自動文件生成
- 掃描所有實作 `ActionInterface` 的類別
- 從Action的 `getDocumentation()` 方法提取文件資訊
- 生成標準化的API文件格式

### 2. OpenAPI支援
- 匯出符合OpenAPI 3.0規格的文件
- 支援Swagger UI整合
- 包含完整的請求/回應結構定義

### 3. 文件驗證
- 檢查Action文件的完整性
- 提供錯誤和警告訊息
- 確保文件品質

## 使用方式

### 程式化使用

```php
use App\Contracts\DocumentationGeneratorInterface;

// 取得文件生成器實例
$generator = app(DocumentationGeneratorInterface::class);

// 生成完整文件
$documentation = $generator->generateDocumentation();

// 取得特定Action文件
$actionDoc = $generator->getActionDocumentation('user.info');

// 匯出OpenAPI格式
$openApiJson = $generator->exportToOpenApi();

// 取得Action摘要
$summary = $generator->getActionsSummary();

// 驗證Action文件
$validation = $generator->validateActionDocumentation('user.info');
```

### Artisan命令使用

#### 1. 顯示Action摘要
```bash
php artisan api:generate-docs --summary
```

#### 2. 驗證文件完整性
```bash
php artisan api:generate-docs --validate
```

#### 3. 生成JSON格式文件
```bash
php artisan api:generate-docs --format=json --output=api-docs.json
```

#### 4. 生成OpenAPI格式文件
```bash
php artisan api:generate-docs --format=openapi --output=openapi.json
```

#### 5. 生成特定Action文件
```bash
php artisan api:generate-docs --action=user.info --output=user-info-doc.json
```

## Action文件格式

每個Action類別都應該實作 `getDocumentation()` 方法，回傳以下格式的陣列：

```php
public function getDocumentation(): array
{
    return [
        'name' => 'Action名稱',
        'description' => 'Action詳細描述',
        'version' => '1.0.0',
        'enabled' => true,
        'required_permissions' => ['permission.name'],
        'parameters' => [
            'param_name' => [
                'type' => 'string',
                'required' => true,
                'description' => '參數描述',
                'example' => '範例值',
            ],
        ],
        'responses' => [
            'success' => [
                'status' => 'success',
                'data' => ['example' => 'data'],
            ],
            'error' => [
                'status' => 'error',
                'message' => '錯誤訊息',
                'error_code' => 'ERROR_CODE',
            ],
        ],
        'examples' => [
            [
                'title' => '範例標題',
                'request' => [
                    'action_type' => 'action.name',
                    'param' => 'value',
                ],
                'response' => [
                    'status' => 'success',
                    'data' => ['result' => 'value'],
                ],
            ],
        ],
    ];
}
```

## 生成的文件結構

### 完整文件格式
```json
{
    "info": {
        "title": "API標題",
        "description": "API描述",
        "version": "1.0.0",
        "contact": {...},
        "license": {...},
        "servers": [...]
    },
    "actions": {
        "action.type": {
            "name": "Action名稱",
            "description": "Action描述",
            "parameters": {...},
            "responses": {...},
            "examples": [...]
        }
    },
    "statistics": {
        "total_actions": 10,
        "successful_generations": 10,
        "failed_generations": 0,
        "generation_time": "15.2ms"
    },
    "generated_at": "2024-01-01T00:00:00Z"
}
```

### OpenAPI格式
生成的OpenAPI文件包含：
- 標準的OpenAPI 3.0結構
- 統一的POST接口定義
- Bearer Token安全性配置
- 標準化的回應結構
- 所有Action的枚舉值

## 最佳實踐

### 1. Action文件撰寫
- 提供清晰的名稱和描述
- 詳細說明所有參數
- 包含實際的使用範例
- 定義完整的回應格式

### 2. 文件維護
- 定期執行驗證命令檢查文件品質
- 在CI/CD流程中整合文件生成
- 保持文件與程式碼同步更新

### 3. 效能考量
- 文件生成器使用快取機制提升效能
- 在生產環境中可預先生成文件
- 避免在高頻率請求中即時生成文件

## 故障排除

### 常見問題

1. **Action未出現在文件中**
   - 確認Action類別實作了 `ActionInterface`
   - 檢查Action是否已正確註冊
   - 執行 `php artisan api:generate-docs --validate` 檢查錯誤

2. **文件格式不正確**
   - 檢查 `getDocumentation()` 方法的回傳格式
   - 使用驗證命令找出具體問題
   - 參考現有Action的實作範例

3. **OpenAPI匯出失敗**
   - 確認所有Action文件格式正確
   - 檢查JSON編碼是否有問題
   - 查看Laravel日誌檔案取得詳細錯誤資訊

### 除錯技巧

```bash
# 檢查特定Action的文件
php artisan api:generate-docs --action=problematic.action

# 驗證所有Action文件
php artisan api:generate-docs --validate

# 查看詳細統計資訊
php artisan api:generate-docs --summary
```

## 擴展功能

文件生成器支援以下擴展：

1. **自訂API資訊**
```php
$generator->setApiInfo([
    'title' => '自訂API標題',
    'version' => '2.0.0',
    'description' => '自訂描述',
]);
```

2. **快取管理**
```php
// 清除快取
$generator->clearCache();

// 強制重新生成
$generator->regenerateDocumentation();
```

3. **統計資訊**
```php
$stats = $generator->getGenerationStatistics();
```

這個文件生成器為Laravel統一API系統提供了完整的文件化解決方案，確保API文件始終與程式碼保持同步。