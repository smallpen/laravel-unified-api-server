# Laravel 儲存目錄問題排除指南

## 問題描述

在 Docker 容器部署時，可能會遇到以下錯誤：

```
In ViewClearCommand.php line 59:
View path not found.
```

這個錯誤會導致 Laravel 容器不斷重啟，無法正常啟動。

## 根本原因

1. **缺少必要的目錄結構**：Laravel 需要完整的 `storage/framework` 目錄結構
2. **Docker 掛載覆蓋**：當主機目錄掛載到容器時，可能會覆蓋容器內建立的目錄結構
3. **Git 忽略空目錄**：Git 預設不會追蹤空目錄，導致部署時缺少必要的目錄

## 必要的目錄結構

Laravel 應用程式需要以下目錄結構：

```
storage/
├── app/
│   ├── public/
│   └── .gitkeep
├── framework/
│   ├── cache/
│   │   ├── data/
│   │   └── .gitkeep
│   ├── sessions/
│   │   └── .gitkeep
│   ├── views/
│   │   └── .gitkeep
│   └── .gitkeep
├── logs/
└── .gitkeep

bootstrap/
├── cache/
└── .gitkeep
```

## 解決方案

### 1. 自動初始化腳本

執行以下腳本來建立完整的目錄結構：

```bash
./scripts/init-storage-structure.sh
```

### 2. 手動建立目錄

如果腳本不可用，可以手動建立：

```bash
# 建立必要目錄
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 加入 .gitkeep 檔案
touch storage/app/.gitkeep
touch storage/app/public/.gitkeep
touch storage/framework/cache/.gitkeep
touch storage/framework/sessions/.gitkeep
touch storage/framework/views/.gitkeep
touch bootstrap/cache/.gitkeep

# 設定權限
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### 3. Docker 容器修復

如果容器已經在運行但出現錯誤：

```bash
# 停止容器
docker compose stop laravel

# 建立目錄結構（在主機上）
./scripts/init-storage-structure.sh

# 重新啟動容器
docker compose up -d laravel
```

## 預防措施

### 1. 版本控制

確保所有必要的 `.gitkeep` 檔案都已加入版本控制：

```bash
git add storage/app/.gitkeep
git add storage/app/public/.gitkeep
git add storage/framework/cache/.gitkeep
git add storage/framework/sessions/.gitkeep
git add storage/framework/views/.gitkeep
git add bootstrap/cache/.gitkeep
```

### 2. 部署腳本

部署腳本 (`scripts/deploy.sh`) 已經更新，會自動檢查和建立必要的目錄結構。

### 3. Docker 啟動腳本

Docker 啟動腳本 (`docker/scripts/start.sh`) 已經加強，會在啟動時檢查並建立必要的目錄。

## 檢查清單

部署前請確認：

- [ ] `storage/framework/views` 目錄存在
- [ ] `storage/framework/cache` 目錄存在
- [ ] `storage/framework/sessions` 目錄存在
- [ ] `storage/app/public` 目錄存在
- [ ] `bootstrap/cache` 目錄存在
- [ ] 所有空目錄都有 `.gitkeep` 檔案
- [ ] 目錄權限設定為 755

## 相關檔案

- `scripts/init-storage-structure.sh` - 目錄結構初始化腳本
- `scripts/deploy.sh` - 部署腳本（已更新）
- `docker/scripts/start.sh` - Docker 啟動腳本（已更新）
- `Dockerfile` - Docker 映像檔建構檔案

## 故障排除

如果問題持續存在：

1. 檢查容器日誌：`docker compose logs laravel`
2. 進入容器檢查目錄：`docker compose exec laravel ls -la /var/www/html/storage/framework/`
3. 檢查權限：`docker compose exec laravel ls -la /var/www/html/storage/`
4. 重新建構映像檔：`docker compose build --no-cache laravel`