#!/bin/bash

# Laravel 統一 API Server Cron 任務設定腳本
# 版本: 1.0
# 作者: System Administrator

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日誌函數
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# 取得專案根目錄
PROJECT_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

# 建立 Cron 任務配置
create_cron_jobs() {
    log_info "建立 Cron 任務配置..."
    
    # 建立暫存的 crontab 檔案
    local cron_file="/tmp/laravel-api-cron"
    
    cat > "$cron_file" << EOF
# Laravel 統一 API Server 自動化任務
# 由 setup-cron.sh 腳本自動生成

# 每 5 分鐘執行健康檢查
*/5 * * * * cd $PROJECT_ROOT && ./scripts/monitor.sh health >> ./logs/cron.log 2>&1

# 每 15 分鐘檢查系統資源
*/15 * * * * cd $PROJECT_ROOT && ./scripts/monitor.sh resources >> ./logs/cron.log 2>&1

# 每小時執行完整監控檢查
0 * * * * cd $PROJECT_ROOT && ./scripts/monitor.sh monitor >> ./logs/cron.log 2>&1

# 每日凌晨 2 點執行日誌分析
0 2 * * * cd $PROJECT_ROOT && ./scripts/log-analyzer.sh analyze >> ./logs/cron.log 2>&1

# 每日凌晨 3 點清理舊日誌檔案
0 3 * * * cd $PROJECT_ROOT && find ./storage/logs -name "*.log" -mtime +30 -delete >> ./logs/cron.log 2>&1

# 每日凌晨 4 點執行資料庫備份
0 4 * * * cd $PROJECT_ROOT && ./scripts/deploy.sh backup >> ./logs/cron.log 2>&1

# 每週日凌晨 1 點執行完整系統報告
0 1 * * 0 cd $PROJECT_ROOT && ./scripts/log-analyzer.sh analyze 7 >> ./logs/cron.log 2>&1

# 每週日凌晨 5 點清理舊備份檔案 (保留 30 天)
0 5 * * 0 cd $PROJECT_ROOT && find ./backups -type d -name "backup_*" -mtime +30 -exec rm -rf {} \; >> ./logs/cron.log 2>&1

# 每月 1 號凌晨 6 點執行資料庫優化
0 6 1 * * cd $PROJECT_ROOT && docker compose -f docker compose.prod.yml exec -T database mysql -u root -p"\$MYSQL_ROOT_PASSWORD" -e "OPTIMIZE TABLE api_tokens, api_logs, action_permissions;" >> ./logs/cron.log 2>&1

# 每天檢查 SSL 憑證有效期 (如果使用 HTTPS)
0 7 * * * cd $PROJECT_ROOT && ./scripts/check-ssl.sh >> ./logs/cron.log 2>&1

EOF

    log_success "Cron 任務配置已建立: $cron_file"
    
    # 顯示配置內容
    log_info "Cron 任務配置內容："
    cat "$cron_file"
    
    return 0
}

# 安裝 Cron 任務
install_cron_jobs() {
    log_info "安裝 Cron 任務..."
    
    local cron_file="/tmp/laravel-api-cron"
    
    if [ ! -f "$cron_file" ]; then
        log_error "找不到 Cron 配置檔案，請先執行 create_cron_jobs"
        return 1
    fi
    
    # 備份現有的 crontab
    if crontab -l > /dev/null 2>&1; then
        log_info "備份現有的 crontab..."
        crontab -l > "/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S)"
    fi
    
    # 安裝新的 crontab
    crontab "$cron_file"
    
    log_success "Cron 任務安裝完成"
    
    # 顯示安裝結果
    log_info "當前 Cron 任務："
    crontab -l
}

# 移除 Cron 任務
remove_cron_jobs() {
    log_info "移除 Laravel API Server 相關的 Cron 任務..."
    
    # 備份現有的 crontab
    if crontab -l > /dev/null 2>&1; then
        local backup_file="/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S)"
        crontab -l > "$backup_file"
        log_info "已備份現有 crontab 到: $backup_file"
        
        # 移除包含專案路徑的任務
        crontab -l | grep -v "$PROJECT_ROOT" | crontab -
        
        log_success "Laravel API Server 相關的 Cron 任務已移除"
    else
        log_warning "沒有找到現有的 crontab"
    fi
}

# 檢查 Cron 服務狀態
check_cron_service() {
    log_info "檢查 Cron 服務狀態..."
    
    if systemctl is-active --quiet cron; then
        log_success "Cron 服務正在運行"
    elif systemctl is-active --quiet crond; then
        log_success "Crond 服務正在運行"
    else
        log_error "Cron 服務未運行，請啟動 Cron 服務"
        log_info "嘗試啟動 Cron 服務："
        log_info "  Ubuntu/Debian: sudo systemctl start cron"
        log_info "  CentOS/RHEL: sudo systemctl start crond"
        return 1
    fi
    
    # 檢查 cron 日誌
    if [ -f "/var/log/cron.log" ]; then
        log_info "最近的 Cron 日誌："
        tail -5 /var/log/cron.log
    elif [ -f "/var/log/cron" ]; then
        log_info "最近的 Cron 日誌："
        tail -5 /var/log/cron
    else
        log_warning "找不到 Cron 日誌檔案"
    fi
}

# 建立 SSL 憑證檢查腳本
create_ssl_check_script() {
    log_info "建立 SSL 憑證檢查腳本..."
    
    cat > "$PROJECT_ROOT/scripts/check-ssl.sh" << 'EOF'
#!/bin/bash

# SSL 憑證檢查腳本

# 配置
DOMAIN="localhost"  # 替換為實際域名
DAYS_WARNING=30     # 憑證到期前多少天發出警告

# 檢查 SSL 憑證
check_ssl_cert() {
    if command -v openssl &> /dev/null; then
        local cert_info=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null)
        
        if [ $? -eq 0 ]; then
            local expiry_date=$(echo "$cert_info" | grep "notAfter" | cut -d= -f2)
            local expiry_timestamp=$(date -d "$expiry_date" +%s)
            local current_timestamp=$(date +%s)
            local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
            
            echo "SSL 憑證到期日期: $expiry_date"
            echo "距離到期還有: $days_until_expiry 天"
            
            if [ $days_until_expiry -le $DAYS_WARNING ]; then
                echo "警告: SSL 憑證即將到期！"
                # 這裡可以添加發送警報的邏輯
            fi
        else
            echo "無法檢查 SSL 憑證"
        fi
    else
        echo "OpenSSL 未安裝，跳過 SSL 憑證檢查"
    fi
}

check_ssl_cert
EOF

    chmod +x "$PROJECT_ROOT/scripts/check-ssl.sh"
    log_success "SSL 憑證檢查腳本已建立"
}

# 建立日誌目錄
create_log_directory() {
    log_info "建立日誌目錄..."
    
    mkdir -p "$PROJECT_ROOT/logs"
    
    # 建立 cron 日誌檔案
    touch "$PROJECT_ROOT/logs/cron.log"
    
    log_success "日誌目錄已建立"
}

# 測試 Cron 任務
test_cron_jobs() {
    log_info "測試 Cron 任務..."
    
    # 測試監控腳本
    if [ -x "$PROJECT_ROOT/scripts/monitor.sh" ]; then
        log_info "測試監控腳本..."
        cd "$PROJECT_ROOT" && ./scripts/monitor.sh health
    else
        log_error "監控腳本不存在或不可執行"
    fi
    
    # 測試日誌分析腳本
    if [ -x "$PROJECT_ROOT/scripts/log-analyzer.sh" ]; then
        log_info "測試日誌分析腳本..."
        cd "$PROJECT_ROOT" && ./scripts/log-analyzer.sh summary
    else
        log_error "日誌分析腳本不存在或不可執行"
    fi
    
    log_success "Cron 任務測試完成"
}

# 顯示使用說明
show_usage() {
    echo "使用方法: $0 [選項]"
    echo ""
    echo "選項:"
    echo "  install   建立並安裝 Cron 任務"
    echo "  create    僅建立 Cron 任務配置"
    echo "  remove    移除 Cron 任務"
    echo "  status    檢查 Cron 服務狀態"
    echo "  test      測試 Cron 任務"
    echo "  help      顯示此說明"
    echo ""
    echo "範例:"
    echo "  $0 install  # 完整安裝 Cron 任務"
    echo "  $0 status   # 檢查 Cron 服務狀態"
    echo ""
}

# 主要安裝流程
main() {
    log_info "開始設定 Laravel API Server Cron 任務..."
    
    create_log_directory
    create_ssl_check_script
    create_cron_jobs
    install_cron_jobs
    check_cron_service
    test_cron_jobs
    
    log_success "Cron 任務設定完成！"
    log_info "日誌檔案位置: $PROJECT_ROOT/logs/cron.log"
    log_info "可以使用 'tail -f $PROJECT_ROOT/logs/cron.log' 監控 Cron 任務執行"
}

# 處理命令列參數
case "${1:-install}" in
    "install")
        main
        ;;
    "create")
        create_log_directory
        create_ssl_check_script
        create_cron_jobs
        ;;
    "remove")
        remove_cron_jobs
        ;;
    "status")
        check_cron_service
        ;;
    "test")
        test_cron_jobs
        ;;
    "help")
        show_usage
        ;;
    *)
        log_error "未知的選項: $1"
        show_usage
        exit 1
        ;;
esac