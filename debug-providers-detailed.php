<?php

/**
 * 詳細的服務提供者除錯腳本
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 詳細服務提供者除錯 ===\n";

try {
    // 建立應用程式實例但不啟動
    $app = require_once __DIR__ . '/bootstrap/app.php';
    echo "✓ 應用程式實例建立成功\n";
    
    // 檢查設定檔
    $configPath = __DIR__ . '/config/app.php';
    $config = require $configPath;
    $providers = $config['providers'] ?? [];
    
    echo "\n--- 檢查設定檔中的服務提供者 ---\n";
    $responseFormatterFound = false;
    foreach ($providers as $index => $provider) {
        if (strpos($provider, 'ResponseFormatter') !== false) {
            echo "✓ 找到 ResponseFormatterServiceProvider 在位置 $index: $provider\n";
            $responseFormatterFound = true;
        }
        if (strpos($provider, 'ActionService') !== false) {
            echo "  ActionServiceProvider 在位置 $index: $provider\n";
        }
        if (strpos($provider, 'ExceptionService') !== false) {
            echo "  ExceptionServiceProvider 在位置 $index: $provider\n";
        }
    }
    
    if (!$responseFormatterFound) {
        echo "✗ 未在設定檔中找到 ResponseFormatterServiceProvider\n";
        exit(1);
    }
    
    // 手動測試服務提供者
    echo "\n--- 手動測試 ResponseFormatterServiceProvider ---\n";
    
    try {
        $provider = new App\Providers\ResponseFormatterServiceProvider($app);
        echo "✓ ResponseFormatterServiceProvider 實例建立成功\n";
        
        // 呼叫 register 方法
        $provider->register();
        echo "✓ register() 方法執行成功\n";
        
        // 檢查綁定
        $bindings = [
            App\Contracts\ResponseFormatterInterface::class,
            App\Services\ResponseFormatter::class,
            'response.formatter'
        ];
        
        foreach ($bindings as $binding) {
            if ($app->bound($binding)) {
                echo "✓ 已綁定: $binding\n";
                try {
                    $instance = $app->make($binding);
                    echo "  實例類別: " . get_class($instance) . "\n";
                } catch (\Exception $e) {
                    echo "  ✗ 實例化失敗: " . $e->getMessage() . "\n";
                }
            } else {
                echo "✗ 未綁定: $binding\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "✗ ResponseFormatterServiceProvider 測試失敗: " . $e->getMessage() . "\n";
        echo "  檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    // 測試完整的應用程式啟動
    echo "\n--- 測試完整應用程式啟動 ---\n";
    
    try {
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        echo "✓ 應用程式啟動成功\n";
        
        // 檢查已載入的服務提供者
        $loadedProviders = $app->getLoadedProviders();
        $responseFormatterLoaded = false;
        
        foreach ($loadedProviders as $provider => $loaded) {
            if (strpos($provider, 'ResponseFormatter') !== false) {
                echo "✓ 已載入服務提供者: $provider\n";
                $responseFormatterLoaded = true;
            }
        }
        
        if (!$responseFormatterLoaded) {
            echo "✗ ResponseFormatterServiceProvider 未被載入\n";
            
            // 列出所有已載入的應用程式服務提供者
            echo "\n已載入的應用程式服務提供者:\n";
            foreach ($loadedProviders as $provider => $loaded) {
                if (strpos($provider, 'App\\Providers') === 0) {
                    echo "  - $provider\n";
                }
            }
        }
        
        // 最終測試綁定
        echo "\n--- 最終綁定測試 ---\n";
        if ($app->bound(App\Contracts\ResponseFormatterInterface::class)) {
            echo "✓ ResponseFormatterInterface 已綁定\n";
            $formatter = $app->make(App\Contracts\ResponseFormatterInterface::class);
            echo "✓ 實例化成功: " . get_class($formatter) . "\n";
        } else {
            echo "✗ ResponseFormatterInterface 未綁定\n";
        }
        
    } catch (\Exception $e) {
        echo "✗ 應用程式啟動失敗: " . $e->getMessage() . "\n";
        echo "  檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "  堆疊追蹤:\n" . $e->getTraceAsString() . "\n";
    }
    
} catch (\Exception $e) {
    echo "✗ 除錯過程發生錯誤: " . $e->getMessage() . "\n";
    echo "檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
}