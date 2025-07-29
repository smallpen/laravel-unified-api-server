<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Controllers\Api\UnifiedApiController;
use Illuminate\Http\Request;

/**
 * 簡單的統一API測試
 */
class SimpleUnifiedApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試控制器可以正常實例化
     */
    public function test_controller_can_be_instantiated(): void
    {
        $controller = new UnifiedApiController();
        $this->assertInstanceOf(UnifiedApiController::class, $controller);
    }

    /**
     * 測試POST請求基本處理
     */
    public function test_post_request_basic_handling(): void
    {
        $controller = new UnifiedApiController();
        $request = Request::create('/api', 'POST', ['action_type' => 'test.ping']);
        
        $response = $controller->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}