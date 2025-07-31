#!/bin/bash

# Laravel 開發常用命令腳本
# 使用現代 Docker Compose 語法

show_help() {
    echo "Laravel 統一 API 伺服器 - 開發命令"
    echo ""
    echo "用法: $0 [命令]"
    echo ""
    echo "可用命令："
    echo "  start          啟動所有服務"
    echo "  stop           停止所有服務"
    echo "  restart        重新啟動服務"
    echo "  build          重新建置 Laravel 容器"
    echo "  logs           顯示 Laravel 日誌"
    echo "  shell          進入 Laravel 容器"
    echo "  artisan        執行 Artisan 命令"
    echo "  composer       執行 Composer 命令"
    echo "  test           執行測試"
    echo "  migrate        執行資料庫遷移"
    echo "  fresh          重新建置並啟動（清除所有資料）"
    echo "  status         檢查服務狀態"
    echo "  clean          清理 Docker 資源"
    echo ""
    echo "範例："
    echo "  $0 artisan migrate"
    echo "  $0 composer install"
    echo "  $0 test --filter=ResponseFormatterTest"
}

case "$1" in
    "start")
        echo "啟動服務..."
        docker compose up -d
        ;;
    "stop")
        echo "停止服務..."
        docker compose down
        ;;
    "restart")
        echo "重新啟動服務..."
        docker compose restart
        ;;
    "build")
        echo "重新建置 Laravel 容器..."
        docker compose build laravel
        ;;
    "logs")
        echo "顯示 Laravel 日誌..."
        docker compose logs laravel -f
        ;;
    "shell")
        echo "進入 Laravel 容器..."
        docker compose exec laravel bash
        ;;
    "artisan")
        shift
        echo "執行 Artisan 命令: $@"
        docker compose exec laravel php artisan "$@"
        ;;
    "composer")
        shift
        echo "執行 Composer 命令: $@"
        docker compose exec laravel composer "$@"
        ;;
    "test")
        shift
        echo "執行測試: $@"
        docker compose exec laravel php artisan test "$@"
        ;;
    "migrate")
        echo "執行資料庫遷移..."
        docker compose exec laravel php artisan migrate
        ;;
    "fresh")
        echo "重新建置並啟動（這將清除所有資料）..."
        read -p "確定要繼續嗎？(y/N) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            docker compose down -v
            docker compose build --no-cache
            docker compose up -d
        else
            echo "操作已取消"
        fi
        ;;
    "status")
        echo "檢查服務狀態..."
        docker compose ps
        echo ""
        echo "檢查健康狀態..."
        docker compose exec laravel php artisan --version 2>/dev/null && echo "✓ Laravel 正常" || echo "✗ Laravel 異常"
        ;;
    "clean")
        echo "清理 Docker 資源..."
        docker compose down
        docker system prune -f
        docker builder prune -f
        ;;
    "help"|"--help"|"-h"|"")
        show_help
        ;;
    *)
        echo "未知命令: $1"
        echo ""
        show_help
        exit 1
        ;;
esac