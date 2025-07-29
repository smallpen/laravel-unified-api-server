# Laravel 統一 API Server 系統維護指南

## 目錄

1. [系統概述](#系統概述)
2. [日常維護任務](#日常維護任務)
3. [監控和警報](#監控和警報)
4. [備份和恢復](#備份和恢復)
5. [效能優化](#效能優化)
6. [故障排除](#故障排除)
7. [安全維護](#安全維護)
8. [日誌管理](#日誌管理)
9. [更新和升級](#更新和升級)
10. [緊急處理程序](#緊急處理程序)

## 系統概述

Laravel 統一 API Server 是一個基於 Docker 容器化部署的 API 服務系統，包含以下主要組件：

- **Laravel 應用程式容器**：主要的 API 服務
- **Nginx 容器**：Web 伺服器和反向代理
- **MySQL 容器**：資料庫服務
- **Redis 容器**：快取和會話存儲

### 系統架構

```
[客戶端] → [Nginx] → [Laravel] → [MySQL/Redis]
```

### 重要檔案位置

- 應用程式代碼：`/var/www/html`
- 日誌檔案：`/var/www/html/storage/logs`
- 配置檔案：`/var/www/html/config`
- Docker 配置：`docker-compose.prod.yml`

## 日常維護任務

### 每日檢查清單

- [ ] 檢查系統健康狀態
- [ ] 檢查容器運行狀態
- [ ] 檢查磁碟空間使用率
- [ ] 檢查日誌檔案大小
- [ ] 檢查 API 回應時間
- [ ] 檢查錯誤日誌

#### 執行命令

```bash
# 檢查系統健康狀態
./scripts/monitor.sh health

# 檢查容器狀態
docker-compose -f docker-compose.prod.yml ps

# 檢查磁碟使用率
df -h

# 檢查日誌檔案大小
du -sh storage/logs/*

# 檢查 API 健康狀態
curl -s http://localhost/api/health/detailed | jq .
```

### 每週維護任務

- [ ] 分析系統效能報告
- [ ] 檢查安全日誌
- [ ] 清理舊的日誌檔案
- [ ] 檢查備份完整性
- [ ] 更新系統依賴

#### 執行命令

```bash
# 生成週報告
./scripts/log-analyzer.sh analyze 7

# 清理舊日誌
find storage/logs -name "*.log" -mtime +30 -delete

# 檢查備份
ls -la ./backups/

# 更新 Composer 依賴
docker-compose -f docker-compose.prod.yml exec laravel composer update --no-dev
```

### 每月維護任務

- [ ] 完整系統備份
- [ ] 資料庫優化
- [ ] 安全掃描
- [ ] 效能基準測試
- [ ] 文件更新

#### 執行命令

```bash
# 完整備份
./scripts/deploy.sh backup

# 資料庫優化
docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "OPTIMIZE TABLE api_tokens, api_logs, action_permissions;"

# 效能測試
./scripts/load-test.php
```

## 監控和警報

### 監控指標

1. **系統資源**
   - CPU 使用率 (警報閾值: >80%)
   - 記憶體使用率 (警報閾值: >80%)
   - 磁碟使用率 (警報閾值: >80%)

2. **應用程式指標**
   - API 回應時間 (警報閾值: >2000ms)
   - 錯誤率 (警報閾值: >5%)
   - 請求量 (監控異常波動)

3. **服務可用性**
   - 容器運行狀態
   - 資料庫連線狀態
   - Redis 連線狀態

### 監控腳本使用

```bash
# 執行完整監控檢查
./scripts/monitor.sh monitor

# 僅檢查資源使用率
./scripts/monitor.sh resources

# 生成監控報告
./scripts/monitor.sh report
```

### 設定警報通知

編輯 `scripts/monitor.sh` 檔案，設定以下變數：

```bash
# 郵件通知
ALERT_EMAIL="admin@example.com"

# Slack 通知
SLACK_WEBHOOK="https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK"
```

## 備份和恢復

### 自動備份

系統會在每次部署時自動建立備份，包含：

- 資料庫完整備份
- 上傳檔案備份
- 配置檔案備份

### 手動備份

```bash
# 建立完整備份
./scripts/deploy.sh backup

# 僅備份資料庫
docker-compose -f docker-compose.prod.yml exec database mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 恢復程序

1. **停止服務**
   ```bash
   docker-compose -f docker-compose.prod.yml down
   ```

2. **恢復資料庫**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d database
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < backup_file.sql
   ```

3. **恢復檔案**
   ```bash
   cp -r backup_directory/public/* storage/app/public/
   ```

4. **重啟服務**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

## 效能優化

### 資料庫優化

1. **定期優化表格**
   ```bash
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "OPTIMIZE TABLE api_tokens, api_logs, action_permissions;"
   ```

2. **檢查慢查詢**
   ```bash
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

3. **分析表格使用情況**
   ```bash
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "SELECT table_name, table_rows, data_length, index_length FROM information_schema.tables WHERE table_schema='$MYSQL_DATABASE';"
   ```

### 快取優化

1. **清除應用程式快取**
   ```bash
   docker-compose -f docker-compose.prod.yml exec laravel php artisan cache:clear
   docker-compose -f docker-compose.prod.yml exec laravel php artisan config:clear
   docker-compose -f docker-compose.prod.yml exec laravel php artisan route:clear
   ```

2. **重建快取**
   ```bash
   docker-compose -f docker-compose.prod.yml exec laravel php artisan config:cache
   docker-compose -f docker-compose.prod.yml exec laravel php artisan route:cache
   ```

3. **檢查 Redis 狀態**
   ```bash
   docker-compose -f docker-compose.prod.yml exec redis redis-cli info memory
   ```

### 檔案系統優化

1. **清理暫存檔案**
   ```bash
   docker-compose -f docker-compose.prod.yml exec laravel find /tmp -type f -atime +7 -delete
   ```

2. **壓縮日誌檔案**
   ```bash
   find storage/logs -name "*.log" -size +10M -exec gzip {} \;
   ```

## 故障排除

### 常見問題和解決方案

#### 1. 容器無法啟動

**症狀**：`docker-compose up` 失敗

**診斷**：
```bash
docker-compose -f docker-compose.prod.yml logs
```

**解決方案**：
- 檢查 `.env` 檔案配置
- 檢查埠號衝突
- 檢查磁碟空間
- 重建映像：`docker-compose -f docker-compose.prod.yml build --no-cache`

#### 2. API 回應緩慢

**症狀**：API 回應時間超過 2 秒

**診斷**：
```bash
# 檢查系統資源
./scripts/monitor.sh resources

# 檢查資料庫連線
docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "SHOW PROCESSLIST;"

# 檢查 Redis 狀態
docker-compose -f docker-compose.prod.yml exec redis redis-cli info
```

**解決方案**：
- 清除快取
- 優化資料庫查詢
- 增加系統資源
- 檢查網路連線

#### 3. 資料庫連線失敗

**症狀**：應用程式無法連接資料庫

**診斷**：
```bash
docker-compose -f docker-compose.prod.yml exec laravel php artisan tinker
# 在 tinker 中執行：DB::select('SELECT 1');
```

**解決方案**：
- 檢查資料庫容器狀態
- 檢查資料庫配置
- 重啟資料庫容器
- 檢查網路連線

#### 4. 記憶體不足

**症狀**：系統回應緩慢，出現 500 錯誤

**診斷**：
```bash
free -h
docker stats
```

**解決方案**：
- 重啟容器釋放記憶體
- 增加系統記憶體
- 優化應用程式記憶體使用
- 調整 PHP 記憶體限制

### 日誌分析

使用日誌分析工具診斷問題：

```bash
# 分析錯誤日誌
./scripts/log-analyzer.sh errors

# 分析 API 請求
./scripts/log-analyzer.sh api

# 生成綜合報告
./scripts/log-analyzer.sh summary
```

## 安全維護

### 安全檢查清單

- [ ] 檢查 SSL 憑證有效期
- [ ] 檢查防火牆規則
- [ ] 檢查使用者權限
- [ ] 檢查 API Token 使用情況
- [ ] 檢查安全日誌

### 安全更新

1. **更新系統套件**
   ```bash
   # 在主機上執行
   sudo apt update && sudo apt upgrade -y
   ```

2. **更新 Docker 映像**
   ```bash
   docker-compose -f docker-compose.prod.yml pull
   docker-compose -f docker-compose.prod.yml up -d
   ```

3. **更新 Laravel 依賴**
   ```bash
   docker-compose -f docker-compose.prod.yml exec laravel composer update --no-dev
   ```

### 安全監控

```bash
# 檢查安全日誌
./scripts/log-analyzer.sh security

# 檢查失敗的登入嘗試
grep "authentication failed" storage/logs/security*.log

# 檢查可疑 IP
./scripts/monitor.sh | grep "suspicious"
```

## 日誌管理

### 日誌類型

1. **應用程式日誌** (`storage/logs/laravel.log`)
   - 應用程式錯誤和除錯資訊

2. **API 請求日誌** (`storage/logs/api_requests*.log`)
   - API 請求記錄和回應時間

3. **安全日誌** (`storage/logs/security*.log`)
   - 驗證失敗和安全事件

4. **效能日誌** (`storage/logs/performance*.log`)
   - 慢查詢和效能指標

### 日誌輪轉

系統使用 logrotate 自動管理日誌檔案：

```bash
# 手動執行日誌輪轉
sudo logrotate -f /etc/logrotate.d/laravel-api

# 檢查 logrotate 狀態
sudo logrotate -d /etc/logrotate.d/laravel-api
```

### 日誌分析

```bash
# 分析所有日誌
./scripts/log-analyzer.sh analyze

# 分析特定天數的日誌
./scripts/log-analyzer.sh analyze 14

# 分析特定類型的日誌
./scripts/log-analyzer.sh errors 7
```

## 更新和升級

### 應用程式更新流程

1. **準備更新**
   ```bash
   # 建立備份
   ./scripts/deploy.sh backup
   
   # 檢查系統狀態
   ./scripts/monitor.sh health
   ```

2. **執行更新**
   ```bash
   # 拉取最新代碼
   git pull origin main
   
   # 執行部署
   ./scripts/deploy.sh deploy
   ```

3. **驗證更新**
   ```bash
   # 檢查健康狀態
   curl -s http://localhost/api/health/detailed
   
   # 檢查服務狀態
   docker-compose -f docker-compose.prod.yml ps
   ```

### 回滾程序

如果更新失敗，可以快速回滾：

1. **停止服務**
   ```bash
   docker-compose -f docker-compose.prod.yml down
   ```

2. **恢復代碼**
   ```bash
   git reset --hard HEAD~1
   ```

3. **恢復資料庫**
   ```bash
   # 使用最新的備份恢復
   docker-compose -f docker-compose.prod.yml up -d database
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < ./backups/latest/database.sql
   ```

4. **重啟服務**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

## 緊急處理程序

### 服務中斷處理

1. **立即響應**
   - 確認服務中斷範圍
   - 通知相關人員
   - 開始故障排除

2. **診斷步驟**
   ```bash
   # 檢查容器狀態
   docker-compose -f docker-compose.prod.yml ps
   
   # 檢查系統資源
   top
   df -h
   free -h
   
   # 檢查網路連線
   netstat -tuln
   
   # 檢查日誌
   tail -f storage/logs/laravel.log
   ```

3. **恢復步驟**
   ```bash
   # 重啟服務
   docker-compose -f docker-compose.prod.yml restart
   
   # 如果無效，重建服務
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   
   # 最後手段：完整重建
   docker-compose -f docker-compose.prod.yml down -v
   docker-compose -f docker-compose.prod.yml build --no-cache
   docker-compose -f docker-compose.prod.yml up -d
   ```

### 資料損壞處理

1. **立即停止服務**
   ```bash
   docker-compose -f docker-compose.prod.yml down
   ```

2. **評估損壞程度**
   ```bash
   # 檢查資料庫完整性
   docker-compose -f docker-compose.prod.yml up -d database
   docker-compose -f docker-compose.prod.yml exec database mysql -u root -p -e "CHECK TABLE api_tokens, api_logs, action_permissions;"
   ```

3. **恢復資料**
   ```bash
   # 使用最新備份恢復
   ./scripts/deploy.sh backup  # 先備份當前狀態
   # 然後恢復最後一個已知良好的備份
   ```

### 安全事件處理

1. **隔離系統**
   ```bash
   # 停止對外服務
   docker-compose -f docker-compose.prod.yml stop nginx
   ```

2. **收集證據**
   ```bash
   # 備份日誌
   cp -r storage/logs/ /tmp/security_incident_$(date +%Y%m%d_%H%M%S)/
   
   # 分析安全日誌
   ./scripts/log-analyzer.sh security
   ```

3. **修復和加固**
   - 修補安全漏洞
   - 更新密碼和 Token
   - 加強防火牆規則
   - 更新安全配置

## 聯絡資訊

### 緊急聯絡人

- **系統管理員**：[聯絡資訊]
- **開發團隊**：[聯絡資訊]
- **基礎設施團隊**：[聯絡資訊]

### 外部支援

- **雲端服務提供商**：[支援聯絡方式]
- **第三方服務**：[支援聯絡方式]

---

**注意**：本文件應定期更新，確保所有程序和聯絡資訊保持最新狀態。建議每季度檢查一次文件內容的準確性。