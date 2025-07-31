#!/bin/bash

echo "=== Laravel 統一 API 伺服器快速啟動 ==="

# 設定錯誤處理
set -e

# 檢查 Docker Compose 版本
echo "檢查 Docker Compose 版本..."
if docker compose version > /dev/null 2>&1; then
    echo "✓ Docker Compose 可用"
    docker compose version
else
    echo "✗ Docker Compose 不可用，請確保已安裝 Docker Desktop 或 Docker Engine"
    exit 1
fi

# 檢查必要檔案
echo ""
echo "檢查必要檔案..."
required_files=("docker compose.yml" "Dockerfile" ".env")
for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file 存在"
    else
        echo "✗ $file 不存在"
        if [ "$file" = ".env" ]; then
            echo "  建議：複製 .env.example 到 .env"
            if [ -f ".env.example" ]; then
                cp .env.example .env
                echo "  已自動複製 .env.example 到 .env"
            fi
        fi
    fi
done

# 停止現有服務
echo ""
echo "停止現有服務..."
docker compose down

# 建置服務
echo ""
echo "建置 Laravel 應用程式..."
docker compose build laravel

# 啟動服務
echo ""
echo "啟動所有服務..."
docker compose up -d

# 等待服務啟動
echo ""
echo "等待服務啟動..."
sleep 10

# 檢查服務狀態
echo ""
echo "檢查服務狀態..."
docker compose ps

# 檢查 Laravel 應用程式健康狀態
echo ""
echo "檢查 Laravel 應用程式..."
if docker compose exec laravel php artisan --version > /dev/null 2>&1; then
    echo "✓ Laravel 應用程式正常運行"
    docker compose exec laravel php artisan --version
else
    echo "✗ Laravel 應用程式可能有問題"
    echo "檢查日誌："
    docker compose logs laravel --tail=20
fi

# 顯示存取資訊
echo ""
echo "=== 服務存取資訊 ==="
echo "API 伺服器: http://localhost"
echo "PhpMyAdmin: http://localhost:8080 (開發模式)"
echo "Mailpit: http://localhost:8025 (開發模式)"
echo ""
echo "常用命令："
echo "  檢視日誌: docker compose logs laravel -f"
echo "  進入容器: docker compose exec laravel bash"
echo "  停止服務: docker compose down"
echo "  重新啟動: docker compose restart"
echo ""
echo "=== 啟動完成 ==="