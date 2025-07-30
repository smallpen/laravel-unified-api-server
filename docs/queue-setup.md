# 佇列系統設定指南

## 概述

本專案使用 Redis 作為佇列驅動，提供高效能的背景任務處理能力。

## 為什麼使用 Redis 而非資料庫？

### 效能優勢
- **記憶體存取速度**：Redis 是記憶體資料庫，處理速度比磁碟資料庫快數百倍
- **低延遲**：任務的入隊和出隊操作延遲極低
- **高併發支援**：能夠處理大量併發的佇列操作

### 功能優勢
- **原生佇列支援**：Redis 提供 List 和 Stream 等專門的佇列資料結構
- **任務優先級**：支援多個佇列和優先級排序
- **延遲任務**：支援延遲執行的任務
- **任務重試**：更好的失敗任務重試機制

### 系統資源優勢
- **減少資料庫負載**：避免佇列操作影響主要業務資料庫
- **更好的擴展性**：Redis 集群可以處理更大規模的佇列需求
- **記憶體效率**：Redis 的記憶體使用效率很高

## 配置說明

### 環境變數
```env
# 佇列連線設定
QUEUE_CONNECTION=redis

# Redis 連線設定
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default
```

### 佇列配置檔案
佇列的詳細配置位於 `config/queue.php`，包含：
- 預設佇列連線
- 各種佇列驅動的配置
- 失敗任務處理設定
- 批次處理配置

## 使用方法

### 1. 建立佇列任務
```bash
php artisan make:job ProcessDataJob
```

### 2. 分派任務到佇列
```php
use App\Jobs\ProcessDataJob;

// 立即分派
ProcessDataJob::dispatch($data);

// 延遲分派（5分鐘後執行）
ProcessDataJob::dispatch($data)->delay(now()->addMinutes(5));

// 指定佇列
ProcessDataJob::dispatch($data)->onQueue('high-priority');
```

### 3. 處理佇列任務
```bash
# 處理預設佇列的任務
php artisan queue:work

# 處理特定佇列
php artisan queue:work --queue=high-priority,default

# 指定處理任務數量後停止
php artisan queue:work --max-jobs=100

# 指定記憶體限制
php artisan queue:work --memory=512
```

### 4. 監控佇列狀態
```bash
# 檢查佇列狀態
php artisan queue:monitor

# 重啟所有佇列工作程序
php artisan queue:restart

# 清空佇列
php artisan queue:clear

# 檢查失敗的任務
php artisan queue:failed

# 重試失敗的任務
php artisan queue:retry all
```

## 測試佇列功能

### 使用測試指令
```bash
# 建立一個測試任務
php artisan queue:test

# 建立多個測試任務
php artisan queue:test --count=5
```

### 啟動佇列處理程序
```bash
php artisan queue:work
```

## 生產環境部署

### 使用 Supervisor 管理佇列程序
建立 Supervisor 配置檔案 `/etc/supervisor/conf.d/laravel-worker.conf`：

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

### 重新載入 Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## 最佳實踐

### 1. 任務設計原則
- 保持任務的冪等性（可重複執行）
- 處理任務失敗的情況
- 避免在任務中執行長時間運行的操作
- 使用適當的重試次數和延遲

### 2. 效能優化
- 使用多個佇列來分離不同優先級的任務
- 適當設定 `--sleep` 參數避免過度輪詢
- 監控記憶體使用量，適時重啟工作程序
- 使用 `--max-jobs` 參數定期重啟工作程序

### 3. 錯誤處理
- 實作 `failed()` 方法處理失敗任務
- 使用適當的日誌記錄
- 設定失敗任務的通知機制
- 定期清理過期的失敗任務

## 常見問題

### Q: 任務沒有被處理？
A: 檢查佇列工作程序是否正在運行，使用 `php artisan queue:work` 啟動。

### Q: 任務處理失敗？
A: 檢查日誌檔案，使用 `php artisan queue:failed` 查看失敗任務。

### Q: Redis 連線失敗？
A: 檢查 Redis 服務是否正在運行，確認連線設定是否正確。

### Q: 記憶體不足？
A: 使用 `--memory` 參數限制記憶體使用，或增加系統記憶體。