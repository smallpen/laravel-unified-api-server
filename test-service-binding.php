<?php

/**
 * 測試服務綁定腳本
 * 
 * 用於驗證 ResponseFormatterInterface 是否正確綁定
 */

require_once __DIR__ . '/vendor/autoload.php';

// 建立 Laravel 應用程式實例
$app = require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "=== Laravel 服務綁定測試 ===\n";
    
    // 測試 ResponseFormatterInterface 綁定
    echo "1. 測試 ResponseFormatterInterface 綁定...\n";
    $formatter = $app->make(\App\Contracts\ResponseFormatterInterface::class);
    echo "✓ ResponseFormatterInterface 綁定成功\n";
    echo "   實作類別: " . get_class($formatter) . "\n";
    
    // 測試 ResponseFormatter 直接綁定
    echo "\n2. 測試 ResponseFormatter 直接綁定...\n";
    $directFormatter = $app->make(\App\Services\ResponseFormatter::class);
    echo "✓ ResponseFormatter 綁定成功\n";
    echo "   類別: " . get_class($directFormatter) . "\n";
    
    // 測試別名綁定
    echo "\n3. 測試別名綁定...\n";
    $aliasFormatter = $app->make('response.formatter');
    echo "✓ response.formatter 別名綁定成功\n";
    echo "   類別: " . get_class($aliasFormatter) . "\n";
    
    // 測試是否為同一個實例（單例模式）
    echo "\n4. 測試單例模式...\n";
    $isSingleton = $formatter === $directFormatter && $directFormatter === $aliasFormatter;
    echo $isSingleton ? "✓ 單例模式正常工作\n" : "✗ 單例模式有問題\n";
    
    // 測試基本功能
    echo "\n5. 測試基本功能...\n";
    $result = $formatter->success(['test' => 'data'], '測試成功');
    echo "✓ success() 方法正常工作\n";
    echo "   回應結構: " . json_encode(array_keys($result), JSON_UNESCAPED_UNICODE) . "\n";
    
    // 測試 ExceptionHandlerService 綁定
    echo "\n6. 測試 ExceptionHandlerService 綁定...\n";
    try {
        $exceptionHandler = $app->make(\App\Services\ExceptionHandlerService::class);
        echo "✓ ExceptionHandlerService 綁定成功\n";
        echo "   類別: " . get_class($exceptionHandler) . "\n";
    } catch (\Exception $e) {
        echo "✗ ExceptionHandlerService 綁定失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 測試完成 ===\n";
    
} catch (\Exception $e) {
    echo "✗ 測試失敗: " . $e->getMessage() . "\n";
    echo "錯誤檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆疊追蹤:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}