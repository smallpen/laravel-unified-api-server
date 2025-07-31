<?php

/**
 * 簡單除錯腳本
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 簡單除錯 ===\n";

try {
    // 建立應用程式實例
    $app = require_once __DIR__ . '/bootstrap/app.php';
    echo "✓ 應用程式實例建立成功\n";
    
    // 啟動應用程式
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "✓ 應用程式啟動成功\n";
    
    // 檢查設定是否載入
    echo "APP_ENV: " . env('APP_ENV', 'not set') . "\n";
    echo "APP_NAME: " . env('APP_NAME', 'not set') . "\n";
    
    // 檢查類別是否存在
    $classes = [
        'App\Contracts\ResponseFormatterInterface',
        'App\Services\ResponseFormatter',
        'App\Providers\ResponseFormatterServiceProvider'
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class) || interface_exists($class)) {
            echo "✓ 類別存在: $class\n";
        } else {
            echo "✗ 類別不存在: $class\n";
        }
    }
    
    // 檢查服務提供者是否在設定中
    $configPath = __DIR__ . '/config/app.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        $providers = $config['providers'] ?? [];
        
        $found = false;
        foreach ($providers as $provider) {
            if (strpos($provider, 'ResponseFormatterServiceProvider') !== false) {
                echo "✓ 在設定中找到: $provider\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "✗ 在設定中未找到 ResponseFormatterServiceProvider\n";
        }
    }
    
    // 嘗試手動註冊並測試
    echo "\n--- 手動註冊測試 ---\n";
    
    $provider = new App\Providers\ResponseFormatterServiceProvider($app);
    $provider->register();
    echo "✓ 手動註冊完成\n";
    
    // 測試綁定
    if ($app->bound('App\Contracts\ResponseFormatterInterface')) {
        echo "✓ 介面已綁定\n";
        
        $formatter = $app->make('App\Contracts\ResponseFormatterInterface');
        echo "✓ 實例化成功: " . get_class($formatter) . "\n";
        
        // 測試基本功能
        $result = $formatter->success(['test' => 'data'], '測試成功');
        echo "✓ 功能測試通過\n";
        
    } else {
        echo "✗ 介面未綁定\n";
    }
    
} catch (\Exception $e) {
    echo "✗ 錯誤: " . $e->getMessage() . "\n";
    echo "檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
}