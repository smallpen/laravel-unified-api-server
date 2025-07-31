<?php

/**
 * 服務提供者除錯腳本
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

echo "=== Laravel 服務提供者除錯 ===\n";

try {
    // 檢查設定檔中的服務提供者
    echo "1. 檢查 config/app.php 中註冊的服務提供者...\n";
    $config = $app->make('config');
    $providers = $config->get('app.providers', []);
    
    $foundResponseFormatter = false;
    foreach ($providers as $provider) {
        if (strpos($provider, 'ResponseFormatter') !== false) {
            echo "✓ 找到 ResponseFormatterServiceProvider: $provider\n";
            $foundResponseFormatter = true;
        }
    }
    
    if (!$foundResponseFormatter) {
        echo "✗ 未找到 ResponseFormatterServiceProvider\n";
    }
    
    // 檢查已載入的服務提供者
    echo "\n2. 檢查已載入的服務提供者...\n";
    $loadedProviders = $app->getLoadedProviders();
    
    foreach ($loadedProviders as $provider => $loaded) {
        if (strpos($provider, 'ResponseFormatter') !== false) {
            echo "✓ 已載入: $provider\n";
        }
    }
    
    // 檢查容器中的綁定
    echo "\n3. 檢查容器綁定...\n";
    
    $bindings = [
        \App\Contracts\ResponseFormatterInterface::class,
        \App\Services\ResponseFormatter::class,
        'response.formatter'
    ];
    
    foreach ($bindings as $binding) {
        if ($app->bound($binding)) {
            echo "✓ 已綁定: $binding\n";
            try {
                $instance = $app->make($binding);
                echo "  實例類別: " . get_class($instance) . "\n";
            } catch (\Exception $e) {
                echo "  ✗ 無法實例化: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✗ 未綁定: $binding\n";
        }
    }
    
    // 檢查類別是否存在
    echo "\n4. 檢查類別是否存在...\n";
    
    $classes = [
        \App\Contracts\ResponseFormatterInterface::class,
        \App\Services\ResponseFormatter::class,
        \App\Providers\ResponseFormatterServiceProvider::class
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class) || interface_exists($class)) {
            echo "✓ 類別存在: $class\n";
        } else {
            echo "✗ 類別不存在: $class\n";
        }
    }
    
    // 嘗試手動註冊服務提供者
    echo "\n5. 嘗試手動註冊服務提供者...\n";
    try {
        $provider = new \App\Providers\ResponseFormatterServiceProvider($app);
        $provider->register();
        echo "✓ 手動註冊成功\n";
        
        // 再次檢查綁定
        if ($app->bound(\App\Contracts\ResponseFormatterInterface::class)) {
            echo "✓ 手動註冊後綁定成功\n";
            $instance = $app->make(\App\Contracts\ResponseFormatterInterface::class);
            echo "  實例類別: " . get_class($instance) . "\n";
        } else {
            echo "✗ 手動註冊後仍未綁定\n";
        }
        
    } catch (\Exception $e) {
        echo "✗ 手動註冊失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 除錯完成 ===\n";
    
} catch (\Exception $e) {
    echo "✗ 除錯過程發生錯誤: " . $e->getMessage() . "\n";
    echo "錯誤檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆疊追蹤:\n" . $e->getTraceAsString() . "\n";
}