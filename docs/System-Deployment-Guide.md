# 系統部署和維護文件

## 概述

本文件提供Laravel統一API系統的完整部署指南，包含開發環境設定、生產環境部署、系統維護和監控等內容。

## 系統需求

### 最低系統需求

- **作業系統**：Ubuntu 20.04+ / CentOS 8+ / Windows 10+
- **PHP**：8.1+
- **資料庫**：MySQL 8.0+ / PostgreSQL 13+
- **記憶體**：最少 2GB RAM
- **儲存空間**：最少 10GB 可用空間
- **網路**：穩定的網際網路連線

### 建議系統需求

- **作業系統**：Ubuntu 22.04 LTS
- **PHP**：8.2+
- **資料庫**：MySQL 8.0+
- **記憶體**：4GB+ RAM
- **儲存空間**：50GB+ SSD
- **CPU**：2+ 核心

### 必要軟體

- Docker 20.10+
- Docker Compose 2.0+
- Git 2.30+
- Nginx 1.20+（如不使用Docker）

## 開發環境設定

### 1. 複製專案

```bash
# 複製專案到本地
git clone https://github.com/your-org/laravel-unified-api-server.git
cd laravel-unified-api-server

# 複製環境設定檔案
cp .env.example .env
```

### 2. 設定環境變數

編輯 `.env` 檔案：

```env
# 應用程式設定
APP_NAME="Laravel Unified API Server"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# 資料庫設定
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=api_server
DB_USERNAME=root
DB_PASSWORD=secret

# Redis設定
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# 日誌設定
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# API設定
API_RATE_LIMIT=60
API_TOKEN_EXPIRE_HOURS=24
API_DOCUMENTATION_ENABLED=true
```

### 3. 使用Docker啟動開發環境

```bash
# 建立並啟動容器
docker-compose up -d

# 安裝PHP依賴
docker-compose exec laravel composer install

# 生成應用程式金鑰
docker-compose exec laravel php artisan key:generate

# 執行資料庫遷移
docker-compose exec laravel php artisan migrate

# 建立儲存連結
docker-compose exec laravel php artisan storage:link

# 清除快取
docker-compose exec laravel php artisan config:clear
docker-compose exec laravel php artisan cache:clear
```

### 4. 驗證安裝

```bash
# 檢查容器狀態
docker-compose ps

# 測試API端點
curl -X POST http://localhost:8000/api/ \
  -H "Content-Type: application/json" \
  -d '{"action_type": "system.ping"}'

# 查看API文件
open http://localhost:8000/api/docs
```

## 生產環境部署

### 1. 伺服器準備

#### Ubuntu/Debian系統

```bash
# 更新系統
sudo apt update && sudo apt upgrade -y

# 安裝必要軟體
sudo apt install -y curl wget git unzip

# 安裝Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# 安裝Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# 重新登入以套用Docker群組權限
logout
```

#### CentOS/RHEL系統

```bash
# 更新系統
sudo yum update -y

# 安裝必要軟體
sudo yum install -y curl wget git unzip

# 安裝Docker
sudo yum install -y yum-utils
sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo yum install -y docker-ce docker-ce-cli containerd.io
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker $USER

# 安裝Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 2. 部署腳本

建立部署腳本 `deploy.sh`：

```bash
#!/bin/bash

set -e

# 設定變數
PROJECT_DIR="/var/www/laravel-unified-api-server"
BACKUP_DIR="/var/backups/api-server"
LOG_FILE="/var/log/deploy.log"

# 記錄函數
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

# 建立備份
backup_database() {
    log "建立資料庫備份..."
    mkdir -p $BACKUP_DIR
    docker-compose exec -T database mysqldump -u root -psecret api_server > $BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql
}

# 部署主要流程
deploy() {
    log "開始部署流程..."
    
    # 切換到專案目錄
    cd $PROJECT_DIR
    
    # 備份資料庫
    backup_database
    
    # 拉取最新程式碼
    log "拉取最新程式碼..."
    git pull origin main
    
    # 停止服務
    log "停止現有服務..."
    docker-compose down
    
    # 重建映像檔
    log "重建Docker映像檔..."
    docker-compose build --no-cache
    
    # 啟動服務
    log "啟動服務..."
    docker-compose up -d
    
    # 等待服務啟動
    sleep 30
    
    # 執行遷移
    log "執行資料庫遷移..."
    docker-compose exec -T laravel php artisan migrate --force
    
    # 清除快取
    log "清除快取..."
    docker-compose exec -T laravel php artisan config:cache
    docker-compose exec -T laravel php artisan route:cache
    docker-compose exec -T laravel php artisan view:cache
    
    # 重新啟動服務
    log "重新啟動服務..."
    docker-compose restart
    
    # 健康檢查
    log "執行健康檢查..."
    sleep 10
    
    if curl -f -s http://localhost/api/health > /dev/null; then
        log "部署成功！"
    else
        log "健康檢查失敗，請檢查服務狀態"
        exit 1
    fi
}

# 執行部署
deploy
```

### 3. 生產環境設定

#### 環境變數設定

```env
# 生產環境 .env
APP_NAME="Laravel Unified API Server"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# 資料庫設定
DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=api_server_prod
DB_USERNAME=api_user
DB_PASSWORD=secure-password-here

# Redis設定
REDIS_HOST=redis
REDIS_PASSWORD=secure-redis-password
REDIS_PORT=6379

# 日誌設定
LOG_CHANNEL=daily
LOG_LEVEL=warning

# 快取設定
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# API設定
API_RATE_LIMIT=1000
API_TOKEN_EXPIRE_HOURS=168
API_DOCUMENTATION_ENABLED=false

# 郵件設定
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

#### Docker Compose生產設定

建立 `docker-compose.prod.yml`：

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    container_name: api_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/prod.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
      - ./storage/logs/nginx:/var/log/nginx
    depends_on:
      - laravel
    networks:
      - api_network

  laravel:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    container_name: api_laravel
    restart: unless-stopped
    volumes:
      - ./storage:/var/www/html/storage
      - ./bootstrap/cache:/var/www/html/bootstrap/cache
    environment:
      - APP_ENV=production
    depends_on:
      - database
      - redis
    networks:
      - api_network

  database:
    image: mysql:8.0
    container_name: api_database
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: api_server_prod
      MYSQL_USER: api_user
      MYSQL_PASSWORD: secure-password-here
      MYSQL_ROOT_PASSWORD: secure-root-password
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/mysql/prod.cnf:/etc/mysql/conf.d/custom.cnf:ro
    networks:
      - api_network

  redis:
    image: redis:alpine
    container_name: api_redis
    restart: unless-stopped
    command: redis-server --requirepass secure-redis-password
    volumes:
      - redis_data:/data
    networks:
      - api_network

volumes:
  db_data:
  redis_data:

networks:
  api_network:
    driver: bridge
```

### 4. SSL憑證設定

#### 使用Let's Encrypt

```bash
# 安裝Certbot
sudo apt install -y certbot python3-certbot-nginx

# 取得SSL憑證
sudo certbot --nginx -d your-domain.com

# 設定自動更新
sudo crontab -e
# 添加以下行：
# 0 12 * * * /usr/bin/certbot renew --quiet
```

#### Nginx SSL設定

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    location / {
        proxy_pass http://laravel:9000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

## 系統監控

### 1. 健康檢查端點

建立健康檢查Action：

```php
<?php

namespace App\Actions\System;

use App\Actions\BaseAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckAction extends BaseAction
{
    protected string $name = '系統健康檢查';
    protected string $description = '檢查系統各項服務的運行狀態';

    public function getRequiredPermissions(): array
    {
        return []; // 無需權限
    }

    protected function handle(array $data, User $user): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = array_reduce($checks, function ($carry, $check) {
            return $carry && $check['status'] === 'healthy';
        }, true);

        return [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'uptime' => $this->getUptime(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => '資料庫連線正常'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => '資料庫連線失敗: ' . $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'healthy', 'message' => 'Redis連線正常'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Redis連線失敗: ' . $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testFile = storage_path('app/health_check.txt');
            file_put_contents($testFile, 'test');
            unlink($testFile);
            return ['status' => 'healthy', 'message' => '儲存系統正常'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => '儲存系統異常: ' . $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            // 檢查佇列連線
            $queueSize = Redis::llen('queues:default');
            return [
                'status' => 'healthy',
                'message' => '佇列系統正常',
                'queue_size' => $queueSize
            ];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => '佇列系統異常: ' . $e->getMessage()];
        }
    }

    private function getUptime(): string
    {
        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int) explode(' ', $uptime)[0];
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return "{$days}天 {$hours}小時 {$minutes}分鐘";
    }
}
```

### 2. 監控腳本

建立監控腳本 `scripts/monitor.sh`：

```bash
#!/bin/bash

# 設定變數
API_URL="https://your-domain.com/api/"
LOG_FILE="/var/log/api-monitor.log"
ALERT_EMAIL="admin@your-domain.com"

# 記錄函數
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

# 發送警報
send_alert() {
    local message="$1"
    echo "$message" | mail -s "API系統警報" $ALERT_EMAIL
    log "警報已發送: $message"
}

# 檢查API健康狀態
check_api_health() {
    local response=$(curl -s -X POST $API_URL \
        -H "Content-Type: application/json" \
        -d '{"action_type": "system.health"}' \
        --max-time 10)
    
    if [ $? -eq 0 ]; then
        local status=$(echo $response | jq -r '.data.status // "unknown"')
        if [ "$status" = "healthy" ]; then
            log "API健康檢查通過"
            return 0
        else
            log "API健康檢查失敗: $status"
            send_alert "API系統健康檢查失敗，狀態: $status"
            return 1
        fi
    else
        log "無法連接到API端點"
        send_alert "無法連接到API端點: $API_URL"
        return 1
    fi
}

# 檢查Docker容器狀態
check_containers() {
    local unhealthy_containers=$(docker ps --filter "health=unhealthy" --format "table {{.Names}}")
    
    if [ -n "$unhealthy_containers" ]; then
        log "發現不健康的容器: $unhealthy_containers"
        send_alert "Docker容器健康檢查失敗: $unhealthy_containers"
        return 1
    else
        log "所有Docker容器運行正常"
        return 0
    fi
}

# 檢查磁碟空間
check_disk_space() {
    local usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ $usage -gt 80 ]; then
        log "磁碟空間使用率過高: ${usage}%"
        send_alert "磁碟空間警報：使用率達到 ${usage}%"
        return 1
    else
        log "磁碟空間使用率正常: ${usage}%"
        return 0
    fi
}

# 檢查記憶體使用率
check_memory() {
    local usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ $usage -gt 90 ]; then
        log "記憶體使用率過高: ${usage}%"
        send_alert "記憶體使用率警報：使用率達到 ${usage}%"
        return 1
    else
        log "記憶體使用率正常: ${usage}%"
        return 0
    fi
}

# 主要監控流程
main() {
    log "開始系統監控檢查"
    
    local failed_checks=0
    
    check_api_health || ((failed_checks++))
    check_containers || ((failed_checks++))
    check_disk_space || ((failed_checks++))
    check_memory || ((failed_checks++))
    
    if [ $failed_checks -eq 0 ]; then
        log "所有監控檢查通過"
    else
        log "監控檢查完成，發現 $failed_checks 個問題"
    fi
    
    log "監控檢查結束"
}

# 執行監控
main
```

### 3. 設定Cron任務

```bash
# 編輯crontab
sudo crontab -e

# 添加監控任務
# 每5分鐘執行一次健康檢查
*/5 * * * * /var/www/laravel-unified-api-server/scripts/monitor.sh

# 每天凌晨2點執行資料庫備份
0 2 * * * /var/www/laravel-unified-api-server/scripts/backup.sh

# 每週日凌晨3點清理日誌檔案
0 3 * * 0 /var/www/laravel-unified-api-server/scripts/cleanup.sh
```

## 系統維護

### 1. 日誌管理

#### 日誌輪轉設定

建立 `/etc/logrotate.d/api-server`：

```
/var/www/laravel-unified-api-server/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        docker-compose exec laravel php artisan queue:restart
    endscript
}
```

#### 日誌分析腳本

```bash
#!/bin/bash

# 日誌分析腳本
LOG_DIR="/var/www/laravel-unified-api-server/storage/logs"
REPORT_FILE="/tmp/log_analysis_$(date +%Y%m%d).txt"

# 分析錯誤日誌
echo "=== 錯誤統計 ===" > $REPORT_FILE
grep -c "ERROR" $LOG_DIR/laravel.log >> $REPORT_FILE

# 分析API請求統計
echo "=== API請求統計 ===" >> $REPORT_FILE
grep "Action執行成功" $LOG_DIR/laravel.log | wc -l >> $REPORT_FILE

# 分析最常見的錯誤
echo "=== 最常見錯誤 ===" >> $REPORT_FILE
grep "ERROR" $LOG_DIR/laravel.log | sort | uniq -c | sort -nr | head -10 >> $REPORT_FILE

# 發送報告
mail -s "API系統日誌分析報告" admin@your-domain.com < $REPORT_FILE
```

### 2. 資料庫維護

#### 備份腳本

```bash
#!/bin/bash

# 資料庫備份腳本
BACKUP_DIR="/var/backups/api-server"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_backup_$DATE.sql"

# 建立備份目錄
mkdir -p $BACKUP_DIR

# 執行備份
docker-compose exec -T database mysqldump \
    -u root -psecret \
    --single-transaction \
    --routines \
    --triggers \
    api_server_prod > $BACKUP_FILE

# 壓縮備份檔案
gzip $BACKUP_FILE

# 刪除7天前的備份
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "資料庫備份完成: ${BACKUP_FILE}.gz"
```

#### 資料庫優化

```bash
#!/bin/bash

# 資料庫優化腳本
docker-compose exec database mysql -u root -psecret -e "
USE api_server_prod;

-- 優化表格
OPTIMIZE TABLE users;
OPTIMIZE TABLE api_tokens;
OPTIMIZE TABLE api_logs;
OPTIMIZE TABLE action_permissions;

-- 分析表格
ANALYZE TABLE users;
ANALYZE TABLE api_tokens;
ANALYZE TABLE api_logs;
ANALYZE TABLE action_permissions;

-- 檢查表格
CHECK TABLE users;
CHECK TABLE api_tokens;
CHECK TABLE api_logs;
CHECK TABLE action_permissions;
"

echo "資料庫優化完成"
```

### 3. 效能調優

#### PHP-FPM調優

編輯 `docker/php/php-fpm.conf`：

```ini
[www]
user = www-data
group = www-data
listen = 9000
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000

request_terminate_timeout = 60
request_slowlog_timeout = 10
slowlog = /var/log/php-fpm-slow.log
```

#### MySQL調優

編輯 `docker/mysql/prod.cnf`：

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

max_connections = 200
thread_cache_size = 16
table_open_cache = 2000

slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

### 4. 安全性維護

#### 安全更新腳本

```bash
#!/bin/bash

# 安全更新腳本
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# 更新系統套件
log "更新系統套件..."
sudo apt update && sudo apt upgrade -y

# 更新Docker映像檔
log "更新Docker映像檔..."
docker-compose pull

# 重建並重啟服務
log "重建服務..."
docker-compose down
docker-compose up -d --build

# 檢查服務狀態
sleep 30
if curl -f -s http://localhost/api/health > /dev/null; then
    log "安全更新完成，服務運行正常"
else
    log "警告：服務可能存在問題"
fi
```

#### 安全掃描

```bash
#!/bin/bash

# 安全掃描腳本
SCAN_REPORT="/tmp/security_scan_$(date +%Y%m%d).txt"

echo "=== 安全掃描報告 ===" > $SCAN_REPORT
echo "掃描時間: $(date)" >> $SCAN_REPORT

# 檢查開放端口
echo "=== 開放端口 ===" >> $SCAN_REPORT
netstat -tuln >> $SCAN_REPORT

# 檢查失敗的登入嘗試
echo "=== 失敗登入嘗試 ===" >> $SCAN_REPORT
grep "Failed password" /var/log/auth.log | tail -20 >> $SCAN_REPORT

# 檢查Docker容器安全性
echo "=== Docker安全檢查 ===" >> $SCAN_REPORT
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    aquasec/trivy image laravel-unified-api-server_laravel >> $SCAN_REPORT

# 發送報告
mail -s "安全掃描報告" admin@your-domain.com < $SCAN_REPORT
```

## 故障排除

### 常見問題和解決方案

#### 1. 容器無法啟動

```bash
# 檢查容器日誌
docker-compose logs laravel

# 檢查容器狀態
docker-compose ps

# 重建容器
docker-compose down
docker-compose up -d --build
```

#### 2. 資料庫連線失敗

```bash
# 檢查資料庫容器
docker-compose logs database

# 測試資料庫連線
docker-compose exec laravel php artisan tinker
>>> DB::connection()->getPdo();

# 重啟資料庫服務
docker-compose restart database
```

#### 3. API回應緩慢

```bash
# 檢查系統資源使用
docker stats

# 檢查慢查詢日誌
docker-compose exec database mysql -u root -psecret -e "
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
"

# 清除應用程式快取
docker-compose exec laravel php artisan cache:clear
docker-compose exec laravel php artisan config:clear
```

#### 4. 記憶體不足

```bash
# 檢查記憶體使用
free -h

# 重啟服務釋放記憶體
docker-compose restart

# 調整PHP記憶體限制
# 編輯 docker/php/php.ini
memory_limit = 512M
```

### 緊急恢復程序

#### 1. 服務完全停止

```bash
# 緊急重啟所有服務
cd /var/www/laravel-unified-api-server
docker-compose down
docker-compose up -d

# 如果仍有問題，使用備份恢復
./scripts/restore_backup.sh /var/backups/api-server/latest_backup.sql.gz
```

#### 2. 資料庫損壞

```bash
# 停止應用程式
docker-compose stop laravel

# 修復資料庫
docker-compose exec database mysql -u root -psecret -e "
USE api_server_prod;
REPAIR TABLE users;
REPAIR TABLE api_tokens;
REPAIR TABLE api_logs;
"

# 如果修復失敗，從備份恢復
./scripts/restore_database.sh
```

這個部署和維護指南提供了完整的系統管理流程，確保Laravel統一API系統能夠穩定運行並及時處理各種問題。