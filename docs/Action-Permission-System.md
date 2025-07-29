# Action層級權限控制系統

## 概述

本系統實作了Action層級的權限控制機制，允許對每個API Action進行細粒度的權限管理。系統支援動態權限配置、權限繼承和靈活的權限檢查邏輯。

## 核心組件

### 1. ActionPermission模型

管理Action的權限配置，儲存在`action_permissions`資料表中。

```php
// 建立權限配置
ActionPermission::create([
    'action_type' => 'user.info',
    'required_permissions' => ['user.read'],
    'is_active' => true,
    'description' => '取得使用者資訊',
]);
```

### 2. PermissionChecker服務

負責執行權限檢查邏輯，整合Action預設權限和資料庫權限配置。

```php
// 檢查使用者是否可以執行Action
$canExecute = $permissionChecker->canExecuteAction($user, $action);
```

### 3. User模型權限方法

使用者模型提供權限管理的便利方法。

```php
// 檢查使用者權限
$user->hasPermission('user.read');
$user->hasAllPermissions(['user.read', 'user.update']);

// 管理使用者權限
$user->addPermission('admin.read');
$user->setPermissions(['user.read', 'user.update']);
```

## 權限檢查流程

1. **Action啟用檢查**：確認Action是否啟用
2. **權限配置查詢**：從資料庫查詢Action權限配置
3. **權限要求確定**：使用資料庫配置或Action預設權限
4. **使用者權限驗證**：檢查使用者是否具有所需權限
5. **結果回傳**：允許或拒絕Action執行

## 權限配置優先順序

1. **資料庫配置**：`action_permissions`表中的啟用配置
2. **Action預設**：Action類別中`getRequiredPermissions()`方法定義的權限
3. **無權限要求**：如果沒有配置且Action無預設權限，則允許執行

## 使用方法

### 在Action中定義權限

```php
class GetUserInfoAction extends BaseAction
{
    public function getRequiredPermissions(): array
    {
        return ['user.read'];
    }
    
    // ... 其他方法
}
```

### 動態配置權限

```php
// 使用PermissionChecker服務
$permissionChecker->setActionPermissions(
    'user.info',
    ['user.read', 'admin.read'],
    '需要管理員權限的使用者資訊查詢'
);
```

### 使用命令列管理權限

```bash
# 列出所有權限配置
php artisan action:permissions list

# 查看特定Action權限
php artisan action:permissions show user.info

# 設定Action權限
php artisan action:permissions set user.info --permissions=user.read --permissions=admin.read --description="更新的權限"

# 移除Action權限配置
php artisan action:permissions remove user.info

# 從檔案同步權限配置
php artisan action:permissions sync --file=config/action_permissions.json
```

## 權限配置檔案格式

```json
{
  "user.info": {
    "permissions": ["user.read"],
    "description": "取得使用者資訊",
    "is_active": true
  },
  "user.list": {
    "permissions": ["user.list", "admin.read"],
    "description": "取得使用者清單（需要管理員權限）",
    "is_active": true
  },
  "system.ping": {
    "permissions": [],
    "description": "系統連線測試（無權限要求）",
    "is_active": true
  }
}
```

## 使用者權限管理

### 權限類型範例

- `user.read`：讀取使用者資訊
- `user.update`：更新使用者資料
- `user.list`：列出使用者清單
- `user.create`：建立新使用者
- `user.delete`：刪除使用者
- `admin.read`：管理員讀取權限
- `admin.write`：管理員寫入權限
- `system.read`：系統資訊讀取
- `system.config`：系統配置管理

### 使用者類型範例

```php
// 一般使用者
$user->setPermissions([
    'user.read',
    'user.update',
    'user.change_password',
]);

// 進階使用者
$user->setPermissions([
    'user.read',
    'user.update',
    'user.change_password',
    'user.list',
    'system.read',
]);

// 管理員使用者
$user->makeAdmin();
$user->setPermissions([
    'user.read', 'user.update', 'user.create', 'user.delete',
    'admin.read', 'admin.write',
    'system.read', 'system.config',
]);
```

## API回應

### 權限不足回應

```json
{
    "status": "error",
    "message": "權限不足，無法執行此Action",
    "error_code": "INSUFFICIENT_PERMISSIONS"
}
```

### 成功執行回應

```json
{
    "status": "success",
    "message": "Action執行成功",
    "data": {
        // Action執行結果
    }
}
```

## 測試

系統包含完整的測試套件：

- **單元測試**：ActionPermission模型和PermissionChecker服務
- **整合測試**：完整API流程的權限檢查
- **功能測試**：各種權限場景的驗證

```bash
# 執行權限相關測試
php artisan test tests/Unit/Models/ActionPermissionTest.php
php artisan test tests/Unit/Services/PermissionCheckerTest.php
php artisan test tests/Feature/PermissionIntegrationTest.php
```

## 最佳實踐

1. **最小權限原則**：只給予使用者執行必要功能的最小權限
2. **權限分組**：將相關權限組織成邏輯群組
3. **動態配置**：使用資料庫配置來靈活調整權限要求
4. **日誌記錄**：記錄權限檢查失敗的情況以便監控
5. **測試覆蓋**：為所有權限場景編寫測試

## 故障排除

### 常見問題

1. **權限檢查失敗**：檢查使用者權限配置和Action權限要求
2. **配置不生效**：確認權限配置的`is_active`狀態
3. **測試失敗**：檢查測試資料的權限設定

### 除錯工具

```bash
# 檢查使用者權限
php artisan tinker
>>> $user = User::find(1);
>>> $user->getPermissions();

# 檢查Action權限配置
>>> use App\Models\ActionPermission;
>>> ActionPermission::findByActionType('user.info');
```

## 擴展功能

系統設計支援未來的擴展：

- 角色權限系統整合
- 權限繼承機制
- 時間限制權限
- 條件式權限檢查
- 權限快取機制