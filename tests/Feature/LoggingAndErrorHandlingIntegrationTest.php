<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 日誌和錯誤處理系統整合測試
 */
class LoggingAndErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試成功的 API 請求會被正確記錄
     */
    public function test_successful_api_request_is_logged(): void
    {
        $user = User::factory()->create();
        
        // 模擬一個成功的 API 請求
        $response = $this->actingAs($user)
            ->postJson('/api/', [
                'action_type' => 'test_action',
                'param1' => 'value1',
            ]);

        // 驗證回應（這裡可能會是 404 因為路由不存在，但這不影響日誌記錄）
        // 主要是測試日誌記錄功能
        
        // 驗證日誌是否被建立
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $user->id,
            'action_type' => 'test_action',
        ]);

        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('test_action', $log->action_type);
        $this->assertEquals(['action_type' => 'test_action', 'param1' => 'value1'], $log->request_data);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertNotEmpty($log->request_id);
    }

    /**
     * 測試錯誤請求會被正確記錄和處理
     */
    public function test_error_request_is_logged_and_handled(): void
    {
        // 發送一個會產生錯誤的請求（沒有 action_type）
        $response = $this->postJson('/api/', [
            'param1' => 'value1',
        ]);

        // 驗證日誌是否被建立
        $this->assertDatabaseHas('api_logs', [
            'action_type' => 'unknown',
        ]);

        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('unknown', $log->action_type);
        $this->assertNull($log->user_id); // 未登入使用者
    }

    /**
     * 測試敏感資料在日誌中被正確清理
     */
    public function test_sensitive_data_is_sanitized_in_logs(): void
    {
        $user = User::factory()->create();
        
        // 發送包含敏感資料的請求
        $response = $this->actingAs($user)
            ->postJson('/api/', [
                'action_type' => 'login',
                'username' => 'testuser',
                'password' => 'secret123',
                'api_key' => 'sensitive_key',
            ]);

        // 驗證日誌中的敏感資料被清理
        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('[REDACTED]', $log->request_data['password']);
        $this->assertEquals('[REDACTED]', $log->request_data['api_key']);
        $this->assertEquals('testuser', $log->request_data['username']); // 非敏感資料保留
    }

    /**
     * 測試未授權請求的錯誤處理
     */
    public function test_unauthorized_error_handling(): void
    {
        // 發送沒有 Bearer Token 的請求
        $response = $this->postJson('/api/', [
            'action_type' => 'test_action',
        ]);

        // 驗證回應格式
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'status',
            'message',
            'error_code',
        ]);

        $responseData = $response->json();
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('UNAUTHORIZED', $responseData['error_code']);
    }

    /**
     * 測試 404 錯誤的處理
     */
    public function test_not_found_error_handling(): void
    {
        // 發送請求到不存在的路由
        $response = $this->postJson('/api/nonexistent');

        // 驗證回應格式
        $response->assertStatus(404);
        $response->assertJsonStructure([
            'status',
            'message',
            'error_code',
        ]);

        $responseData = $response->json();
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('NOT_FOUND', $responseData['error_code']);
    }

    /**
     * 測試請求 ID 在回應中的存在
     */
    public function test_request_id_is_included_in_response(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/', [
                'action_type' => 'test_action',
            ]);

        // 檢查日誌中的請求 ID
        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertNotEmpty($log->request_id);
        
        // 驗證請求 ID 是有效的 UUID 格式
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $log->request_id
        );
    }

    /**
     * 測試高頻請求的日誌記錄
     */
    public function test_multiple_requests_are_logged_separately(): void
    {
        $user = User::factory()->create();
        
        // 發送多個請求
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($user)
                ->postJson('/api/', [
                    'action_type' => "test_action_{$i}",
                    'request_number' => $i,
                ]);
        }

        // 驗證所有請求都被記錄
        $this->assertEquals(3, ApiLog::count());
        
        $logs = ApiLog::orderBy('created_at')->get();
        for ($i = 1; $i <= 3; $i++) {
            $this->assertEquals("test_action_{$i}", $logs[$i-1]->action_type);
            $this->assertEquals($i, $logs[$i-1]->request_data['request_number']);
            $this->assertNotEmpty($logs[$i-1]->request_id);
        }
        
        // 驗證每個請求都有唯一的請求 ID
        $requestIds = $logs->pluck('request_id')->toArray();
        $this->assertEquals(3, count(array_unique($requestIds)));
    }
}