# Laravel統一API系統文件總覽

## 概述

歡迎使用Laravel統一API系統！本系統提供了一個統一的API接口，支援Bearer Token驗證、Action模式處理、自動文件生成等功能。

## 文件結構

### 📚 使用指南
- **[API使用範例和最佳實踐指南](API-Usage-Examples.md)** - 完整的API呼叫範例和最佳實踐
- **[Action開發指南和範本](Action-Development-Guide.md)** - Action開發的完整指南和範本
- **[ResponseFormatter使用範例](ResponseFormatter-Usage-Examples.md)** - 回應格式化服務的使用方法

### 🔧 系統管理
- **[系統部署和維護文件](System-Deployment-Guide.md)** - 完整的部署和維護指南
- **[系統維護指南](System-Maintenance-Guide.md)** - 日常維護操作指南
- **[部署檢查清單](Deployment-Checklist.md)** - 部署前的檢查項目

### 🛠️ 功能說明
- **[Action權限系統](Action-Permission-System.md)** - 權限控制系統的使用方法
- **[文件生成器使用指南](Documentation-Generator-Usage.md)** - API文件自動生成功能
- **[Swagger UI使用指南](Swagger-UI-Usage-Guide.md)** - API文件介面使用方法
- **[例外處理系統](Exception-Handling-System.md)** - 錯誤處理機制說明

### 🚨 故障排除
- **[故障排除和常見問題解答](Troubleshooting-FAQ.md)** - 常見問題的解決方案

## 快速開始

### 1. 環境設定

```bash
# 複製專案
git clone https://github.com/your-org/laravel-unified-api-server.git
cd laravel-unified-api-server

# 設定環境變數
cp .env.example .env

# 啟動Docker環境
docker compose up -d

# 安裝依賴和初始化
docker compose exec laravel composer install
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
```

### 2. 第一個API呼叫

```bash
# 測試系統連線
curl -X POST http://localhost:8000/api/ \
  -H "Content-Type: application/json" \
  -d '{"action_type": "system.ping"}'
```

### 3. 查看API文件

訪問 http://localhost:8000/api/docs 查看完整的API文件。

## 系統架構

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   API Client    │───▶│  Nginx Proxy    │───▶│ Laravel App     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                        │
                       ┌─────────────────┐             │
                       │  Redis Cache    │◀────────────┤
                       └─────────────────┘             │
                                                        │
                       ┌─────────────────┐             │
                       │ MySQL Database  │◀────────────┘
                       └─────────────────┘
```

## 核心功能

### 🔐 Bearer Token驗證
- 安全的API存取控制
- Token過期管理
- 使用者權限檢查

### 🎯 Action模式處理
- 統一的API入口點
- 模組化的業務邏輯處理
- 自動Action註冊和發現

### 📖 自動文件生成
- 從程式碼自動生成API文件
- Swagger UI整合
- 即時文件更新

### 🛡️ 權限控制系統
- Action層級權限管理
- 動態權限配置
- 細粒度存取控制

### 📊 監控和日誌
- 完整的API請求日誌
- 系統健康檢查
- 效能監控

## 開發工作流程

### 1. 建立新的Action

```bash
# 使用Artisan命令建立Action
php artisan make:action User/CreateUserAction

# 實作Action邏輯
# 參考 Action開發指南
```

### 2. 設定權限

```bash
# 設定Action權限
php artisan action:permissions set user.create --permissions=user.create

# 查看權限配置
php artisan action:permissions list
```

### 3. 測試API

```bash
# 建立測試Token
php artisan tinker
>>> $user = App\Models\User::first();
>>> $token = $user->createToken('test-token');
>>> echo $token->plainTextToken;

# 測試API呼叫
curl -X POST http://localhost:8000/api/ \
  -H "Authorization: Bearer your-token-here" \
  -d '{"action_type": "user.create", "name": "測試使用者"}'
```

### 4. 查看文件

```bash
# 生成API文件
php artisan api:generate-docs

# 訪問Swagger UI
open http://localhost:8000/api/docs
```

## 部署環境

### 開發環境
- Docker Compose本地開發
- 熱重載和除錯支援
- 完整的日誌輸出

### 測試環境
- 自動化測試執行
- 效能基準測試
- 安全性掃描

### 生產環境
- 高可用性部署
- 負載均衡配置
- 監控和警報系統

## 最佳實踐

### 🔒 安全性
- 使用HTTPS進行所有API通訊
- 定期輪換API Token
- 實施適當的權限控制
- 定期進行安全性掃描

### ⚡ 效能
- 使用Redis快取頻繁查詢的資料
- 實施API速率限制
- 優化資料庫查詢
- 使用CDN加速靜態資源

### 🧪 測試
- 為所有Action撰寫單元測試
- 實施整合測試
- 進行負載測試
- 定期執行安全測試

### 📝 文件
- 保持API文件與程式碼同步
- 提供清晰的使用範例
- 記錄所有配置選項
- 維護變更日誌

## 支援和貢獻

### 取得幫助
1. 查看[故障排除指南](Troubleshooting-FAQ.md)
2. 搜尋現有的GitHub Issues
3. 建立新的Issue並提供詳細資訊

### 貢獻指南
1. Fork專案並建立功能分支
2. 撰寫測試並確保通過
3. 更新相關文件
4. 提交Pull Request

### 版本發布
- 遵循語義化版本控制
- 維護詳細的變更日誌
- 提供升級指南

## 授權條款

本專案採用MIT授權條款，詳見LICENSE檔案。

## 聯絡資訊

- **專案維護者**：[維護者姓名]
- **電子郵件**：admin@your-domain.com
- **GitHub**：https://github.com/your-org/laravel-unified-api-server
- **文件網站**：https://docs.your-domain.com

---

**最後更新**：2024年1月
**文件版本**：1.0.0