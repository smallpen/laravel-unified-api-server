<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $apiTitle }} - API文件</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.3/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.3/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #fafafa;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .swagger-ui .topbar {
            background-color: #1b1b1b;
            border-bottom: 1px solid #d4d4d4;
        }

        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }

        .swagger-ui .info {
            margin: 50px 0;
        }

        .swagger-ui .info .title {
            font-size: 36px;
            margin: 0;
            font-family: "Titillium Web", sans-serif;
            color: #3b4151;
            font-weight: 600;
        }

        .swagger-ui .info .description {
            font-size: 14px;
            margin: 20px 0;
            color: #3b4151;
        }

        .swagger-ui .info .version {
            font-size: 14px;
            padding: 4px 8px;
            background: #89bf04;
            color: #fff;
            border-radius: 4px;
            margin-left: 10px;
        }

        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-bottom: 20px;
        }

        .custom-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }

        .custom-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .api-info-panel {
            background: white;
            border: 1px solid #d4d4d4;
            border-radius: 4px;
            margin: 20px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .api-info-panel h3 {
            margin-top: 0;
            color: #3b4151;
            font-size: 18px;
        }

        .api-info-panel .info-item {
            margin: 10px 0;
            display: flex;
            align-items: center;
        }

        .api-info-panel .info-label {
            font-weight: 600;
            color: #3b4151;
            min-width: 80px;
            margin-right: 10px;
        }

        .api-info-panel .info-value {
            color: #666;
        }

        .refresh-button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
            transition: background-color 0.3s;
        }

        .refresh-button:hover {
            background: #45a049;
        }

        .refresh-button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        .loading-indicator {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px;
            display: none;
        }

        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
        }

        .status-healthy {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .auto-refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            display: none;
        }

        .auto-refresh-indicator.show {
            display: block;
            animation: fadeInOut 3s ease-in-out;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .custom-header h1 {
                font-size: 24px;
            }
            
            .api-info-panel {
                margin: 10px;
                padding: 15px;
            }
            
            .api-info-panel .info-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .api-info-panel .info-label {
                min-width: auto;
                margin-bottom: 5px;
            }

            .status-indicator {
                margin-left: 0;
                margin-top: 5px;
            }

            .auto-refresh-indicator {
                top: 10px;
                right: 10px;
                left: 10px;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div class="custom-header">
        <h1>{{ $apiTitle }}</h1>
        <p>{{ $apiDescription }}</p>
        <p>版本: {{ $apiVersion }}</p>
    </div>

    <div class="api-info-panel">
        <h3>📋 API資訊</h3>
        <div class="info-item">
            <span class="info-label">接口路徑:</span>
            <span class="info-value">{{ config('app.url') }}/api/</span>
        </div>
        <div class="info-item">
            <span class="info-label">請求方法:</span>
            <span class="info-value">POST</span>
        </div>
        <div class="info-item">
            <span class="info-label">驗證方式:</span>
            <span class="info-value">Bearer Token</span>
        </div>
        <div class="info-item">
            <span class="info-label">內容類型:</span>
            <span class="info-value">application/json</span>
        </div>
        <div class="info-item">
            <span class="info-label">文件更新:</span>
            <span class="info-value" id="last-updated">載入中...</span>
            <span class="status-indicator" id="status-indicator">檢查中...</span>
            <button class="refresh-button" onclick="refreshDocumentation()" id="refresh-btn">
                🔄 重新整理文件
            </button>
        </div>
    </div>

    <div class="success-message" id="success-message"></div>
    <div class="error-message" id="error-message" style="display: none;"></div>
    <div class="loading-indicator" id="loading-indicator">
        <p>🔄 正在載入API文件...</p>
    </div>
    <div class="auto-refresh-indicator" id="auto-refresh-indicator">
        🔄 檢測到文件更新
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-standalone-preset.js"></script>
    <script>
        // Swagger UI 配置
        const ui = SwaggerUIBundle({
            url: '{{ $openApiUrl }}',
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],
            layout: "StandaloneLayout",
            validatorUrl: null,
            tryItOutEnabled: true,
            supportedSubmitMethods: ['post'],
            onComplete: function() {
                console.log('Swagger UI 載入完成');
                updateLastUpdatedTime();
                hideLoading();
            },
            onFailure: function(error) {
                console.error('Swagger UI 載入失敗:', error);
                showError('無法載入API文件: ' + error.message);
                hideLoading();
            },
            requestInterceptor: function(request) {
                // 為所有請求添加 CORS 標頭
                request.headers['Access-Control-Allow-Origin'] = '*';
                return request;
            },
            responseInterceptor: function(response) {
                // 處理回應
                return response;
            }
        });

        // 顯示載入指示器
        function showLoading() {
            document.getElementById('loading-indicator').style.display = 'block';
            document.getElementById('refresh-btn').disabled = true;
        }

        // 隱藏載入指示器
        function hideLoading() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('refresh-btn').disabled = false;
        }

        // 顯示錯誤訊息
        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            
            // 5秒後自動隱藏
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        // 顯示成功訊息
        function showSuccess(message) {
            const successDiv = document.getElementById('success-message');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            
            // 3秒後自動隱藏
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        }

        // 更新最後更新時間
        function updateLastUpdatedTime() {
            const now = new Date();
            const timeString = now.toLocaleString('zh-TW', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('last-updated').textContent = timeString;
        }

        // 重新整理文件
        function refreshDocumentation() {
            showLoading();
            
            fetch('{{ route("api.docs.regenerate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess('API文件已成功重新生成');
                    // 重新載入 Swagger UI
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showError('重新生成文件失敗: ' + data.message);
                }
            })
            .catch(error => {
                console.error('重新生成文件時發生錯誤:', error);
                showError('重新生成文件時發生錯誤: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }

        // 頁面載入時顯示載入指示器
        document.addEventListener('DOMContentLoaded', function() {
            showLoading();
        });

        // 監聽鍵盤快捷鍵
        document.addEventListener('keydown', function(event) {
            // Ctrl+R 或 F5 重新整理文件
            if ((event.ctrlKey && event.key === 'r') || event.key === 'F5') {
                event.preventDefault();
                refreshDocumentation();
            }
        });

        // 定期檢查文件更新（每30秒）
        let lastCheckTime = null;
        setInterval(function() {
            checkDocumentationStatus();
        }, 30000); // 30秒

        // 檢查文件更新狀態
        function checkDocumentationStatus() {
            fetch('{{ route("api.docs.status") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const status = data.data;
                        
                        // 檢查是否有新的文件生成
                        if (lastCheckTime && status.last_generated && 
                            new Date(status.last_generated) > new Date(lastCheckTime)) {
                            showSuccess('檢測到文件更新，正在重新載入...');
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                        
                        lastCheckTime = data.timestamp;
                        console.log('文件狀態:', status);
                        
                        // 更新狀態指示器
                        updateStatusIndicator(status);
                    }
                })
                .catch(error => {
                    console.warn('無法取得文件狀態:', error);
                });
        }

        // 更新狀態指示器
        function updateStatusIndicator(status) {
            const statusElement = document.getElementById('status-indicator');
            if (statusElement) {
                const isHealthy = status.failed_generations === 0 && status.warnings_count < 5;
                statusElement.className = isHealthy ? 'status-healthy' : 'status-warning';
                statusElement.textContent = isHealthy ? '正常' : '警告';
            }
        }

        // 初始檢查
        setTimeout(checkDocumentationStatus, 1000);

        // 處理視窗大小變化
        window.addEventListener('resize', function() {
            // Swagger UI 會自動處理響應式佈局
        });
    </script>
</body>
</html>