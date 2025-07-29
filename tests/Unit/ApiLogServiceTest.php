<?php

namespace Tests\Unit;

use App\Models\ApiLog;
use App\Models\User;
use App\Services\ApiLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 日誌服務單元測試
 */
class ApiLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiLogService();
    }

    /**
     * 測試取得 API 使用統計
     */
    public function test_get_api_usage_stats(): void
    {
        $user = User::factory()->create();
        
        // 建立測試資料
        ApiLog::factory()->count(5)->create(['user_id' => $user->id, 'status_code' => 200]);
        ApiLog::factory()->count(3)->create(['user_id' => $user->id, 'status_code' => 400]);
        ApiLog::factory()->count(2)->create(['user_id' => null, 'status_code' => 200]); // 匿名使用者

        $stats = $this->service->getApiUsageStats();

        $this->assertEquals(10, $stats['total_requests']);
        $this->assertEquals(7, $stats['successful_requests']);
        $this->assertEquals(3, $stats['failed_requests']);
        $this->assertEquals(1, $stats['unique_users']); // 只計算有 user_id 的
        $this->assertIsFloat($stats['average_response_time']);
        $this->assertIsArray($stats['top_actions']);
        $this->assertIsArray($stats['error_distribution']);
    }

    /**
     * 測試取得最常使用的動作
     */
    public function test_get_top_actions(): void
    {
        // 建立不同動作類型的日誌
        ApiLog::factory()->count(5)->actionType('get_user_info')->create();
        ApiLog::factory()->count(3)->actionType('update_profile')->create();
        ApiLog::factory()->count(2)->actionType('delete_post')->create();

        $topActions = $this->service->getTopActions();

        $this->assertCount(3, $topActions);
        $this->assertEquals('get_user_info', $topActions[0]['action_type']);
        $this->assertEquals(5, $topActions[0]['count']);
        $this->assertEquals('update_profile', $topActions[1]['action_type']);
        $this->assertEquals(3, $topActions[1]['count']);
    }

    /**
     * 測試取得錯誤分佈
     */
    public function test_get_error_distribution(): void
    {
        // 建立不同錯誤狀態碼的日誌
        ApiLog::factory()->count(3)->create(['status_code' => 404]);
        ApiLog::factory()->count(2)->create(['status_code' => 500]);
        ApiLog::factory()->count(1)->create(['status_code' => 400]);
        ApiLog::factory()->count(5)->create(['status_code' => 200]); // 成功請求，不應包含

        $errorDistribution = $this->service->getErrorDistribution();

        $this->assertCount(3, $errorDistribution);
        $this->assertEquals(404, $errorDistribution[0]['status_code']);
        $this->assertEquals(3, $errorDistribution[0]['count']);
        $this->assertEquals(500, $errorDistribution[1]['status_code']);
        $this->assertEquals(2, $errorDistribution[1]['count']);
    }

    /**
     * 測試取得使用者活動日誌
     */
    public function test_get_user_activity_logs(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        ApiLog::factory()->count(3)->create(['user_id' => $user1->id]);
        ApiLog::factory()->count(2)->create(['user_id' => $user2->id]);

        $userLogs = $this->service->getUserActivityLogs($user1->id);

        $this->assertEquals(3, $userLogs['total']);
        $this->assertCount(3, $userLogs['data']);
    }

    /**
     * 測試取得效能分析資料
     */
    public function test_get_performance_analysis(): void
    {
        // 建立不同回應時間的日誌
        ApiLog::factory()->create(['response_time' => 100, 'action_type' => 'fast_action']);
        ApiLog::factory()->create(['response_time' => 500, 'action_type' => 'medium_action']);
        ApiLog::factory()->create(['response_time' => 1000, 'action_type' => 'slow_action']);
        ApiLog::factory()->create(['response_time' => 2000, 'action_type' => 'slow_action']);

        $analysis = $this->service->getPerformanceAnalysis();

        $this->assertArrayHasKey('response_time_percentiles', $analysis);
        $this->assertArrayHasKey('slowest_actions', $analysis);
        $this->assertArrayHasKey('hourly_distribution', $analysis);
        
        $this->assertIsFloat($analysis['response_time_percentiles']['p50']);
        $this->assertIsFloat($analysis['response_time_percentiles']['p90']);
        $this->assertIsFloat($analysis['response_time_percentiles']['p95']);
        $this->assertIsFloat($analysis['response_time_percentiles']['p99']);
    }

    /**
     * 測試取得最慢的動作
     */
    public function test_get_slowest_actions(): void
    {
        // 建立不同平均回應時間的動作
        ApiLog::factory()->count(2)->create(['action_type' => 'slow_action', 'response_time' => 1000]);
        ApiLog::factory()->count(3)->create(['action_type' => 'fast_action', 'response_time' => 100]);

        $slowestActions = $this->service->getSlowestActions();

        $this->assertCount(2, $slowestActions);
        $this->assertEquals('slow_action', $slowestActions[0]['action_type']);
        $this->assertEquals(1000, $slowestActions[0]['avg_response_time']);
        $this->assertEquals('fast_action', $slowestActions[1]['action_type']);
        $this->assertEquals(100, $slowestActions[1]['avg_response_time']);
    }

    /**
     * 測試清理舊日誌
     */
    public function test_cleanup_old_logs(): void
    {
        // 建立新舊日誌
        ApiLog::factory()->create(['created_at' => now()->subDays(40)]); // 舊日誌
        ApiLog::factory()->create(['created_at' => now()->subDays(20)]); // 新日誌
        ApiLog::factory()->create(['created_at' => now()->subDays(10)]); // 新日誌

        $deletedCount = $this->service->cleanupOldLogs(30);

        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(2, ApiLog::count());
    }

    /**
     * 測試篩選條件的應用
     */
    public function test_applies_filters_correctly(): void
    {
        $user = User::factory()->create();
        $startDate = now()->subDays(5);
        $endDate = now()->subDays(1);
        
        // 建立符合條件的日誌
        ApiLog::factory()->create([
            'user_id' => $user->id,
            'action_type' => 'test_action',
            'status_code' => 200,
            'created_at' => now()->subDays(3),
        ]);
        
        // 建立不符合條件的日誌
        ApiLog::factory()->create([
            'user_id' => $user->id,
            'action_type' => 'other_action',
            'status_code' => 404,
            'created_at' => now()->subDays(10),
        ]);

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => $user->id,
            'action_type' => 'test_action',
            'status_code' => 200,
        ];

        $stats = $this->service->getApiUsageStats($filters);

        $this->assertEquals(1, $stats['total_requests']);
        $this->assertEquals(1, $stats['successful_requests']);
        $this->assertEquals(0, $stats['failed_requests']);
    }

    /**
     * 測試匯出 CSV 格式
     */
    public function test_export_logs_csv(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        ApiLog::factory()->create([
            'user_id' => $user->id,
            'action_type' => 'test_action',
            'status_code' => 200,
            'response_time' => 123.45,
            'ip_address' => '192.168.1.1',
        ]);

        $csv = $this->service->exportLogs([], 'csv');

        $this->assertStringContainsString('ID,使用者ID,使用者名稱,動作類型,狀態碼,回應時間,IP位址,建立時間', $csv);
        $this->assertStringContainsString('Test User', $csv);
        $this->assertStringContainsString('test_action', $csv);
        $this->assertStringContainsString('200', $csv);
        $this->assertStringContainsString('123.450', $csv);
        $this->assertStringContainsString('192.168.1.1', $csv);
    }

    /**
     * 測試匯出 JSON 格式
     */
    public function test_export_logs_json(): void
    {
        $user = User::factory()->create();
        ApiLog::factory()->create(['user_id' => $user->id]);

        $json = $this->service->exportLogs([], 'json');
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals($user->id, $data[0]['user_id']);
    }

    /**
     * 測試不支援的匯出格式
     */
    public function test_export_logs_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不支援的匯出格式: xml');

        $this->service->exportLogs([], 'xml');
    }
}
