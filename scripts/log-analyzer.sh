#!/bin/bash

# Laravel 統一 API Server 日誌分析腳本
# 版本: 1.0
# 作者: System Administrator

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 配置變數
LOG_DIR="storage/logs"
REPORT_DIR="./logs/reports"
DAYS_TO_ANALYZE=7

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

# 建立報告目錄
mkdir -p "$REPORT_DIR"

# 分析 API 請求日誌
analyze_api_requests() {
    log_info "分析 API 請求日誌..."
    
    local report_file="$REPORT_DIR/api_requests_$(date +%Y%m%d_%H%M%S).txt"
    local log_pattern="$LOG_DIR/api_requests-*.log"
    
    if ls $log_pattern 1> /dev/null 2>&1; then
        {
            echo "API 請求分析報告"
            echo "分析時間: $(date '+%Y-%m-%d %H:%M:%S')"
            echo "分析期間: 最近 $DAYS_TO_ANALYZE 天"
            echo "========================================"
            echo ""
            
            # 總請求數
            echo "總請求數統計:"
            find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; | wc -l
            echo ""
            
            # 最常用的 Action Types
            echo "最常用的 Action Types (前 10 名):"
            find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "action_type" {} \; | \
                sed -n 's/.*action_type:\([^,]*\).*/\1/p' | \
                sort | uniq -c | sort -nr | head -10
            echo ""
            
            # HTTP 狀態碼統計
            echo "HTTP 狀態碼統計:"
            find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "status_code" {} \; | \
                sed -n 's/.*status_code:\([0-9]*\).*/\1/p' | \
                sort | uniq -c | sort -nr
            echo ""
            
            # 回應時間分析
            echo "回應時間分析 (毫秒):"
            find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "response_time" {} \; | \
                sed -n 's/.*response_time:\([0-9.]*\).*/\1/p' | \
                awk '{
                    sum += $1; 
                    count++; 
                    if($1 > max) max = $1; 
                    if(min == "" || $1 < min) min = $1
                } 
                END {
                    if(count > 0) {
                        printf "平均回應時間: %.2f ms\n", sum/count;
                        printf "最快回應時間: %.2f ms\n", min;
                        printf "最慢回應時間: %.2f ms\n", max;
                        printf "總請求數: %d\n", count
                    }
                }'
            echo ""
            
            # 每小時請求量統計
            echo "每小時請求量統計 (最近 24 小時):"
            find $LOG_DIR -name "api_requests-*.log" -mtime -1 -exec grep -h "$(date +%Y-%m-%d)" {} \; | \
                sed -n 's/.*\([0-9]\{2\}:[0-9]\{2\}:[0-9]\{2\}\).*/\1/p' | \
                cut -d: -f1 | sort | uniq -c | sort -k2
            echo ""
            
            # 使用者活動統計
            echo "使用者活動統計 (前 10 名):"
            find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "user_id" {} \; | \
                sed -n 's/.*user_id:\([^,]*\).*/\1/p' | \
                sort | uniq -c | sort -nr | head -10
            echo ""
            
        } > "$report_file"
        
        log_success "API 請求分析報告已生成: $report_file"
    else
        log_warning "找不到 API 請求日誌檔案"
    fi
}

# 分析錯誤日誌
analyze_errors() {
    log_info "分析錯誤日誌..."
    
    local report_file="$REPORT_DIR/errors_$(date +%Y%m%d_%H%M%S).txt"
    local log_pattern="$LOG_DIR/errors-*.log"
    
    if ls $log_pattern 1> /dev/null 2>&1 || [ -f "$LOG_DIR/laravel.log" ]; then
        {
            echo "錯誤日誌分析報告"
            echo "分析時間: $(date '+%Y-%m-%d %H:%M:%S')"
            echo "分析期間: 最近 $DAYS_TO_ANALYZE 天"
            echo "========================================"
            echo ""
            
            # 錯誤級別統計
            echo "錯誤級別統計:"
            {
                find $LOG_DIR -name "errors-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                if [ -f "$LOG_DIR/laravel.log" ]; then
                    find $LOG_DIR -name "laravel.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                fi
            } | grep -E "\.(ERROR|CRITICAL|EMERGENCY|WARNING)" | \
                sed -n 's/.*\.\(ERROR\|CRITICAL\|EMERGENCY\|WARNING\).*/\1/p' | \
                sort | uniq -c | sort -nr
            echo ""
            
            # 最常見的錯誤類型
            echo "最常見的錯誤類型 (前 10 名):"
            {
                find $LOG_DIR -name "errors-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                if [ -f "$LOG_DIR/laravel.log" ]; then
                    find $LOG_DIR -name "laravel.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                fi
            } | grep -E "Exception|Error" | \
                sed -n 's/.*\(Exception\|Error\)[^:]*:\([^{]*\).*/\2/p' | \
                sort | uniq -c | sort -nr | head -10
            echo ""
            
            # 每日錯誤數量趨勢
            echo "每日錯誤數量趨勢:"
            for i in $(seq 0 $((DAYS_TO_ANALYZE-1))); do
                date_to_check=$(date -d "$i days ago" +%Y-%m-%d)
                error_count=$(
                    {
                        find $LOG_DIR -name "errors-*.log" -exec grep -l "$date_to_check" {} \; -exec cat {} \; 2>/dev/null || true
                        if [ -f "$LOG_DIR/laravel.log" ]; then
                            grep "$date_to_check" "$LOG_DIR/laravel.log" 2>/dev/null || true
                        fi
                    } | grep -c -E "\.(ERROR|CRITICAL|EMERGENCY)" || echo "0"
                )
                echo "$date_to_check: $error_count 個錯誤"
            done
            echo ""
            
            # 最近的嚴重錯誤
            echo "最近的嚴重錯誤 (CRITICAL/EMERGENCY):"
            {
                find $LOG_DIR -name "errors-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                if [ -f "$LOG_DIR/laravel.log" ]; then
                    find $LOG_DIR -name "laravel.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                fi
            } | grep -E "\.(CRITICAL|EMERGENCY)" | tail -10
            echo ""
            
        } > "$report_file"
        
        log_success "錯誤日誌分析報告已生成: $report_file"
    else
        log_warning "找不到錯誤日誌檔案"
    fi
}

# 分析安全日誌
analyze_security() {
    log_info "分析安全日誌..."
    
    local report_file="$REPORT_DIR/security_$(date +%Y%m%d_%H%M%S).txt"
    local log_pattern="$LOG_DIR/security-*.log"
    
    if ls $log_pattern 1> /dev/null 2>&1; then
        {
            echo "安全日誌分析報告"
            echo "分析時間: $(date '+%Y-%m-%d %H:%M:%S')"
            echo "分析期間: 最近 $DAYS_TO_ANALYZE 天"
            echo "========================================"
            echo ""
            
            # 失敗的驗證嘗試
            echo "失敗的驗證嘗試:"
            find $LOG_DIR -name "security-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "authentication failed\|invalid token\|unauthorized" {} \; | wc -l
            echo ""
            
            # 可疑的 IP 地址
            echo "可疑的 IP 地址 (失敗嘗試次數前 10 名):"
            find $LOG_DIR -name "security-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "ip:" {} \; | \
                sed -n 's/.*ip:\([0-9.]*\).*/\1/p' | \
                sort | uniq -c | sort -nr | head -10
            echo ""
            
            # 安全事件類型統計
            echo "安全事件類型統計:"
            find $LOG_DIR -name "security-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "event_type" {} \; | \
                sed -n 's/.*event_type:\([^,]*\).*/\1/p' | \
                sort | uniq -c | sort -nr
            echo ""
            
        } > "$report_file"
        
        log_success "安全日誌分析報告已生成: $report_file"
    else
        log_warning "找不到安全日誌檔案"
    fi
}

# 分析效能日誌
analyze_performance() {
    log_info "分析效能日誌..."
    
    local report_file="$REPORT_DIR/performance_$(date +%Y%m%d_%H%M%S).txt"
    local log_pattern="$LOG_DIR/performance-*.log"
    
    if ls $log_pattern 1> /dev/null 2>&1; then
        {
            echo "效能日誌分析報告"
            echo "分析時間: $(date '+%Y-%m-%d %H:%M:%S')"
            echo "分析期間: 最近 $DAYS_TO_ANALYZE 天"
            echo "========================================"
            echo ""
            
            # 慢查詢統計
            echo "慢查詢統計 (執行時間 > 1000ms):"
            find $LOG_DIR -name "performance-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "slow_query" {} \; | \
                grep -E "execution_time:[0-9]*[0-9]{4,}" | wc -l
            echo ""
            
            # 記憶體使用量統計
            echo "記憶體使用量統計:"
            find $LOG_DIR -name "performance-*.log" -mtime -$DAYS_TO_ANALYZE -exec grep -h "memory_usage" {} \; | \
                sed -n 's/.*memory_usage:\([0-9]*\).*/\1/p' | \
                awk '{
                    sum += $1; 
                    count++; 
                    if($1 > max) max = $1; 
                    if(min == "" || $1 < min) min = $1
                } 
                END {
                    if(count > 0) {
                        printf "平均記憶體使用: %.2f MB\n", sum/count/1024/1024;
                        printf "最低記憶體使用: %.2f MB\n", min/1024/1024;
                        printf "最高記憶體使用: %.2f MB\n", max/1024/1024
                    }
                }'
            echo ""
            
        } > "$report_file"
        
        log_success "效能日誌分析報告已生成: $report_file"
    else
        log_warning "找不到效能日誌檔案"
    fi
}

# 生成綜合報告
generate_summary_report() {
    log_info "生成綜合分析報告..."
    
    local summary_file="$REPORT_DIR/summary_$(date +%Y%m%d_%H%M%S).txt"
    
    {
        echo "Laravel 統一 API Server 日誌綜合分析報告"
        echo "生成時間: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "分析期間: 最近 $DAYS_TO_ANALYZE 天"
        echo "========================================"
        echo ""
        
        # 系統健康度評估
        echo "系統健康度評估:"
        
        # 計算錯誤率
        local total_requests=0
        local total_errors=0
        
        if ls $LOG_DIR/api_requests-*.log 1> /dev/null 2>&1; then
            total_requests=$(find $LOG_DIR -name "api_requests-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; | wc -l)
        fi
        
        if ls $LOG_DIR/errors-*.log 1> /dev/null 2>&1 || [ -f "$LOG_DIR/laravel.log" ]; then
            total_errors=$(
                {
                    find $LOG_DIR -name "errors-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                    if [ -f "$LOG_DIR/laravel.log" ]; then
                        find $LOG_DIR -name "laravel.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; 2>/dev/null || true
                    fi
                } | grep -c -E "\.(ERROR|CRITICAL|EMERGENCY)" || echo "0"
            )
        fi
        
        if [ $total_requests -gt 0 ]; then
            local error_rate=$(echo "scale=2; $total_errors * 100 / $total_requests" | bc -l)
            echo "總請求數: $total_requests"
            echo "總錯誤數: $total_errors"
            echo "錯誤率: ${error_rate}%"
            
            if (( $(echo "$error_rate < 1" | bc -l) )); then
                echo "健康狀態: 良好 ✓"
            elif (( $(echo "$error_rate < 5" | bc -l) )); then
                echo "健康狀態: 一般 ⚠"
            else
                echo "健康狀態: 需要關注 ✗"
            fi
        else
            echo "無足夠資料進行健康度評估"
        fi
        echo ""
        
        # 建議事項
        echo "建議事項:"
        
        if [ $total_errors -gt 100 ]; then
            echo "- 錯誤數量較多，建議檢查應用程式邏輯"
        fi
        
        if ls $LOG_DIR/security-*.log 1> /dev/null 2>&1; then
            local security_events=$(find $LOG_DIR -name "security-*.log" -mtime -$DAYS_TO_ANALYZE -exec cat {} \; | wc -l)
            if [ $security_events -gt 50 ]; then
                echo "- 安全事件較多，建議加強安全防護"
            fi
        fi
        
        # 檢查日誌檔案大小
        local large_logs=$(find $LOG_DIR -name "*.log" -size +100M)
        if [ -n "$large_logs" ]; then
            echo "- 發現大型日誌檔案，建議定期清理："
            echo "$large_logs"
        fi
        
        echo ""
        echo "報告生成完成。詳細分析請查看各個專項報告。"
        
    } > "$summary_file"
    
    log_success "綜合分析報告已生成: $summary_file"
}

# 清理舊的報告檔案
cleanup_old_reports() {
    log_info "清理舊的報告檔案..."
    
    find "$REPORT_DIR" -name "*.txt" -mtime +30 -delete 2>/dev/null || true
    
    log_success "舊報告檔案清理完成"
}

# 主要分析流程
main() {
    log_info "開始日誌分析..."
    
    analyze_api_requests
    analyze_errors
    analyze_security
    analyze_performance
    generate_summary_report
    cleanup_old_reports
    
    log_success "日誌分析完成，報告已保存到 $REPORT_DIR"
}

# 顯示使用說明
show_usage() {
    echo "使用方法: $0 [選項] [天數]"
    echo ""
    echo "選項:"
    echo "  analyze   執行完整日誌分析 (預設)"
    echo "  api       僅分析 API 請求日誌"
    echo "  errors    僅分析錯誤日誌"
    echo "  security  僅分析安全日誌"
    echo "  performance 僅分析效能日誌"
    echo "  summary   僅生成綜合報告"
    echo "  cleanup   清理舊報告檔案"
    echo "  help      顯示此說明"
    echo ""
    echo "天數: 分析最近幾天的日誌 (預設: $DAYS_TO_ANALYZE 天)"
    echo ""
    echo "範例:"
    echo "  $0 analyze 14    # 分析最近 14 天的所有日誌"
    echo "  $0 errors 7      # 分析最近 7 天的錯誤日誌"
    echo ""
}

# 處理命令列參數
if [ -n "$2" ] && [[ "$2" =~ ^[0-9]+$ ]]; then
    DAYS_TO_ANALYZE=$2
fi

case "${1:-analyze}" in
    "analyze")
        main
        ;;
    "api")
        analyze_api_requests
        ;;
    "errors")
        analyze_errors
        ;;
    "security")
        analyze_security
        ;;
    "performance")
        analyze_performance
        ;;
    "summary")
        generate_summary_report
        ;;
    "cleanup")
        cleanup_old_reports
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