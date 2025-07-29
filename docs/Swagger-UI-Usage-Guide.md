# Swagger UI 使用指南

## 概述

本系統整合了完整的 Swagger UI 介面，提供互動式的 API 文件瀏覽和測試功能。所有 API 文件都會自動從 Action 類別生成，並支援即時更新。

## 功能特色

### 🎯 核心功能

- **自動文件生成**：從 Action 類別自動生成 OpenAPI 3.0 規格文件
- **互動式介面**：完整的 Swagger UI 介面，支援直接測試 API
- **即時更新**：當 Action 變更時自動更新文件
- **多格式輸出**：支援 JSON、OpenAPI 規格等多種格式
- **狀態監控**：即時監控文件生成狀態和系統健康度

### 🔄 即時更新機制

系統採用事件驅動的即時更新機制：

1. **Action 註冊事件**：當新增或修改 Action 時觸發
2. **自動快取清除**：事件監聽器自動清除文件快取
3. **背景重新生成**：大量變更時在背景預先生成文件
4. **前端自動檢測**：Swagger UI 每 30 秒檢查更新

## 存取方式

### 主要介面

```
GET /api/docs/
```

開啟完整的 Swagger UI 介面，包含：
- API 基本資訊
- 互動式文件瀏覽
- 即時狀態監控
- 文件重新生成功能

### API 端點

#### 1. OpenAPI 規格文件

```
GET /api/docs/openapi.json
```

取得符合 OpenAPI 3.0 規範的 JSON 格式文件，可用於：
- Swagger UI 載入
- 第三方工具整合
- 自動化測試工具

#### 2. 完整文件資料

```
GET /api/docs/json
```

取得包含詳細統計資訊的完整文件資料：

```json
{
  "status": "success",
  "message": "API文件取得成功",
  "data": {
    "info": {
      "title": "統一API Server",
      "description": "統一API伺服器系統",
      "version": "1.0.0"
    },
    "actions": {
      "user.info": {
        "name": "取得使用者資訊",
        "description": "取得當前使用者的詳細資訊",
        "parameters": {...},
        "responses": {...}
      }
    },
    "statistics": {
      "total_actions": 7,
      "successful_generations": 7,
      "failed_generations": 0,
      "generation_time": "0.74ms"
    }
  }
}
```

#### 3. Action 摘要列表

```
GET /api/docs/actions
```

取得所有 Action 的摘要資訊：

```json
{
  "status": "success",
  "data": {
    "actions": {
      "user.info": {
        "name": "取得使用者資訊",
        "description": "取得當前使用者的詳細資訊",
        "version": "1.0.0",
        "enabled": true,
        "parameter_count": 0,
        "example_count": 1
      }
    },
    "total_count": 7
  }
}
```

#### 4. 特定 Action 文件

```
GET /api/docs/actions/{actionType}
```

取得指定 Action 的詳細文件：

```json
{
  "status": "success",
  "data": {
    "action_type": "user.info",
    "class_name": "App\\Actions\\User\\GetUserInfoAction",
    "name": "取得使用者資訊",
    "description": "取得當前使用者的詳細資訊",
    "parameters": {},
    "responses": {
      "success": {
        "status": "success",
        "message": "使用者資訊取得成功",
        "data": {
          "user": {
            "id": 1,
            "name": "使用者名稱",
            "email": "user@example.com"
          }
        }
      }
    },
    "examples": [
      {
        "title": "基本使用範例",
        "request": {
          "action_type": "user.info"
        },
        "response": {
          "status": "success",
          "data": {...}
        }
      }
    ]
  }
}
```

#### 5. 文件狀態監控

```
GET /api/docs/status
```

取得文件系統的即時狀態：

```json
{
  "status": "success",
  "data": {
    "is_up_to_date": true,
    "last_generated": "2024-01-01T12:00:00Z",
    "total_actions": 7,
    "successful_generations": 7,
    "failed_generations": 0,
    "warnings_count": 0,
    "generation_time": "0.74ms",
    "cache_status": "active"
  }
}
```

#### 6. 重新生成文件

```
POST /api/docs/regenerate
```

手動觸發文件重新生成：

```json
{
  "status": "success",
  "message": "API文件重新生成成功",
  "data": {
    "documentation": {...},
    "statistics": {
      "generation_time": "1.23ms",
      "total_actions": 7,
      "successful_generations": 7
    }
  }
}
```

#### 7. Action 變更歷史

```
GET /api/docs/changes
```

取得 Action 變更歷史和摘要：

```json
{
  "status": "success",
  "data": {
    "recent_changes": [],
    "action_summary": {...},
    "last_scan": "2024-01-01T12:00:00Z",
    "total_actions": 7
  }
}
```

#### 8. 文件驗證

```
GET /api/docs/validate/{actionType}
```

驗證特定 Action 的文件完整性：

```json
{
  "status": "success",
  "data": {
    "valid": true,
    "errors": [],
    "warnings": [
      "使用預設描述，建議提供具體的Action描述"
    ]
  }
}
```

#### 9. 生成統計資訊

```
GET /api/docs/statistics
```

取得詳細的文件生成統計資訊。

## 使用範例

### 1. 基本瀏覽

1. 開啟瀏覽器，前往 `http://your-domain/api/docs/`
2. 查看 API 基本資訊和狀態指示器
3. 瀏覽 Swagger UI 介面中的 API 端點
4. 點擊任意端點查看詳細參數和回應格式

### 2. 測試 API

1. 在 Swagger UI 中點擊 "Try it out" 按鈕
2. 填入必要的參數（如 Bearer Token）
3. 輸入 `action_type` 和其他參數
4. 點擊 "Execute" 執行請求
5. 查看實際的 API 回應

### 3. 監控文件狀態

- 觀察右上角的狀態指示器：
  - 🟢 **正常**：文件生成正常，無錯誤
  - 🟡 **警告**：有少量警告或非關鍵錯誤
  - 🔴 **錯誤**：文件生成失敗或有嚴重錯誤

### 4. 手動重新整理

- 點擊 "🔄 重新整理文件" 按鈕
- 系統會重新掃描所有 Action 並更新文件
- 完成後會自動重新載入頁面

### 5. 自動更新

- 系統每 30 秒自動檢查文件更新
- 當檢測到變更時會顯示通知並自動重新載入
- 支援鍵盤快捷鍵 `Ctrl+R` 或 `F5` 手動重新整理

## 開發者指南

### Action 文件註解

為了生成完整的 API 文件，Action 類別應該實作 `getDocumentation()` 方法：

```php
public function getDocumentation(): array
{
    return [
        'name' => '取得使用者資訊',
        'description' => '取得當前登入使用者的詳細資訊，包括基本資料和權限設定',
        'version' => '1.0.0',
        'enabled' => true,
        'required_permissions' => ['user.read'],
        'parameters' => [
            'include_permissions' => [
                'type' => 'boolean',
                'required' => false,
                'description' => '是否包含使用者權限資訊',
                'default' => false,
            ],
        ],
        'responses' => [
            'success' => [
                'status' => 'success',
                'message' => '使用者資訊取得成功',
                'data' => [
                    'user' => [
                        'id' => 1,
                        'name' => '使用者名稱',
                        'email' => 'user@example.com',
                        'created_at' => '2024-01-01T00:00:00Z',
                    ],
                ],
            ],
            'error' => [
                'status' => 'error',
                'message' => '無法取得使用者資訊',
                'error_code' => 'USER_NOT_FOUND',
            ],
        ],
        'examples' => [
            [
                'title' => '基本使用範例',
                'description' => '取得基本使用者資訊',
                'request' => [
                    'action_type' => 'user.info',
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                        ],
                    ],
                ],
            ],
            [
                'title' => '包含權限資訊',
                'description' => '取得使用者資訊並包含權限設定',
                'request' => [
                    'action_type' => 'user.info',
                    'include_permissions' => true,
                ],
                'response' => [
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                            'permissions' => ['user.read', 'user.write'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}
```

### 事件監聽

系統會自動觸發以下事件：

```php
// Action 註冊時
event(new ActionRegistryUpdated('register', ['user.info']));

// Action 自動發現時
event(new ActionRegistryUpdated('discover', ['user.info', 'user.update']));
```

監聽器會自動清除文件快取並重新生成文件。

### 自訂配置

可以在 `DocumentationGenerator` 中自訂 API 基本資訊：

```php
$generator->setApiInfo([
    'title' => '我的 API 系統',
    'description' => '自訂的 API 描述',
    'version' => '2.0.0',
    'contact' => [
        'name' => '技術支援',
        'email' => 'support@mycompany.com',
    ],
]);
```

## 故障排除

### 常見問題

1. **Swagger UI 無法載入**
   - 檢查路由是否正確註冊
   - 確認視圖檔案是否存在
   - 檢查 CDN 資源是否可存取

2. **OpenAPI 規格錯誤**
   - 檢查 Action 的 `getDocumentation()` 方法
   - 驗證回傳的陣列格式
   - 查看應用程式日誌

3. **文件未即時更新**
   - 檢查事件監聽器是否正確註冊
   - 確認快取是否正常清除
   - 手動觸發重新生成

4. **狀態指示器顯示錯誤**
   - 檢查 Action 類別是否有語法錯誤
   - 確認所有 Action 都正確實作介面
   - 查看文件驗證結果

### 除錯指令

```bash
# 清除快取
php artisan cache:clear
php artisan config:clear

# 檢查路由
php artisan route:list --name=api.docs

# 測試文件生成
php artisan tinker
>>> app(\App\Services\DocumentationGenerator::class)->generateDocumentation()

# 觸發 Action 自動發現
>>> app(\App\Services\ActionRegistry::class)->autoDiscoverActions()
```

## 效能優化

### 快取策略

- 文件生成結果會自動快取
- 只有在 Action 變更時才會清除快取
- 大量變更時會在背景預先生成文件

### 監控建議

- 定期檢查文件生成統計資訊
- 監控生成時間和錯誤率
- 設定適當的快取過期時間

## 安全考量

- API 文件端點不需要 Bearer Token 驗證
- 敏感資訊不會包含在文件中
- 支援 CORS 設定以限制存取來源

## 總結

Swagger UI 整合提供了完整的 API 文件解決方案，支援自動生成、即時更新和互動式測試。透過事件驅動的架構，確保文件始終保持最新狀態，大幅提升開發效率和 API 使用體驗。