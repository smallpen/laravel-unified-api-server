<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文件載入錯誤</title>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            color: #333;
        }

        .error-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .error-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .error-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }

        .error-header .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .error-content {
            padding: 30px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .error-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .error-details h3 {
            margin-top: 0;
            color: #495057;
        }

        .error-details pre {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            color: #495057;
        }

        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .troubleshooting {
            margin-top: 30px;
            padding: 20px;
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 4px;
        }

        .troubleshooting h3 {
            margin-top: 0;
            color: #004085;
        }

        .troubleshooting ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .troubleshooting li {
            margin: 8px 0;
            color: #004085;
        }

        .system-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 12px;
            color: #6c757d;
        }

        .system-info h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #495057;
        }

        .system-info .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .system-info .info-item {
            display: flex;
            justify-content: space-between;
        }

        .system-info .info-label {
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .error-container {
                margin: 20px;
            }
            
            .error-header, .error-content {
                padding: 20px;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-header">
            <div class="icon">⚠️</div>
            <h1>API文件載入失敗</h1>
        </div>

        <div class="error-content">
            <div class="error-message">
                <strong>錯誤:</strong> {{ $error }}
            </div>

            @if(isset($message) && $message)
            <div class="error-details">
                <h3>詳細錯誤資訊</h3>
                <pre>{{ $message }}</pre>
            </div>
            @endif

            <div class="troubleshooting">
                <h3>🔧 故障排除建議</h3>
                <ul>
                    <li>檢查Laravel應用程式是否正常運行</li>
                    <li>確認DocumentationGenerator服務是否正確註冊</li>
                    <li>檢查Action類別是否正確實作ActionInterface介面</li>
                    <li>確認資料庫連線是否正常</li>
                    <li>檢查應用程式日誌檔案以取得更多資訊</li>
                    <li>嘗試清除應用程式快取: <code>php artisan cache:clear</code></li>
                    <li>重新載入Action註冊: <code>php artisan config:clear</code></li>
                </ul>
            </div>

            <div class="system-info">
                <h4>系統資訊</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Laravel版本:</span>
                        <span>{{ app()->version() }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">PHP版本:</span>
                        <span>{{ PHP_VERSION }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">環境:</span>
                        <span>{{ config('app.env') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">除錯模式:</span>
                        <span>{{ config('app.debug') ? '開啟' : '關閉' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">時間:</span>
                        <span>{{ now()->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">時區:</span>
                        <span>{{ config('app.timezone') }}</span>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="location.reload()">
                    🔄 重新載入頁面
                </button>
                <a href="{{ route('api.docs.json') }}" class="btn btn-secondary" target="_blank">
                    📄 查看JSON格式文件
                </a>
                <a href="{{ config('app.url') }}" class="btn btn-secondary">
                    🏠 返回首頁
                </a>
            </div>
        </div>
    </div>

    <script>
        // 自動重試載入
        let retryCount = 0;
        const maxRetries = 3;
        const retryInterval = 5000; // 5秒

        function autoRetry() {
            if (retryCount < maxRetries) {
                retryCount++;
                console.log(`嘗試自動重新載入 (${retryCount}/${maxRetries})...`);
                
                setTimeout(() => {
                    location.reload();
                }, retryInterval);
            }
        }

        // 5秒後開始自動重試
        setTimeout(autoRetry, retryInterval);

        // 監聽鍵盤事件
        document.addEventListener('keydown', function(event) {
            // 按 R 鍵重新載入
            if (event.key === 'r' || event.key === 'R') {
                location.reload();
            }
            // 按 Escape 鍵返回首頁
            if (event.key === 'Escape') {
                window.location.href = '{{ config("app.url") }}';
            }
        });

        // 顯示載入提示
        console.log('API文件載入失敗，請檢查系統狀態');
        console.log('錯誤詳情:', '{{ $error }}');
        @if(isset($message))
        console.log('詳細訊息:', '{{ $message }}');
        @endif
    </script>
</body>
</html>