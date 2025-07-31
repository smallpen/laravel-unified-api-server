# 統一API Server Docker配置

本目錄包含統一API Server的完整Docker配置，支援開發和生產環境。

## 目錄結構

```
docker/
├── nginx/                 # Nginx配置
│   ├── nginx.conf        # 主配置檔案
│   ├── conf.d/           # 虛擬主機配置
│   │   ├── default.conf  # HTTP配置
│   │   └── ssl.conf      # HTTPS配置
│   └── ssl/              # SSL憑證目錄
├── php/                  # PHP配置
│   ├── php.ini          # PHP配置檔案
│   └── php-fpm.conf     # PHP-FPM配置
├── mysql/               # MySQL配置
│   ├── my.cnf           # MySQL配置檔案
│   └── init/            # 初始化腳本
├── redis/               # Redis配置
│   └── redis.conf       # Redis配置檔案
├── supervisor/          # Supervisor配置
│   └── supervisord.conf # 進程管理配置
└── scripts/             # 管理腳本
    ├── start.sh         # 啟動腳本
    ├── stop.sh          # 停止腳本
    ├── rebuild.sh       # 重建腳本
    ├── health-check.sh  # 健康檢查
    ├── logs.sh          # 日誌查看
    ├── backup.sh        # 備份腳本
    ├── restore.sh       # 還原腳本
    ├── generate-ssl.sh  # SSL憑證生成
    └── setup-env.sh     # 環境設定
```

## 快速開始

### 1. 使用管理腳本（推薦）

```bash
# 啟動開發環境
./manage.sh start

# 啟動生產環境
./manage.sh start production

# 檢查服務狀態
./manage.sh status

# 查看日誌
./manage.sh logs

# 停止服務
./manage.sh stop
```

### 2. 直接使用Docker Compose

```bash
# 開發環境
docker compose up -d

# 生產環境
docker compose -f docker compose.yml -f docker compose.prod.yml up -d
```

## 環境配置

### 開發環境特性

- 啟用除錯模式
- 包含PhpMyAdmin和Mailpit
- 程式碼即時同步
- 詳細日誌記錄

### 生產環境特性

- 優化效能設定
- 多容器副本
- 資源限制
- 安全性強化
- 移除開發工具

## 服務說明

### Nginx (Web Server)
- **連接埠**: 80 (HTTP), 443 (HTTPS)
- **功能**: 反向代理、SSL終止、靜態檔案服務
- **配置**: `docker/nginx/`

### Laravel (應用程式)
- **連接埠**: 9000 (內部)
- **功能**: API服務、業務邏輯處理
- **管理**: Supervisor進程管理

### MySQL (資料庫)
- **連接埠**: 3306
- **版本**: 8.0
- **配置**: 優化效能設定

### Redis (快取)
- **連接埠**: 6379
- **功能**: Session存儲、快取、佇列

### PhpMyAdmin (開發工具)
- **連接埠**: 8080
- **用途**: 資料庫管理介面

### Mailpit (開發工具)
- **連接埠**: 8025 (Web), 1025 (SMTP)
- **用途**: 郵件測試

## 環境變數

主要環境變數在`.env`檔案中配置：

```bash
# Docker相關
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443
DB_EXTERNAL_PORT=3306
REDIS_EXTERNAL_PORT=6379
TIMEZONE=Asia/Taipei

# 資料庫
DB_DATABASE=unified_api
DB_USERNAME=api_user
DB_PASSWORD=api_password
DB_ROOT_PASSWORD=root_password

# SSL
SSL_ENABLED=false
SSL_REDIRECT=false
```

## SSL配置

### 生成自簽名憑證（開發用）

```bash
./manage.sh ssl
```

### 使用正式憑證（生產用）

1. 將憑證檔案放置到`docker/nginx/ssl/`目錄
2. 更新`docker/nginx/conf.d/ssl.conf`中的憑證路徑
3. 在`.env`中設定`SSL_ENABLED=true`

## 備份與還原

### 建立備份

```bash
# 開發環境備份
./manage.sh backup

# 生產環境備份
./manage.sh backup production
```

### 還原備份

```bash
# 還原到開發環境
./manage.sh restore ./backups/unified_api_backup_20240101_120000.tar.gz

# 還原到生產環境
./manage.sh restore ./backups/unified_api_backup_20240101_120000.tar.gz production
```

## 監控與日誌

### 健康檢查

```bash
./manage.sh status
```

### 查看日誌

```bash
# 查看所有服務日誌
./manage.sh logs

# 查看特定服務日誌
./manage.sh logs development nginx

# 查看生產環境日誌
./manage.sh logs production
```

### 日誌位置

- **Nginx**: `/var/log/nginx/`
- **PHP-FPM**: `/var/log/php-fpm-*.log`
- **Laravel**: `storage/logs/laravel.log`
- **MySQL**: `/var/log/mysql/`
- **Supervisor**: `/var/log/supervisor/`

## 效能優化

### 生產環境優化

1. **PHP OPcache**: 啟用並優化設定
2. **Nginx快取**: 靜態檔案快取和Gzip壓縮
3. **MySQL**: InnoDB緩衝池和查詢優化
4. **Redis**: 記憶體管理和持久化設定

### 資源限制

生產環境設定了資源限制：
- **Nginx**: 512MB記憶體，0.5 CPU
- **Laravel**: 1GB記憶體，1.0 CPU
- **MySQL**: 2GB記憶體，1.0 CPU
- **Redis**: 512MB記憶體，0.5 CPU

## 安全性

### 安全措施

1. **網路隔離**: 使用Docker網路隔離服務
2. **最小權限**: 非root使用者執行應用程式
3. **安全標頭**: 設定各種HTTP安全標頭
4. **速率限制**: API請求速率限制
5. **SSL/TLS**: 支援HTTPS和安全設定

### 安全檢查清單

- [ ] 更改預設密碼
- [ ] 啟用SSL憑證
- [ ] 設定防火牆規則
- [ ] 定期更新映像
- [ ] 監控異常存取

## 故障排除

### 常見問題

1. **容器無法啟動**
   ```bash
   docker compose logs [服務名稱]
   ```

2. **資料庫連線失敗**
   ```bash
   ./manage.sh status
   docker compose exec database mysql -u root -p
   ```

3. **權限問題**
   ```bash
   sudo chown -R $USER:$USER storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

4. **SSL憑證問題**
   ```bash
   ./manage.sh ssl
   ```

### 重置環境

```bash
# 完全重置（會刪除所有資料）
./manage.sh stop development true
./manage.sh rebuild development
```

## 維護

### 定期維護任務

1. **日誌輪轉**: 定期清理舊日誌
2. **備份**: 定期建立資料備份
3. **更新**: 定期更新Docker映像
4. **監控**: 檢查系統資源使用情況

### 更新流程

```bash
# 1. 建立備份
./manage.sh backup production

# 2. 停止服務
./manage.sh stop production

# 3. 更新程式碼
git pull

# 4. 重建服務
./manage.sh rebuild production

# 5. 驗證服務
./manage.sh status production
```

## 支援

如有問題，請檢查：

1. Docker和Docker Compose版本
2. 系統資源是否充足
3. 網路連線是否正常
4. 日誌檔案中的錯誤訊息

更多資訊請參考專案文件或聯繫開發團隊。