<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Api\UnifiedApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Mockery;

/**
 * UnifiedApiController 單元測試
 * 
 * 測試統一API控制器的核心功能
 */
class UnifiedApiControllerTest extends TestCase
{
    protected UnifiedApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new UnifiedApiController();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試處理有效的POST請求
     */
    public function test_handle_valid_post_request(): void
    {
        // 建立模擬請求
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);

        // 模擬已驗證的使用者
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('test.ping', $responseData['data']['action_type']);
        $this->assertEquals(1, $responseData['data']['user_id']);
    }

    /**
     * 測試處理非POST請求應回傳405錯誤
     */
    public function test_handle_non_post_request_returns_405(): void
    {
        // 建立GET請求
        $request = Request::create('/api', 'GET');

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(405, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('METHOD_NOT_ALLOWED', $responseData['error_code']);
        $this->assertStringContainsString('不支援的請求方法', $responseData['message']);
    }

    /**
     * 測試缺少action_type參數應回傳驗證錯誤
     */
    public function test_handle_missing_action_type_returns_validation_error(): void
    {
        // 建立沒有action_type的請求
        $request = Request::create('/api', 'POST', []);

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
        $this->assertArrayHasKey('details', $responseData);
        $this->assertArrayHasKey('action_type', $responseData['details']);
    }

    /**
     * 測試無效的action_type格式應回傳驗證錯誤
     */
    public function test_handle_invalid_action_type_format_returns_validation_error(): void
    {
        // 建立包含無效字元的action_type請求
        $request = Request::create('/api', 'POST', [
            'action_type' => 'invalid@action#type'
        ]);

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
        $this->assertArrayHasKey('details', $responseData);
        $this->assertArrayHasKey('action_type', $responseData['details']);
    }

    /**
     * 測試過長的action_type應回傳驗證錯誤
     */
    public function test_handle_too_long_action_type_returns_validation_error(): void
    {
        // 建立超過100字元的action_type
        $longActionType = str_repeat('a', 101);
        $request = Request::create('/api', 'POST', [
            'action_type' => $longActionType
        ]);

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('VALIDATION_ERROR', $responseData['error_code']);
    }

    /**
     * 測試不存在的Action應回傳404錯誤
     */
    public function test_handle_non_existent_action_returns_404(): void
    {
        // 建立不存在的action_type請求
        $request = Request::create('/api', 'POST', [
            'action_type' => 'non.existent.action'
        ]);

        // 執行控制器方法
        $response = $this->controller->handle($request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('ACTION_NOT_FOUND', $responseData['error_code']);
        $this->assertStringContainsString('找不到指定的Action', $responseData['message']);
    }

    /**
     * 測試routeToAction方法的基本功能
     */
    public function test_route_to_action_with_valid_action(): void
    {
        // 建立請求
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);

        // 模擬使用者
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // 執行routeToAction方法
        $response = $this->controller->routeToAction('test.ping', $request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('test.ping', $responseData['data']['action_type']);
    }

    /**
     * 測試routeToAction方法處理不存在的Action
     */
    public function test_route_to_action_with_invalid_action(): void
    {
        // 建立請求
        $request = Request::create('/api', 'POST');

        // 執行routeToAction方法
        $response = $this->controller->routeToAction('invalid.action', $request);

        // 驗證回應
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('ACTION_NOT_FOUND', $responseData['error_code']);
    }

    /**
     * 測試有效的action_type格式
     */
    public function test_valid_action_type_formats(): void
    {
        $validActionTypes = [
            'test.ping',
            'user.info',
            'user_profile.update',
            'system-status.check',
            'api.v1.user.create',
            'simple',
            'test123',
            'action_with_numbers_123'
        ];

        foreach ($validActionTypes as $actionType) {
            $request = Request::create('/api', 'POST', [
                'action_type' => $actionType
            ]);

            // 如果是允許的測試Action，應該成功
            if (in_array($actionType, ['test.ping', 'user.info'])) {
                $response = $this->controller->handle($request);
                $this->assertEquals(200, $response->getStatusCode(), "Action type '{$actionType}' should be valid");
            } else {
                // 其他Action應該回傳404（因為不在允許清單中）
                $response = $this->controller->handle($request);
                $this->assertEquals(404, $response->getStatusCode(), "Action type '{$actionType}' should return 404");
            }
        }
    }

    /**
     * 測試回應格式包含必要欄位
     */
    public function test_response_format_contains_required_fields(): void
    {
        // 測試成功回應格式
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);

        $response = $this->controller->handle($request);
        $responseData = json_decode($response->getContent(), true);

        // 驗證成功回應必要欄位
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);

        // 測試錯誤回應格式
        $errorRequest = Request::create('/api', 'POST', [
            'action_type' => 'non.existent'
        ]);

        $errorResponse = $this->controller->handle($errorRequest);
        $errorData = json_decode($errorResponse->getContent(), true);

        // 驗證錯誤回應必要欄位
        $this->assertArrayHasKey('status', $errorData);
        $this->assertArrayHasKey('message', $errorData);
        $this->assertArrayHasKey('error_code', $errorData);
        $this->assertArrayHasKey('timestamp', $errorData);
    }

    /**
     * 測試時間戳格式
     */
    public function test_timestamp_format(): void
    {
        $request = Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);

        $response = $this->controller->handle($request);
        $responseData = json_decode($response->getContent(), true);

        // 驗證時間戳格式為ISO 8601
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $responseData['timestamp']
        );
    }
}