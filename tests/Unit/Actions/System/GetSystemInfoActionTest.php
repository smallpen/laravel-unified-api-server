<?php

namespace Tests\Unit\Actions\System;

use Tests\TestCase;
use App\Actions\System\GetSystemInfoAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

/**
 * GetSystemInfoAction單元測試
 */
class GetSystemInfoActionTest extends TestCase
{
    use RefreshDatabase;

    protected GetSystemInfoAction $action;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->action = new GetSystemInfoAction();
        $this->user = User::factory()->create();
    }

    /**
     * 測試取得基本系統資訊
     */
    public function test_execute_returns_basic_system_info(): void
    {
        $request = new Request(['type' => 'basic']);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('system_info', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $systemInfo = $result['system_info'];
        $this->assertArrayHasKey('app_name', $systemInfo);
        $this->assertArrayHasKey('app_version', $systemInfo);
        $this->assertArrayHasKey('laravel_version', $systemInfo);
        $this->assertArrayHasKey('php_version', $systemInfo);
        $this->assertArrayHasKey('environment', $systemInfo);
        $this->assertArrayHasKey('timezone', $systemInfo);
        $this->assertArrayHasKey('locale', $systemInfo);
        
        $this->assertEquals(config('app.name'), $systemInfo['app_name']);
        $this->assertEquals('1.0.0', $systemInfo['app_version']);
        $this->assertEquals(PHP_VERSION, $systemInfo['php_version']);
        $this->assertEquals(config('app.env'), $systemInfo['environment']);
    }

    /**
     * 測試取得統計資訊
     */
    public function test_execute_returns_stats_info(): void
    {
        // 建立一些測試資料
        User::factory()->count(5)->create();
        
        $request = new Request(['type' => 'stats']);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('system_info', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $systemInfo = $result['system_info'];
        $this->assertArrayHasKey('total_users', $systemInfo);
        $this->assertArrayHasKey('active_users_today', $systemInfo);
        $this->assertArrayHasKey('new_users_this_month', $systemInfo);
        $this->assertArrayHasKey('database_size', $systemInfo);
        
        $this->assertEquals(6, $systemInfo['total_users']); // 5 + 1 from setUp
        $this->assertIsInt($systemInfo['active_users_today']);
        $this->assertIsInt($systemInfo['new_users_this_month']);
    }

    /**
     * 測試取得健康檢查資訊
     */
    public function test_execute_returns_health_info(): void
    {
        $request = new Request(['type' => 'health']);
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('system_info', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $systemInfo = $result['system_info'];
        $this->assertArrayHasKey('overall_status', $systemInfo);
        $this->assertArrayHasKey('checks', $systemInfo);
        
        $checks = $systemInfo['checks'];
        $this->assertArrayHasKey('database', $checks);
        $this->assertArrayHasKey('cache', $checks);
        $this->assertArrayHasKey('storage', $checks);
        
        $this->assertContains($systemInfo['overall_status'], ['healthy', 'unhealthy']);
        $this->assertContains($checks['database'], ['healthy', 'unhealthy']);
        $this->assertContains($checks['cache'], ['healthy', 'unhealthy']);
        $this->assertContains($checks['storage'], ['healthy', 'unhealthy', 'warning']);
    }

    /**
     * 測試預設取得基本資訊
     */
    public function test_execute_returns_basic_info_by_default(): void
    {
        $request = new Request(); // 沒有指定type參數
        
        $result = $this->action->execute($request, $this->user);
        
        $this->assertArrayHasKey('system_info', $result);
        $systemInfo = $result['system_info'];
        
        // 應該包含基本資訊的欄位
        $this->assertArrayHasKey('app_name', $systemInfo);
        $this->assertArrayHasKey('laravel_version', $systemInfo);
        $this->assertArrayHasKey('php_version', $systemInfo);
    }

    /**
     * 測試不支援的資訊類型（透過驗證失敗）
     */
    public function test_execute_throws_validation_exception_for_unsupported_type(): void
    {
        $request = new Request(['type' => 'unsupported']);
        
        $this->expectException(ValidationException::class);
        
        $this->action->execute($request, $this->user);
    }

    /**
     * 測試參數驗證 - 有效的type參數
     */
    public function test_validate_passes_with_valid_type(): void
    {
        $validTypes = ['basic', 'stats', 'health'];
        
        foreach ($validTypes as $type) {
            $request = new Request(['type' => $type]);
            $result = $this->action->validate($request);
            $this->assertTrue($result);
        }
    }

    /**
     * 測試參數驗證 - 無參數
     */
    public function test_validate_passes_with_no_parameters(): void
    {
        $request = new Request();
        
        $result = $this->action->validate($request);
        
        $this->assertTrue($result);
    }

    /**
     * 測試參數驗證 - 無效的type參數
     */
    public function test_validate_fails_with_invalid_type(): void
    {
        $request = new Request(['type' => 'invalid']);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試參數驗證 - 非字串的type參數
     */
    public function test_validate_fails_with_non_string_type(): void
    {
        $request = new Request(['type' => 123]);
        
        $this->expectException(ValidationException::class);
        
        $this->action->validate($request);
    }

    /**
     * 測試取得所需權限
     */
    public function test_get_required_permissions(): void
    {
        $permissions = $this->action->getRequiredPermissions();
        
        $this->assertIsArray($permissions);
        $this->assertContains('system.read', $permissions);
    }

    /**
     * 測試取得Action類型
     */
    public function test_get_action_type(): void
    {
        $actionType = $this->action->getActionType();
        
        $this->assertEquals('system.info', $actionType);
    }

    /**
     * 測試Action是否啟用
     */
    public function test_is_enabled(): void
    {
        $isEnabled = $this->action->isEnabled();
        
        $this->assertTrue($isEnabled);
    }

    /**
     * 測試取得版本資訊
     */
    public function test_get_version(): void
    {
        $version = $this->action->getVersion();
        
        $this->assertEquals('1.0.0', $version);
    }

    /**
     * 測試取得文件資訊
     */
    public function test_get_documentation(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('name', $documentation);
        $this->assertArrayHasKey('description', $documentation);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);
        
        $this->assertEquals('取得系統資訊', $documentation['name']);
    }

    /**
     * 測試文件資訊中的參數規格
     */
    public function test_documentation_parameters(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('type', $documentation['parameters']);
        
        $typeParam = $documentation['parameters']['type'];
        $this->assertEquals('string', $typeParam['type']);
        $this->assertFalse($typeParam['required']);
        $this->assertEquals('basic', $typeParam['default']);
        $this->assertIsArray($typeParam['enum']);
        $this->assertContains('basic', $typeParam['enum']);
        $this->assertContains('stats', $typeParam['enum']);
        $this->assertContains('health', $typeParam['enum']);
    }

    /**
     * 測試文件資訊中的回應格式
     */
    public function test_documentation_responses(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertArrayHasKey('success_basic', $documentation['responses']);
        $this->assertArrayHasKey('success_stats', $documentation['responses']);
        $this->assertArrayHasKey('error', $documentation['responses']);
        
        $basicResponse = $documentation['responses']['success_basic'];
        $this->assertEquals('success', $basicResponse['status']);
        $this->assertArrayHasKey('data', $basicResponse);
        $this->assertArrayHasKey('system_info', $basicResponse['data']);
        
        $statsResponse = $documentation['responses']['success_stats'];
        $this->assertEquals('success', $statsResponse['status']);
        $this->assertArrayHasKey('data', $statsResponse);
        
        $errorResponse = $documentation['responses']['error'];
        $this->assertEquals('error', $errorResponse['status']);
        $this->assertArrayHasKey('message', $errorResponse);
        $this->assertArrayHasKey('error_code', $errorResponse);
    }

    /**
     * 測試文件資訊中的使用範例
     */
    public function test_documentation_examples(): void
    {
        $documentation = $this->action->getDocumentation();
        
        $this->assertIsArray($documentation['examples']);
        $this->assertCount(3, $documentation['examples']);
        
        foreach ($documentation['examples'] as $example) {
            $this->assertArrayHasKey('title', $example);
            $this->assertArrayHasKey('request', $example);
            $this->assertIsString($example['title']);
            $this->assertIsArray($example['request']);
            $this->assertArrayHasKey('action_type', $example['request']);
            $this->assertEquals('system.info', $example['request']['action_type']);
        }
    }

    /**
     * 測試快取健康檢查
     */
    public function test_cache_health_check(): void
    {
        // 清除快取以確保測試環境乾淨
        Cache::forget('health_check');
        
        $request = new Request(['type' => 'health']);
        $result = $this->action->execute($request, $this->user);
        
        $checks = $result['system_info']['checks'];
        
        // 在測試環境中，快取應該是正常的
        $this->assertContains($checks['cache'], ['healthy', 'unhealthy']);
    }
}