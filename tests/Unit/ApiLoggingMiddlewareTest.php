<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiLoggingMiddleware;
use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * API 日誌中介軟體單元測試
 */
class ApiLoggingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ApiLoggingMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiLoggingMiddleware();
    }

    /**
     * 測試中介軟體記錄成功請求
     */
    public function test_logs_successful_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/api/', 'POST', [
            'action_type' => 'test_action',
            'param1' => 'value1',
        ]);
        $request->headers->set('User-Agent', 'Test Agent');

        $response = new JsonResponse([
            'status' => 'success',
            'data' => ['result' => 'test'],
        ], 200);
        $response->headers->set('Content-Type', 'application/json');

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // 驗證日誌是否被建立
        $this->assertDatabaseHas('api_logs', [
            'user_id' => $user->id,
            'action_type' => 'test_action',
            'status_code' => 200,
            'user_agent' => 'Test Agent',
        ]);

        $log = ApiLog::first();
        $this->assertNotNull($log);
        $this->assertEquals(['action_type' => 'test_action', 'param1' => 'value1'], $log->request_data);
        $this->assertEquals(['status' => 'success', 'data' => ['result' => 'test']], $log->response_data);
        $this->assertGreaterThan(0, $log->response_time);
        $this->assertNotEmpty($log->request_id);
    }

    /**
     * 測試中介軟體記錄錯誤請求
     */
    public function test_logs_error_request(): void
    {
        $request = Request::create('/api/', 'POST', [
            'action_type' => 'invalid_action',
        ]);

        $response = new JsonResponse([
            'status' => 'error',
            'message' => '動作不存在',
            'error_code' => 'ACTION_NOT_FOUND',
        ], 404);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // 驗證錯誤日誌是否被建立
        $this->assertDatabaseHas('api_logs', [
            'user_id' => null, // 未登入使用者
            'action_type' => 'invalid_action',
            'status_code' => 404,
        ]);

        $log = ApiLog::first();
        $this->assertEquals([
            'status' => 'error',
            'message' => '動作不存在',
            'error_code' => 'ACTION_NOT_FOUND',
        ], $log->response_data);
    }

    /**
     * 測試敏感資料被正確清理
     */
    public function test_sanitizes_sensitive_data(): void
    {
        $request = Request::create('/api/', 'POST', [
            'action_type' => 'login',
            'username' => 'testuser',
            'password' => 'secret123',
            'api_key' => 'sensitive_key',
        ]);

        $response = new JsonResponse([
            'status' => 'success',
            'data' => [
                'user_id' => 1,
                'access_token' => 'token123',
                'profile' => ['name' => 'Test User'],
            ],
        ], 200);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $log = ApiLog::first();
        
        // 驗證請求中的敏感資料被清理
        $this->assertEquals('[REDACTED]', $log->request_data['password']);
        $this->assertEquals('[REDACTED]', $log->request_data['api_key']);
        $this->assertEquals('testuser', $log->request_data['username']); // 非敏感資料保留
        
        // 驗證回應中的敏感資料被清理
        $this->assertEquals('[REDACTED]', $log->response_data['data']['access_token']);
        $this->assertEquals(['name' => 'Test User'], $log->response_data['data']['profile']); // 非敏感資料保留
    }

    /**
     * 測試未知 action_type 的處理
     */
    public function test_handles_missing_action_type(): void
    {
        $request = Request::create('/api/', 'POST', [
            'param1' => 'value1',
        ]);

        $response = new JsonResponse([
            'status' => 'error',
            'message' => '缺少 action_type 參數',
        ], 400);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertDatabaseHas('api_logs', [
            'action_type' => 'unknown',
            'status_code' => 400,
        ]);
    }

    /**
     * 測試非 JSON 回應的處理
     */
    public function test_handles_non_json_response(): void
    {
        $request = Request::create('/api/', 'POST', [
            'action_type' => 'download_file',
        ]);

        $response = new \Illuminate\Http\Response('Binary file content', 200, [
            'Content-Type' => 'application/octet-stream',
        ]);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $log = ApiLog::first();
        $this->assertEquals([
            'type' => 'non-json',
            'size' => strlen('Binary file content'),
        ], $log->response_data);
    }

    /**
     * 測試大型資料的處理
     */
    public function test_handles_large_data(): void
    {
        $largeData = str_repeat('x', 70000); // 超過 65535 字元限制
        
        $request = Request::create('/api/', 'POST', [
            'action_type' => 'upload_large_data',
            'large_field' => $largeData,
        ]);

        $response = new JsonResponse([
            'status' => 'success',
            'large_response' => $largeData,
        ], 200);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $log = ApiLog::first();
        $this->assertEquals(['error' => '請求資料過大，已省略'], $log->request_data);
        $this->assertEquals(['error' => '回應資料過大，已省略'], $log->response_data);
    }

    /**
     * 測試 IP 位址的正確取得
     */
    public function test_gets_correct_ip_address(): void
    {
        $request = Request::create('/api/', 'POST', [
            'action_type' => 'test_ip',
        ]);
        
        // 模擬透過代理的請求
        $request->server->set('HTTP_X_FORWARDED_FOR', '203.0.113.1, 192.168.1.1');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $response = new JsonResponse(['status' => 'success'], 200);

        // 執行中介軟體
        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $log = ApiLog::first();
        $this->assertEquals('203.0.113.1', $log->ip_address); // 應該取得真實的公網 IP
    }

    /**
     * 測試請求 ID 的生成
     */
    public function test_generates_unique_request_id(): void
    {
        $request1 = Request::create('/api/', 'POST', ['action_type' => 'test1']);
        $request2 = Request::create('/api/', 'POST', ['action_type' => 'test2']);
        
        $response = new JsonResponse(['status' => 'success'], 200);

        // 執行兩次中介軟體
        $this->middleware->handle($request1, function () use ($response) {
            return $response;
        });
        
        $this->middleware->handle($request2, function () use ($response) {
            return $response;
        });

        $logs = ApiLog::all();
        $this->assertCount(2, $logs);
        $this->assertNotEquals($logs[0]->request_id, $logs[1]->request_id);
        $this->assertNotEmpty($logs[0]->request_id);
        $this->assertNotEmpty($logs[1]->request_id);
    }
}
