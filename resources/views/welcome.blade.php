<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>統一API Server</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            margin: 2rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .status {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .links {
            margin-top: 2rem;
        }
        .links a {
            display: inline-block;
            margin: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .links a:hover {
            background: #0056b3;
        }
        .version {
            margin-top: 2rem;
            color: #999;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 統一API Server</h1>
        <div class="status">系統運行正常</div>
        <p>歡迎使用統一API服務器！這是一個基於Laravel框架構建的現代化API服務平台。</p>
        
        <div class="links">
            <a href="/health">健康檢查</a>
            <a href="/health/detailed">詳細狀態</a>
            <a href="/docs">API文檔</a>
        </div>
        
        <div class="version">
            Laravel {{ app()->version() }} | PHP {{ phpversion() }}
        </div>
    </div>
</body>
</html>