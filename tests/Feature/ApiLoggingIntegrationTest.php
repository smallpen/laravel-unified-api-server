<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API 日誌整合測試
 */
class ApiLoggingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試 API 請求會被正確記錄
     */
    public function test_api_request_is_logged(): void
    {
        $response = $this->postJson('/api/test-logging', [
            'action_type' => 'test_logging',
            'test_param' => 'test_value',
        ], [
            'User-Agent' => 'Test Client/1.0',
        ]);

        $response->assertStatus(200);

        // 驗證日誌記錄
        $this->assertDatabaseHas('api_logs', [
            'action_type' => 'test_logging',
            'user_agent' => 'Test Client/1.0',
            'status_code' => 200,
        ]);

        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->request_data);
        $this->assertArrayHasKey('action_type', $log->request_data);
        $this->assertArrayHasKey('test_param', $log->request_data);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertNotEmpty($log->request_id);
        $this->assertNotEmpty($log->ip_address);
    }

    /**
     * 測試未授權請求也會被記錄
     */
    public function test_unauthorized_request_is_logged(): void
    {
        $response = $this->postJson('/api/', [
            'action_type' => 'get_user_info',
        ]);

        $response->assertStatus(401);

        // 驗證錯誤日誌記錄
        $this->assertDatabaseHas('api_logs', [
            'user_id' => null,
            'action_type' => 'get_user_info',
            'status_code' => 401,
        ]);
    }

    /**
     * 測試不同狀態碼的請求都會被記錄
     */
    public function test_different_status_codes_are_logged(): void
    {
        $user = User::factory()->create();
        
        $plainToken = \Illuminate\Support\Str::random(80);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Test Token',
            'permissions' => ['*'],
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // 成功請求
        $this->postJson('/api/', [
            'action_type' => 'get_user_info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 無效動作請求
        $this->postJson('/api/', [
            'action_type' => 'invalid_action',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證兩個請求都被記錄
        $logs = ApiLog::all();
        $this->assertCount(2, $logs);
        
        $successLog = $logs->where('action_type', 'get_user_info')->first();
        $errorLog = $logs->where('action_type', 'invalid_action')->first();
        
        $this->assertNotNull($successLog);
        $this->assertNotNull($errorLog);
        $this->assertNotEquals($successLog->status_code, $errorLog->status_code);
    }

    /**
     * 測試請求 ID 的唯一性
     */
    public function test_request_ids_are_unique(): void
    {
        $user = User::factory()->create();
        
        $plainToken = \Illuminate\Support\Str::random(80);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Test Token',
            'permissions' => ['*'],
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // 發送多個請求
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/', [
                'action_type' => 'test_action_' . $i,
            ], [
                'Authorization' => 'Bearer ' . $plainToken,
            ]);
        }

        $logs = ApiLog::all();
        $requestIds = $logs->pluck('request_id')->toArray();
        
        $this->assertCount(3, $logs);
        $this->assertEquals(3, count(array_unique($requestIds))); // 所有 request_id 都是唯一的
    }

    /**
     * 測試回應時間記錄
     */
    public function test_response_time_is_recorded(): void
    {
        $user = User::factory()->create();
        
        $plainToken = \Illuminate\Support\Str::random(80);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Test Token',
            'permissions' => ['*'],
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $this->postJson('/api/', [
            'action_type' => 'get_user_info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertIsFloat($log->response_time);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertLessThan(10000, $log->response_time); // 應該少於 10 秒
    }
}
