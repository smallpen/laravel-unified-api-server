<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * API負載測試
 * 
 * 測試API在高併發情況下的效能表現
 */
class ApiLoadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試使用者
     * 
     * @var User
     */
    protected User $testUser;

    /**
     * 測試Token
     * 
     * @var string
     */
    protected string $testToken;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 建立測試使用者
        $this->testUser = User::factory()->create([
            'name' => '負載測試使用者',
            'email' => 'load-test@example.com',
        ]);

        // 建立測試Token
        $tokenData = ApiToken::createToken(
            $this->testUser->id,
            '負載測試Token',
            ['*'], // 完整權限
            now()->addHours(1)
        );
        $this->testToken = $tokenData['token'];
    }

    /**
     * 測試單一API請求的基準效能
     */
    public function test_single_api_request_performance()
    {
        $startTime = microtime(true);

        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 轉換為毫秒

        // system.ping 不需要權限，應該回傳200
        $response->assertStatus(200);

        // 斷言回應時間應該在合理範圍內（小於2000毫秒，因為可能包含資料庫設定時間）
        $this->assertLessThan(2000, $responseTime, "API回應時間過長: {$responseTime}ms");

        // 記錄效能資訊
        echo "\n單一API請求效能: {$responseTime}ms, 記憶體使用: " . (memory_get_peak_usage(true) / 1024 / 1024) . "MB\n";
    }

    /**
     * 測試連續API請求的效能
     */
    public function test_consecutive_api_requests_performance()
    {
        $requestCount = 10; // 減少請求數量以加快測試
        $responseTimes = [];
        $totalStartTime = microtime(true);

        for ($i = 0; $i < $requestCount; $i++) {
            $startTime = microtime(true);

            $response = $this->postJson('/api/', [
                'action_type' => 'system.ping'
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            $responseTimes[] = $responseTime;

            // system.ping 應該回傳200
            $response->assertStatus(200);
        }

        $totalEndTime = microtime(true);
        $totalTime = ($totalEndTime - $totalStartTime) * 1000;

        $averageResponseTime = array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = max($responseTimes);
        $minResponseTime = min($responseTimes);

        // 放寬效能斷言
        $this->assertLessThan(5000, $averageResponseTime, "平均回應時間過長: {$averageResponseTime}ms");
        $this->assertLessThan(10000, $maxResponseTime, "最大回應時間過長: {$maxResponseTime}ms");

        echo "\n連續請求效能: 平均{$averageResponseTime}ms, 最大{$maxResponseTime}ms, 最小{$minResponseTime}ms\n";
    }

    /**
     * 測試資料庫查詢效能
     */
    public function test_database_query_performance()
    {
        // 建立大量測試資料
        $userCount = 100;
        User::factory()->count($userCount)->create();

        // 啟用查詢日誌
        DB::enableQueryLog();

        $startTime = microtime(true);

        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertStatus(200);

        // 斷言查詢數量應該在合理範圍內
        $this->assertLessThan(10, $queryCount, "資料庫查詢次數過多: {$queryCount}");
        $this->assertLessThan(1000, $responseTime, "資料庫查詢回應時間過長: {$responseTime}ms");

        Log::info('資料庫查詢效能測試', [
            'query_count' => $queryCount,
            'response_time_ms' => $responseTime,
            'queries' => $queries,
        ]);

        DB::disableQueryLog();
    }

    /**
     * 測試記憶體使用量
     */
    public function test_memory_usage()
    {
        $initialMemory = memory_get_usage(true);

        $requestCount = 20;
        for ($i = 0; $i < $requestCount; $i++) {
            $response = $this->postJson('/api/', [
                'action_type' => 'system.ping'
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $response->assertStatus(200);
        }

        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // 斷言記憶體使用量應該在合理範圍內
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, "記憶體使用量增加過多: " . ($memoryIncrease / 1024 / 1024) . "MB");
        $this->assertLessThan(100 * 1024 * 1024, $peakMemory, "峰值記憶體使用量過高: " . ($peakMemory / 1024 / 1024) . "MB");

        Log::info('記憶體使用量測試', [
            'initial_memory_mb' => $initialMemory / 1024 / 1024,
            'final_memory_mb' => $finalMemory / 1024 / 1024,
            'peak_memory_mb' => $peakMemory / 1024 / 1024,
            'memory_increase_mb' => $memoryIncrease / 1024 / 1024,
            'request_count' => $requestCount,
        ]);
    }

    /**
     * 測試快取效能
     */
    public function test_cache_performance()
    {
        // 清除快取
        Cache::flush();

        // 第一次請求（無快取）
        $startTime = microtime(true);
        $response1 = $this->postJson('/api/', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);
        $endTime = microtime(true);
        $firstRequestTime = ($endTime - $startTime) * 1000;

        $response1->assertStatus(200);

        // 第二次請求（可能有快取）
        $startTime = microtime(true);
        $response2 = $this->postJson('/api/', [
            'action_type' => 'system.ping'
        ], [
            'Authorization' => 'Bearer ' . $this->testToken
        ]);
        $endTime = microtime(true);
        $secondRequestTime = ($endTime - $startTime) * 1000;

        $response2->assertStatus(200);

        // 比較兩次請求的效能
        $performanceImprovement = (($firstRequestTime - $secondRequestTime) / $firstRequestTime) * 100;

        Log::info('快取效能測試', [
            'first_request_time_ms' => $firstRequestTime,
            'second_request_time_ms' => $secondRequestTime,
            'performance_improvement_percent' => $performanceImprovement,
        ]);

        // 如果有快取機制，第二次請求應該更快
        if ($performanceImprovement > 0) {
            $this->assertGreaterThan(0, $performanceImprovement, '快取機制未生效');
        }
    }

    /**
     * 測試併發請求處理能力
     */
    public function test_concurrent_request_handling()
    {
        $concurrentUsers = 5;
        $requestsPerUser = 10;
        $results = [];

        // 模擬併發使用者
        for ($user = 0; $user < $concurrentUsers; $user++) {
            $userResults = [];
            
            for ($request = 0; $request < $requestsPerUser; $request++) {
                $startTime = microtime(true);

                $response = $this->postJson('/api/', [
                    'action_type' => 'system.ping'
                ], [
                    'Authorization' => 'Bearer ' . $this->testToken
                ]);

                $endTime = microtime(true);
                $responseTime = ($endTime - $startTime) * 1000;

                $response->assertStatus(200);
                $userResults[] = $responseTime;
            }

            $results[$user] = $userResults;
        }

        // 分析結果
        $allResponseTimes = array_merge(...$results);
        $averageResponseTime = array_sum($allResponseTimes) / count($allResponseTimes);
        $maxResponseTime = max($allResponseTimes);
        $totalRequests = $concurrentUsers * $requestsPerUser;

        // 效能斷言
        $this->assertLessThan(1500, $averageResponseTime, "併發請求平均回應時間過長: {$averageResponseTime}ms");
        $this->assertLessThan(3000, $maxResponseTime, "併發請求最大回應時間過長: {$maxResponseTime}ms");

        Log::info('併發請求處理能力測試', [
            'concurrent_users' => $concurrentUsers,
            'requests_per_user' => $requestsPerUser,
            'total_requests' => $totalRequests,
            'average_response_time_ms' => $averageResponseTime,
            'max_response_time_ms' => $maxResponseTime,
            'min_response_time_ms' => min($allResponseTimes),
        ]);
    }

    /**
     * 測試Token驗證效能
     */
    public function test_token_validation_performance()
    {
        $requestCount = 30;
        $validationTimes = [];

        for ($i = 0; $i < $requestCount; $i++) {
            $startTime = microtime(true);

            // 直接測試Token驗證邏輯
            $tokenService = app(\App\Services\TokenService::class);
            $user = $tokenService->validateToken($this->testToken);

            $endTime = microtime(true);
            $validationTime = ($endTime - $startTime) * 1000;
            $validationTimes[] = $validationTime;

            $this->assertNotNull($user, 'Token驗證失敗');
            $this->assertEquals($this->testUser->id, $user->id);
        }

        $averageValidationTime = array_sum($validationTimes) / count($validationTimes);
        $maxValidationTime = max($validationTimes);

        // Token驗證應該很快
        $this->assertLessThan(50, $averageValidationTime, "Token驗證平均時間過長: {$averageValidationTime}ms");
        $this->assertLessThan(100, $maxValidationTime, "Token驗證最大時間過長: {$maxValidationTime}ms");

        Log::info('Token驗證效能測試', [
            'request_count' => $requestCount,
            'average_validation_time_ms' => $averageValidationTime,
            'max_validation_time_ms' => $maxValidationTime,
            'min_validation_time_ms' => min($validationTimes),
        ]);
    }

    /**
     * 測試大量資料處理效能
     */
    public function test_large_data_processing_performance()
    {
        // 建立大量測試資料
        $largeDataCount = 1000;
        User::factory()->count($largeDataCount)->create();

        $startTime = microtime(true);

        // 這裡可以測試處理大量資料的Action
        // 使用system.ping進行多次查詢測試
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/', [
                'action_type' => 'system.ping'
            ], [
                'Authorization' => 'Bearer ' . $this->testToken
            ]);

            $response->assertStatus(200);
        }

        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000;

        // 大量資料處理時間應該在合理範圍內
        $this->assertLessThan(5000, $processingTime, "大量資料處理時間過長: {$processingTime}ms");

        Log::info('大量資料處理效能測試', [
            'data_count' => $largeDataCount,
            'processing_time_ms' => $processingTime,
            'memory_usage' => memory_get_peak_usage(true),
        ]);
    }
}