<?php

namespace Tests\Performance;

use PHPUnit\Framework\TestCase;

/**
 * 簡單效能測試
 * 
 * 驗證測試環境是否正常工作
 */
class SimplePerformanceTest extends TestCase
{
    /**
     * 測試基本效能測試功能
     */
    public function test_basic_performance_measurement()
    {
        $startTime = microtime(true);
        
        // 模擬一些工作
        usleep(1000); // 1毫秒
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // 轉換為毫秒
        
        $this->assertGreaterThan(0, $duration);
        $this->assertLessThan(100, $duration); // 應該小於100毫秒
    }

    /**
     * 測試記憶體使用量測量
     */
    public function test_memory_usage_measurement()
    {
        $initialMemory = memory_get_usage();
        
        // 分配一些記憶體
        $data = array_fill(0, 1000, 'test');
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        $this->assertGreaterThan(0, $memoryIncrease);
        
        // 清理
        unset($data);
    }

    /**
     * 測試迴圈效能
     */
    public function test_loop_performance()
    {
        $iterations = 10000;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            // 簡單的計算
            $result = $i * 2 + 1;
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        
        // 10000次迴圈應該很快完成
        $this->assertLessThan(100, $duration);
    }
}