<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

/**
 * 統一API基本功能測試（不包含Bearer Token驗證）
 */
class UnifiedApiBasicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試路由是否正確設定
     */
    public function test_api_route_exists(): void
    {
        // 測試路由存在但會因為缺少Bearer Token而被拒絕
        $response = $this->postJson('/api', [
            'action_type' => 'test.ping'
        ]);

        // 應該回傳401（未授權）而不是404（找不到路由）
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    /**
     * 測試非POST請求被Laravel路由系統拒絕
     */
    public function test_non_post_methods_rejected_by_route(): void
    {
        $response = $this->getJson('/api');
        
        // Laravel路由系統會回傳405 Method Not Allowed
        $response->assertStatus(405);
    }

    /**
     * 測試控制器邏輯（繞過中介軟體）
     */
    public function test_controller_logic_without_middleware(): void
    {
        $user = User::factory()->create();
        
        // 直接測試控制器，不透過HTTP請求
        $controller = new \App\Http\Controllers\Api\UnifiedApiController();
        $request = \Illuminate\Http\Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);
        
        // 手動設定使用者
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $response = $controller->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('test.ping', $data['data']['action_type']);
        $this->assertEquals($user->id, $data['data']['user_id']);
    }

    /**
     * 測試action_type驗證邏輯
     */
    public function test_action_type_validation_logic(): void
    {
        $controller = new \App\Http\Controllers\Api\UnifiedApiController();
        
        // 測試缺少action_type
        $request = \Illuminate\Http\Request::create('/api', 'POST', []);
        $response = $controller->handle($request);
        $this->assertEquals(422, $response->getStatusCode());
        
        // 測試無效的action_type格式
        $request = \Illuminate\Http\Request::create('/api', 'POST', [
            'action_type' => 'invalid@action'
        ]);
        $response = $controller->handle($request);
        $this->assertEquals(422, $response->getStatusCode());
        
        // 測試不存在的action
        $request = \Illuminate\Http\Request::create('/api', 'POST', [
            'action_type' => 'non.existent.action'
        ]);
        $response = $controller->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * 測試非POST請求處理邏輯
     */
    public function test_non_post_request_handling_logic(): void
    {
        $controller = new \App\Http\Controllers\Api\UnifiedApiController();
        
        $request = \Illuminate\Http\Request::create('/api', 'GET', [
            'action_type' => 'test.ping'
        ]);
        
        $response = $controller->handle($request);
        
        $this->assertEquals(405, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('METHOD_NOT_ALLOWED', $data['error_code']);
    }

    /**
     * 測試回應格式標準化
     */
    public function test_response_format_standardization(): void
    {
        $user = User::factory()->create();
        $controller = new \App\Http\Controllers\Api\UnifiedApiController();
        
        // 測試成功回應格式
        $request = \Illuminate\Http\Request::create('/api', 'POST', [
            'action_type' => 'test.ping'
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $response = $controller->handle($request);
        $data = json_decode($response->getContent(), true);
        
        // 檢查成功回應必要欄位
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals('success', $data['status']);
        
        // 測試錯誤回應格式
        $request = \Illuminate\Http\Request::create('/api', 'POST', [
            'action_type' => 'invalid.action'
        ]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $response = $controller->handle($request);
        $data = json_decode($response->getContent(), true);
        
        // 檢查錯誤回應必要欄位
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertEquals('error', $data['status']);
    }
}