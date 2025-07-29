<?php

namespace Tests\Unit\Http\Controllers\Api;

use Tests\TestCase;
use App\Http\Controllers\Api\UnifiedApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedApiController 單元測試
 */
class UnifiedApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected UnifiedApiController $controller;
    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new UnifiedApiController();
        
        // 建立測試使用者
        $this->testUser = User::factory()->create([
            'name' => '測試使用者',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 測試成功處理POST請求
     */
    public function test_handle_successful_post_request(): void
    {
        // 建立模擬請求
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping',
            'data' => ['message' => 'hello'],
        ]);

        // 設定使用者
        $request->setUserResolver(function () {
            return $this->testUser;
        });

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('test.ping', $responseData['data']['action_type']);
        $this->assertEquals($this->testUser->id, $responseData['data']['user_id']);
    }

    /**
     * 測試非POST請求回傳405錯誤
     */
    public function test_handle_non_post_request_returns_405(): void
    {
        // 建立GET請求
        $request = Request::create('/api', 'GET', [
            'action_type' => 'test.ping',
        ]);

        $response = $this->controller->handle($request);

        $this->assertEquals(405, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('METHOD_NOT_ALLOWED', $responseData['error_code']);
        $this->assertStringContainsString('不支援的請求方法', $responseData['message']);
    }

    /**
     * 測試缺少action_type參數回傳422錯誤
     */
    public function test_handle_missing_action_type_returns_422(): void
    {
        $request = Request::create('/api', 'POST', [
            'data' => ['message' => 'hello'],
        ]);

        $response = $this->controller->handle($request);

        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
        $this->assertArrayHasKey('action_type', $responseData['details']);
    }

    /**
     * 測試action_type格式驗證
     */
    public function test_handle_invalid_action_type_format_returns_422(): void
    {
        $invalidActionTypes = [
            '', // 空字串
            'invalid action type', // 包含空格
            'action@type', // 包含特殊字元
            str_repeat('a', 101), // 超過100字元
        ];

        foreach ($invalidActionTypes as $invalidActionType) {
            $request = Request::create('/api', 'POST', [
                'action_type' => $invalidActionType,
            ]);

            $response = $this->controller->handle($request);

            $this->assertEquals(422, $response->getStatusCode(), 
                "Action type '{$invalidActionType}' should return 422");
            
            $responseData = json_decode($response->getContent(), true);
            $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
        }
    }

    /**
     * 測試有效的action_type格式
     */
    public function test_handle_valid_action_type_formats(): void
    {
        $validActionTypes = [
            'test.ping',
            'user_info',
            'system-status',
            'action.sub_action.detail',
            'simple',
        ];

        foreach ($validActionTypes as $validActionType) {
            $request = Request::create('/api', 'POST', [
                'action_type' => $validActionType,
            ]);

            $request->setUserResolver(function () {
                return $this->testUser;
            });

            $response = $this->controller->handle($request);

            // 如果Action存在，應該回傳200；如果不存在，應該回傳404
            $this->assertContains($response->getStatusCode(), [200, 404], 
                "Action type '{$validActionType}' should return 200 or 404");
        }
    }

    /**
     * 測試不存在的Action回傳404錯誤
     */
    public function test_route_to_action_non_existent_action_returns_404(): void
    {
        $request = Request::create('/api', 'POST', [
            'action_type' => 'non.existent.action',
        ]);

        $request->setUserResolver(function () {
            return $this->testUser;
        });

        $response = $this->controller->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('ACTION_NOT_FOUND', $responseData['error_code']);
        $this->assertStringContainsString('找不到指定的Action', $responseData['message']);
    }

    /**
     * 測試routeToAction方法
     */
    public function test_route_to_action_method(): void
    {
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping',
            'data' => ['test' => 'value'],
        ]);

        $request->setUserResolver(function () {
            return $this->testUser;
        });

        $response = $this->controller->routeToAction('test.ping', $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('test.ping', $responseData['data']['action_type']);
    }

    /**
     * 測試系統異常處理
     */
    public function test_handle_system_exception_returns_500(): void
    {
        // 模擬Log facade
        Log::shouldReceive('error')->once();

        // 建立一個會拋出異常的請求（透過模擬）
        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('isMethod')
            ->with('POST')
            ->willThrowException(new \Exception('測試異常'));

        $response = $this->controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('INTERNAL_SERVER_ERROR', $responseData['error_code']);
        $this->assertEquals('系統內部錯誤', $responseData['message']);
    }

    /**
     * 測試成功回應格式
     */
    public function test_success_response_format(): void
    {
        $testData = ['key' => 'value'];
        $testMessage = '測試成功';

        $response = $this->invokeMethod($this->controller, 'successResponse', [
            $testData, $testMessage, 201
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals($testMessage, $responseData['message']);
        $this->assertEquals($testData, $responseData['data']);
        $this->assertArrayHasKey('timestamp', $responseData);
    }

    /**
     * 測試錯誤回應格式
     */
    public function test_error_response_format(): void
    {
        $testMessage = '測試錯誤';
        $testErrorCode = 'TEST_ERROR';
        $testDetails = ['field' => 'error detail'];

        $response = $this->invokeMethod($this->controller, 'errorResponse', [
            $testMessage, $testErrorCode, 400, $testDetails
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals($testMessage, $responseData['message']);
        $this->assertEquals($testErrorCode, $responseData['error_code']);
        $this->assertEquals($testDetails, $responseData['details']);
        $this->assertArrayHasKey('timestamp', $responseData);
    }

    /**
     * 測試Action存在性檢查
     */
    public function test_is_action_exists(): void
    {
        // 測試存在的Action
        $existingActions = [
            'test.ping',
            'user.info',
            'user.update',
            'system.status',
        ];

        foreach ($existingActions as $action) {
            $result = $this->invokeMethod($this->controller, 'isActionExists', [$action]);
            $this->assertTrue($result, "Action '{$action}' should exist");
        }

        // 測試不存在的Action
        $nonExistingActions = [
            'non.existent',
            'invalid.action',
            'test.unknown',
        ];

        foreach ($nonExistingActions as $action) {
            $result = $this->invokeMethod($this->controller, 'isActionExists', [$action]);
            $this->assertFalse($result, "Action '{$action}' should not exist");
        }
    }

    /**
     * 輔助方法：呼叫私有或受保護的方法
     */
    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}