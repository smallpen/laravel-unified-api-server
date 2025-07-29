# Laravel統一API系統

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com)

這是一個基於Laravel框架開發的統一API系統，提供標準化的API接口和完整的開發工具鏈。

## ✨ 主要功能

- 🚀 **統一API入口點** - 所有API請求通過單一端點處理
- 🔐 **Bearer Token驗證** - 安全的API存取控制
- 🎯 **Action模式處理** - 模組化的業務邏輯處理
- 📖 **自動API文件生成** - 從程式碼自動生成Swagger文件
- 🛡️ **權限控制系統** - 細粒度的Action層級權限管理
- 📊 **完整日誌記錄** - 詳細的API請求和系統日誌
- 🐳 **Docker容器化部署** - 完整的容器化解決方案
- 🔄 **自動化測試** - 單元測試、整合測試和效能測試
- 📈 **系統監控** - 健康檢查和效能監控

## 🚀 快速開始

### 環境需求

- **PHP**: 8.1+
- **MySQL**: 8.0+
- **Redis**: 6.0+
- **Docker**: 20.10+
- **Docker Compose**: 2.0+

### 安裝步驟

1. **複製專案**
```bash
git clone https://github.com/your-username/laravel-unified-api-server.git
cd laravel-unified-api-server
```

2. **設定環境變數**
```bash
cp .env.example .env
# 編輯 .env 檔案設定資料庫等配置
```

3. **啟動Docker環境**
```bash
# 使用管理腳本啟動
./manage.sh start

# 或直接使用docker-compose
docker-compose up -d
```

4. **安裝依賴並初始化**
```bash
docker-compose exec laravel composer install
docker-compose exec laravel php artisan key:generate
docker-compose exec laravel php artisan migrate
docker-compose exec laravel php artisan db:seed
```

5. **測試API**
```bash
curl -X POST http://localhost:8000/api/ \
  -H "Content-Type: application/json" \
  -d '{"action_type": "system.ping"}'
```

6. **查看API文件**
```
訪問 http://localhost:8000/api/docs
```

## 📖 API使用

### 基本請求格式

所有API請求都使用POST方法發送到 `/api/` 端點：

```json
{
    "action_type": "action.name",
    "parameter1": "value1",
    "parameter2": "value2"
}
```

### 認證

使用Bearer Token進行認證：

```bash
curl -X POST http://localhost:8000/api/ \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"action_type": "user.info", "user_id": 123}'
```

### 回應格式

成功回應：
```json
{
    "status": "success",
    "message": "操作成功",
    "data": {
        "result": "data"
    },
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

錯誤回應：
```json
{
    "status": "error",
    "message": "錯誤訊息",
    "error_code": "ERROR_CODE",
    "details": {},
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "request_id": "uuid-string"
}
```

## 🛠️ 開發

### 建立新的Action

```bash
# 使用Artisan命令建立Action
php artisan make:action User/CreateUserAction

# 設定Action權限
php artisan action:permissions set user.create --permissions=user.create
```

### 執行測試

```bash
# 執行所有測試
./manage.sh test

# 執行特定測試套件
docker-compose exec laravel php artisan test --testsuite=Unit
docker-compose exec laravel php artisan test --testsuite=Feature
```

### 生成API文件

```bash
# 生成API文件
docker-compose exec laravel php artisan api:generate-docs

# 驗證文件完整性
docker-compose exec laravel php artisan api:generate-docs --validate
```

## 📚 文件

完整的文件位於 `docs/` 目錄：

- 📋 **[文件總覽](docs/README.md)** - 完整的文件導覽
- 🔧 **[API使用範例](docs/API-Usage-Examples.md)** - 詳細的API使用指南
- 👨‍💻 **[Action開發指南](docs/Action-Development-Guide.md)** - Action開發完整指南
- 🚀 **[系統部署指南](docs/System-Deployment-Guide.md)** - 部署和維護指南
- 🔍 **[故障排除指南](docs/Troubleshooting-FAQ.md)** - 常見問題解決方案
- 🛡️ **[權限系統說明](docs/Action-Permission-System.md)** - 權限控制系統
- 📊 **[系統維護指南](docs/System-Maintenance-Guide.md)** - 日常維護操作

## 🏗️ 系統架構

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

## 🧪 測試

系統包含完整的測試套件：

- **單元測試** - 測試個別類別和方法
- **功能測試** - 測試完整的API流程
- **整合測試** - 測試系統組件間的整合
- **效能測試** - 測試系統效能和負載能力
- **安全測試** - 測試安全性和權限控制

```bash
# 執行所有測試
docker-compose exec laravel php artisan test

# 執行測試並生成覆蓋率報告
docker-compose exec laravel php artisan test --coverage
```

## 📊 監控

系統提供完整的監控功能：

- **健康檢查** - `/api/` 端點支援 `system.health` Action
- **效能監控** - 詳細的API回應時間記錄
- **錯誤追蹤** - 完整的錯誤日誌和堆疊追蹤
- **系統指標** - CPU、記憶體、磁碟使用率監控

## 🤝 貢獻

歡迎貢獻！請遵循以下步驟：

1. Fork 此專案
2. 建立功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交變更 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 開啟 Pull Request

## 📄 授權

本專案採用 MIT 授權條款 - 詳見 [LICENSE](LICENSE) 檔案

## 🙋‍♂️ 支援

如果您遇到問題或需要幫助：

1. 查看 [故障排除指南](docs/Troubleshooting-FAQ.md)
2. 搜尋現有的 [Issues](https://github.com/your-username/laravel-unified-api-server/issues)
3. 建立新的 Issue 並提供詳細資訊

## 🔄 版本歷史

- **v1.0.0** - 初始版本
  - 統一API系統
  - Bearer Token驗證
  - Action模式處理
  - 自動文件生成
  - 權限控制系統
  - Docker容器化部署

---

**開發團隊** | **文件版本**: 1.0.0 | **最後更新**: 2024年1月