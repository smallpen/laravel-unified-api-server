<?php

namespace Tests\Unit;

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ApiLog 模型單元測試
 */
class ApiLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試 ApiLog 模型的基本建立功能
     */
    public function test_can_create_api_log(): void
    {
        $user = User::factory()->create();
        
        $logData = [
            'user_id' => $user->id,
            'action_type' => 'test_action',
            'request_data' => ['param1' => 'value1'],
            'response_data' => ['result' => 'success'],
            'response_time' => 123.45,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'status_code' => 200,
            'request_id' => 'test-request-id',
        ];

        $apiLog = ApiLog::create($logData);

        $this->assertInstanceOf(ApiLog::class, $apiLog);
        $this->assertEquals($user->id, $apiLog->user_id);
        $this->assertEquals('test_action', $apiLog->action_type);
        $this->assertEquals(['param1' => 'value1'], $apiLog->request_data);
        $this->assertEquals(['result' => 'success'], $apiLog->response_data);
        $this->assertEquals(123.45, $apiLog->response_time);
        $this->assertEquals('192.168.1.1', $apiLog->ip_address);
        $this->assertEquals('Test Agent', $apiLog->user_agent);
        $this->assertEquals(200, $apiLog->status_code);
        $this->assertEquals('test-request-id', $apiLog->request_id);
    }

    /**
     * 測試與使用者的關聯
     */
    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $apiLog = ApiLog::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $apiLog->user);
        $this->assertEquals($user->id, $apiLog->user->id);
    }

    /**
     * 測試可以建立沒有使用者的日誌（匿名請求）
     */
    public function test_can_create_log_without_user(): void
    {
        $logData = [
            'user_id' => null,
            'action_type' => 'anonymous_action',
            'request_data' => ['param1' => 'value1'],
            'response_data' => ['result' => 'success'],
            'response_time' => 100.0,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'status_code' => 200,
            'request_id' => 'anonymous-request-id',
        ];

        $apiLog = ApiLog::create($logData);

        $this->assertNull($apiLog->user_id);
        $this->assertNull($apiLog->user);
    }

    /**
     * 測試根據動作類型查詢的範圍
     */
    public function test_by_action_type_scope(): void
    {
        ApiLog::factory()->create(['action_type' => 'action_a']);
        ApiLog::factory()->create(['action_type' => 'action_b']);
        ApiLog::factory()->create(['action_type' => 'action_a']);

        $logs = ApiLog::byActionType('action_a')->get();

        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn($log) => $log->action_type === 'action_a'));
    }

    /**
     * 測試根據使用者查詢的範圍
     */
    public function test_by_user_scope(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        ApiLog::factory()->create(['user_id' => $user1->id]);
        ApiLog::factory()->create(['user_id' => $user2->id]);
        ApiLog::factory()->create(['user_id' => $user1->id]);

        $logs = ApiLog::byUser($user1->id)->get();

        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn($log) => $log->user_id === $user1->id));
    }

    /**
     * 測試根據狀態碼查詢的範圍
     */
    public function test_by_status_code_scope(): void
    {
        ApiLog::factory()->create(['status_code' => 200]);
        ApiLog::factory()->create(['status_code' => 404]);
        ApiLog::factory()->create(['status_code' => 200]);

        $logs = ApiLog::byStatusCode(200)->get();

        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn($log) => $log->status_code === 200));
    }

    /**
     * 測試錯誤日誌查詢範圍
     */
    public function test_errors_scope(): void
    {
        ApiLog::factory()->create(['status_code' => 200]);
        ApiLog::factory()->create(['status_code' => 400]);
        ApiLog::factory()->create(['status_code' => 500]);
        ApiLog::factory()->create(['status_code' => 201]);

        $errorLogs = ApiLog::errors()->get();

        $this->assertCount(2, $errorLogs);
        $this->assertTrue($errorLogs->every(fn($log) => $log->status_code >= 400));
    }

    /**
     * 測試成功日誌查詢範圍
     */
    public function test_successful_scope(): void
    {
        ApiLog::factory()->create(['status_code' => 200]);
        ApiLog::factory()->create(['status_code' => 400]);
        ApiLog::factory()->create(['status_code' => 201]);
        ApiLog::factory()->create(['status_code' => 500]);

        $successfulLogs = ApiLog::successful()->get();

        $this->assertCount(2, $successfulLogs);
        $this->assertTrue($successfulLogs->every(fn($log) => $log->status_code < 400));
    }

    /**
     * 測試日期範圍查詢範圍
     */
    public function test_date_range_scope(): void
    {
        $startDate = now()->subDays(5);
        $endDate = now()->subDays(1);
        
        ApiLog::factory()->create(['created_at' => now()->subDays(10)]);
        ApiLog::factory()->create(['created_at' => now()->subDays(3)]);
        ApiLog::factory()->create(['created_at' => now()]);

        $logs = ApiLog::dateRange($startDate, $endDate)->get();

        $this->assertCount(1, $logs);
    }

    /**
     * 測試屬性轉換
     */
    public function test_attribute_casting(): void
    {
        $apiLog = ApiLog::factory()->create([
            'request_data' => ['key' => 'value'],
            'response_data' => ['result' => 'success'],
            'response_time' => '123.45',
            'status_code' => '200',
        ]);

        $this->assertIsArray($apiLog->request_data);
        $this->assertIsArray($apiLog->response_data);
        $this->assertIsFloat($apiLog->response_time);
        $this->assertIsInt($apiLog->status_code);
        $this->assertInstanceOf(\Carbon\Carbon::class, $apiLog->created_at);
    }
}
