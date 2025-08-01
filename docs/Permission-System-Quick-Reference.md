# 權限系統快速參考指南

## 快速開始

### 1. 建立 Token

```bash
# 建立具有特定權限的 Token
php artisan token:manage create \
  --user=2 \
  --name="我的 Token" \
  --permissions=system.server_status \
  --permissions=admin.read

# 建立無限制 Token（使用使用者基礎權限）
php artisan token:manage create \
  --user=2 \
  --name="完整權限 Token"
```

### 2. 使用 API

```bash
curl -X POST http://localhost:8080/api/ \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action_type": "system.server_status"}'
```

## 權限層級

| 層級 | 用途 | 範例 |
|------|------|------|
| **使用者基礎權限** | 定義使用者的權限上限 | `["admin.read", "system.server_status"]` |
| **Token 限制權限** | 細粒度控制特定 Token | `["system.server_status"]` |
| **Action 權限要求** | 定義 Action 所需權限 | `["system.server_status", "admin.read"]` |

## 常用命令

### Token 管理
```bash
# 查看 Token 資訊
php artisan token:manage info --token="YOUR_TOKEN"

# 列出使用者 Token
php artisan token:manage list --user=2

# 撤銷 Token
php artisan token:manage revoke --token="YOUR_TOKEN"

# 清理過期 Token
php artisan token:manage cleanup
```

### Action 管理
```bash
# 列出所有 Action
php artisan action:list

# 檢查 Action 狀態
php artisan action:check system.server_status

# 重新整理 Action 註冊
php artisan action:refresh
```

## 權限類型

### 使用者權限
- `user.read` - 讀取使用者資訊
- `user.update` - 更新使用者資料
- `user.list` - 列出使用者清單
- `user.create` - 建立新使用者
- `user.delete` - 刪除使用者
- `user.change_password` - 修改密碼

### 管理員權限
- `admin.read` - 管理員讀取權限
- `admin.write` - 管理員寫入權限
- `admin.delete` - 管理員刪除權限

### 系統權限
- `system.read` - 系統資訊讀取
- `system.server_status` - 伺服器狀態查詢
- `system.config` - 系統配置管理

## 常見錯誤

| 錯誤代碼 | 說明 | 解決方法 |
|----------|------|----------|
| `UNAUTHORIZED` | Token 無效或過期 | 檢查 Token 是否正確且未過期 |
| `INSUFFICIENT_PERMISSIONS` | 權限不足 | 檢查 Token 權限是否包含所需權限 |
| `ACTION_NOT_FOUND` | Action 不存在 | 執行 `php artisan action:refresh` |
| `ACTION_DISABLED` | Action 已停用 | 檢查 Action 是否啟用 |

## 除錯技巧

### 檢查權限
```php
// 在 tinker 中檢查
php artisan tinker

// 檢查使用者基礎權限
>>> $user = User::find(2);
>>> $user->permissions;

// 檢查 Token 權限
>>> $tokenService = app('App\Services\TokenService');
>>> $user = $tokenService->validateToken('YOUR_TOKEN');
>>> $user->permissions;

// 檢查權限檢查結果
>>> $checker = app('App\Services\PermissionChecker');
>>> $action = app('App\Services\ActionRegistry')->resolve('system.server_status');
>>> $checker->canExecuteAction($user, $action);
```

### 查看日誌
```bash
# 即時查看權限相關日誌
tail -f storage/logs/laravel.log | grep -E "(權限檢查|Permission)"
```

## 最佳實踐

### ✅ 建議做法
- 使用多個 `--permissions` 參數建立 Token
- 為每個 Token 設定明確的名稱和用途
- 定期清理過期的 Token
- 使用最小權限原則

### ❌ 避免做法
- 不要使用逗號分隔的權限字串
- 不要給予過多不必要的權限
- 不要在程式碼中硬編碼 Token
- 不要忽略 Token 的過期時間

## 權限配置範例

### 監控系統 Token
```bash
php artisan token:manage create \
  --user=2 \
  --name="監控系統" \
  --permissions=system.server_status \
  --permissions=admin.read \
  --days=30
```

### 第三方整合 Token
```bash
php artisan token:manage create \
  --user=2 \
  --name="第三方 API" \
  --permissions=user.info \
  --days=90
```

### 管理後台 Token
```bash
php artisan token:manage create \
  --user=2 \
  --name="管理後台" \
  --permissions=user.read \
  --permissions=user.update \
  --permissions=user.list \
  --permissions=admin.read
```

## API 回應格式

### 成功回應
```json
{
  "status": "success",
  "message": "Action執行成功",
  "data": { /* Action 結果 */ },
  "timestamp": "2025-08-01T12:00:00.000Z",
  "request_id": "uuid"
}
```

### 錯誤回應
```json
{
  "status": "error",
  "message": "權限不足，無法執行此Action",
  "error_code": "INSUFFICIENT_PERMISSIONS",
  "details": [],
  "timestamp": "2025-08-01T12:00:00.000Z",
  "request_id": "uuid"
}
```