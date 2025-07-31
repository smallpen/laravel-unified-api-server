# 故障排除和常見問題解答

## 概述

本文件提供Laravel統一API系統的常見問題解答、故障排除步驟和解決方案，幫助開發者和系統管理員快速解決問題。

## 常見問題分類

### 1. 安裝和部署問題

#### Q1: Docker容器無法啟動

**問題描述**：執行 `docker compose up` 時容器無法正常啟動

**可能原因**：
- 端口被佔用
- Docker映像檔損壞
- 環境變數設定錯誤
- 磁碟空間不足

**解決步驟**：

```bash
# 1. 檢查端口使用情況
sudo netstat -tulpn | grep :80
sudo netstat -tulpn | grep :3306

# 2. 停止並清理現有容器
docker compose down
docker system prune -f

# 3. 檢查磁碟空間
df -h

# 4. 重新建立容器
docker compose up -d --build

# 5. 查看容器日誌
docker compose logs -f
```

#### Q2: 資料庫遷移失敗

**問題描述**：執行 `php artisan migrate` 時出現錯誤

**常見錯誤訊息**：
- `SQLSTATE[HY000] [2002] Connection refused`
- `Access denied for user`
- `Unknown database`

**解決步驟**：

```bash
# 1. 檢查資料庫容器狀態
docker compose ps database

# 2. 檢查資料庫連線設定
docker compose exec laravel php artisan tinker
>>> DB::connection()->getPdo();

# 3. 手動建立資料庫
docker compose exec database mysql -u root -p
CREATE DATABASE api_server;

# 4. 重新執行遷移
docker compose exec laravel php artisan migrate:fresh
```

#### Q3: Composer依賴安裝失敗

**問題描述**：`composer install` 執行失敗或速度過慢

**解決步驟**：

```bash
# 1. 清除Composer快取
docker compose exec laravel composer clear-cache

# 2. 使用中國鏡像（如果在中國地區）
docker compose exec laravel composer config repo.packagist composer https://mirrors.aliyun.com/composer/

# 3. 增加記憶體限制
docker compose exec laravel php -d memory_limit=2G /usr/local/bin/composer install

# 4. 忽略平台要求（僅開發環境）
docker compose exec laravel composer install --ignore-platform-reqs
```

### 2. API呼叫問題

#### Q4: Bearer Token驗證失敗

**問題描述**：API請求回傳401 Unauthorized錯誤

**常見原因**：
- Token格式錯誤
- Token已過期
- Token不存在於資料庫
- 中介軟體配置錯誤

**解決步驟**：

```bash
# 1. 檢查Token格式
# 正確格式：Authorization: Bearer your-token-here

# 2. 檢查Token是否存在
docker compose exec laravel php artisan tinker
>>> use App\Models\ApiToken;
>>> ApiToken::where('token_hash', hash('sha256', 'your-token'))->first();

# 3. 建立測試Token
>>> $user = App\Models\User::first();
>>> $token = $user->createToken('test-token');
>>> echo $token->plainTextToken;

# 4. 檢查中介軟體配置
# 確認 routes/api.php 中有正確的中介軟體設定
```

**測試範例**：

```bash
# 使用正確的Token格式測試
curl -X POST http://localhost:8000/api/ \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token-here" \
  -d '{"action_type": "system.ping"}'
```

#### Q5: Action不存在錯誤

**問題描述**：API回傳 "Action not found" 錯誤

**解決步驟**：

```bash
# 1. 檢查Action是否已註冊
docker compose exec laravel php artisan action:list

# 2. 檢查Action類別是否存在
ls app/Actions/

# 3. 清除快取
docker compose exec laravel php artisan cache:clear
docker compose exec laravel php artisan config:clear

# 4. 重新註冊Action
docker compose exec laravel php artisan action:discover
```

#### Q6: 參數驗證失敗

**問題描述**：API回傳驗證錯誤，但參數看起來正確

**常見問題**：
- 參數類型不匹配
- 必填參數缺失
- 參數名稱拼寫錯誤

**除錯步驟**：

```bash
# 1. 檢查Action的驗證規則
docker compose exec laravel php artisan tinker
>>> $action = new App\Actions\YourAction();
>>> $action->getDocumentation()['parameters'];

# 2. 使用API文件檢查參數格式
# 訪問 http://localhost:8000/api/docs

# 3. 啟用除錯模式查看詳細錯誤
# 在 .env 中設定 APP_DEBUG=true
```

### 3. 效能問題

#### Q7: API回應速度慢

**問題描述**：API請求回應時間過長

**診斷步驟**：

```bash
# 1. 檢查系統資源使用
docker stats

# 2. 檢查資料庫慢查詢
docker compose exec database mysql -u root -p -e "
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
SHOW VARIABLES LIKE 'slow_query_log%';
"

# 3. 檢查PHP-FPM狀態
docker compose exec laravel php-fpm -t

# 4. 分析日誌檔案
tail -f storage/logs/laravel.log | grep "Action執行"
```

**優化建議**：

```bash
# 1. 啟用快取
docker compose exec laravel php artisan config:cache
docker compose exec laravel php artisan route:cache
docker compose exec laravel php artisan view:cache

# 2. 優化資料庫查詢
# 在Action中使用 select() 限制查詢欄位
# 使用 with() 預載入關聯資料

# 3. 調整PHP設定
# 編輯 docker/php/php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

#### Q8: 記憶體使用過高

**問題描述**：系統記憶體使用率持續上升

**診斷步驟**：

```bash
# 1. 檢查容器記憶體使用
docker stats --no-stream

# 2. 檢查PHP記憶體使用
docker compose exec laravel php -i | grep memory_limit

# 3. 分析記憶體洩漏
# 在Action中添加記憶體使用監控
memory_get_usage(true)
memory_get_peak_usage(true)
```

**解決方案**：

```bash
# 1. 調整PHP記憶體限制
# 編輯 docker/php/php.ini
memory_limit = 256M

# 2. 重啟服務釋放記憶體
docker compose restart

# 3. 優化程式碼
# 避免在Action中載入大量資料
# 使用分頁處理大型資料集
# 及時釋放不需要的變數
```

### 4. 資料庫問題

#### Q9: 資料庫連線池耗盡

**問題描述**：出現 "Too many connections" 錯誤

**解決步驟**：

```bash
# 1. 檢查當前連線數
docker compose exec database mysql -u root -p -e "SHOW PROCESSLIST;"

# 2. 檢查最大連線數設定
docker compose exec database mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"

# 3. 調整連線數限制
# 編輯 docker/mysql/my.cnf
[mysqld]
max_connections = 200

# 4. 重啟資料庫服務
docker compose restart database
```

#### Q10: 資料庫鎖定問題

**問題描述**：出現死鎖或長時間等待

**診斷步驟**：

```bash
# 1. 檢查當前鎖定狀態
docker compose exec database mysql -u root -p -e "
SELECT * FROM information_schema.INNODB_LOCKS;
SELECT * FROM information_schema.INNODB_LOCK_WAITS;
"

# 2. 檢查長時間運行的查詢
docker compose exec database mysql -u root -p -e "
SELECT * FROM information_schema.PROCESSLIST 
WHERE COMMAND != 'Sleep' AND TIME > 10;
"

# 3. 終止問題查詢
# KILL [PROCESS_ID];
```

### 5. 權限和安全問題

#### Q11: Action權限檢查失敗

**問題描述**：使用者有權限但仍然被拒絕存取

**除錯步驟**：

```bash
# 1. 檢查使用者權限
docker compose exec laravel php artisan tinker
>>> $user = App\Models\User::find(1);
>>> $user->getPermissions();

# 2. 檢查Action權限要求
>>> $action = new App\Actions\YourAction();
>>> $action->getRequiredPermissions();

# 3. 檢查權限配置
>>> use App\Models\ActionPermission;
>>> ActionPermission::where('action_type', 'your.action')->first();

# 4. 測試權限檢查邏輯
>>> $checker = app(App\Contracts\PermissionCheckerInterface::class);
>>> $checker->canExecuteAction($user, $action);
```

#### Q12: CORS問題

**問題描述**：前端無法呼叫API，出現CORS錯誤

**解決步驟**：

```bash
# 1. 檢查CORS中介軟體配置
# 編輯 config/cors.php

# 2. 確認Nginx配置
# 編輯 docker/nginx/nginx.conf
add_header Access-Control-Allow-Origin *;
add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
add_header Access-Control-Allow-Headers "Authorization, Content-Type";

# 3. 重啟服務
docker compose restart nginx
```

### 6. 日誌和監控問題

#### Q13: 日誌檔案過大

**問題描述**：日誌檔案佔用大量磁碟空間

**解決步驟**：

```bash
# 1. 檢查日誌檔案大小
du -sh storage/logs/*

# 2. 設定日誌輪轉
# 編輯 config/logging.php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => 'debug',
    'days' => 7,
],

# 3. 手動清理舊日誌
find storage/logs -name "*.log" -mtime +7 -delete

# 4. 設定logrotate
sudo nano /etc/logrotate.d/laravel-api
```

#### Q14: 監控警報過多

**問題描述**：收到大量監控警報郵件

**調整步驟**：

```bash
# 1. 調整監控閾值
# 編輯 scripts/monitor.sh
# 將磁碟使用率警報從80%調整到90%

# 2. 增加警報間隔
# 避免短時間內重複發送相同警報

# 3. 分類警報等級
# 區分緊急、警告和資訊等級
```

## 除錯工具和技巧

### 1. 日誌分析

```bash
# 即時查看日誌
tail -f storage/logs/laravel.log

# 搜尋特定錯誤
grep "ERROR" storage/logs/laravel.log | tail -20

# 分析API請求統計
grep "Action執行成功" storage/logs/laravel.log | wc -l

# 查看最常見的錯誤
grep "ERROR" storage/logs/laravel.log | sort | uniq -c | sort -nr | head -10
```

### 2. 資料庫除錯

```bash
# 啟用查詢日誌
docker compose exec laravel php artisan tinker
>>> DB::enableQueryLog();
>>> // 執行一些操作
>>> DB::getQueryLog();

# 檢查資料庫狀態
docker compose exec database mysql -u root -p -e "SHOW STATUS;"

# 分析慢查詢
docker compose exec database mysql -u root -p -e "
SELECT * FROM mysql.slow_log 
ORDER BY start_time DESC 
LIMIT 10;
"
```

### 3. 效能分析

```bash
# 使用Xdebug分析效能
# 在 docker/php/php.ini 中啟用
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug

# 使用Laravel Telescope（開發環境）
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### 4. API測試工具

```bash
# 使用curl測試API
curl -X POST http://localhost:8000/api/ \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer token" \
  -d '{"action_type": "test.action"}' \
  -v

# 使用HTTPie（更友善的介面）
http POST localhost:8000/api/ \
  action_type=test.action \
  Authorization:"Bearer token"

# 批次測試腳本
#!/bin/bash
for i in {1..100}; do
  curl -s -X POST http://localhost:8000/api/ \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer token" \
    -d '{"action_type": "system.ping"}' \
    -w "%{time_total}\n" -o /dev/null
done
```

## 緊急處理程序

### 1. 服務完全停止

```bash
# 緊急重啟程序
cd /var/www/laravel-unified-api-server

# 1. 停止所有服務
docker compose down

# 2. 檢查系統資源
free -h
df -h

# 3. 清理Docker資源
docker system prune -f

# 4. 重新啟動服務
docker compose up -d

# 5. 檢查服務狀態
docker compose ps
curl -X POST http://localhost/api/ -d '{"action_type": "system.ping"}'
```

### 2. 資料庫損壞

```bash
# 資料庫修復程序
# 1. 停止應用程式
docker compose stop laravel

# 2. 備份當前資料庫
docker compose exec database mysqldump -u root -p api_server > emergency_backup.sql

# 3. 修復資料庫
docker compose exec database mysql -u root -p -e "
USE api_server;
REPAIR TABLE users;
REPAIR TABLE api_tokens;
REPAIR TABLE api_logs;
CHECK TABLE users;
CHECK TABLE api_tokens;
CHECK TABLE api_logs;
"

# 4. 如果修復失敗，從備份恢復
# mysql -u root -p api_server < latest_backup.sql

# 5. 重啟應用程式
docker compose start laravel
```

### 3. 高負載處理

```bash
# 高負載緊急處理
# 1. 檢查系統負載
uptime
top

# 2. 限制API請求頻率
# 臨時調整 config/api.php 中的速率限制

# 3. 啟用維護模式
docker compose exec laravel php artisan down --message="系統維護中"

# 4. 清理快取和會話
docker compose exec laravel php artisan cache:clear
docker compose exec laravel php artisan session:flush

# 5. 重啟服務
docker compose restart

# 6. 關閉維護模式
docker compose exec laravel php artisan up
```

## 預防措施

### 1. 定期維護檢查清單

```bash
# 每日檢查
- [ ] 檢查服務狀態
- [ ] 查看錯誤日誌
- [ ] 監控系統資源使用
- [ ] 檢查API回應時間

# 每週檢查
- [ ] 資料庫備份驗證
- [ ] 日誌檔案清理
- [ ] 安全更新檢查
- [ ] 效能指標分析

# 每月檢查
- [ ] 系統安全掃描
- [ ] 資料庫優化
- [ ] 容量規劃評估
- [ ] 災難恢復測試
```

### 2. 監控設定建議

```bash
# 設定關鍵指標監控
- API回應時間 > 2秒
- 錯誤率 > 5%
- CPU使用率 > 80%
- 記憶體使用率 > 90%
- 磁碟使用率 > 85%
- 資料庫連線數 > 150
```

### 3. 備份策略

```bash
# 自動備份設定
# 每日資料庫備份
0 2 * * * /path/to/backup_database.sh

# 每週完整系統備份
0 3 * * 0 /path/to/backup_system.sh

# 每月備份驗證
0 4 1 * * /path/to/verify_backups.sh
```

這個故障排除指南涵蓋了Laravel統一API系統的常見問題和解決方案，幫助快速診斷和解決各種技術問題。