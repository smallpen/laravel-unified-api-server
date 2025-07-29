<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ActionRegistry;
use App\Actions\System\PingAction;
use App\Actions\User\GetUserInfoAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Action系統功能測試
 * 
 * 測試Action註冊和執行系統的核心功能
 */
class ActionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected ActionRegistry $registry;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registry = app(ActionRegistry::class);
        
        // 建立測試使用者
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * 測試Action註冊系統是否正常運作
     */
    public function test_action_registry_works(): void
    {
        // 檢查預期的Action是否已註冊
        $this->assertTrue($this->registry->hasAction('system.ping'));
        $this->assertTrue($this->registry->hasAction('user.info'));
        
        // 檢查Action數量
        $actions = $this->registry->getAllActions();
        $this->assertGreaterThanOrEqual(2, count($actions));
    }

    /**
     * 測試PingAction執行
     */
    public function test_ping_action_execution(): void
    {
        $action = $this->registry->resolve('system.ping');
        $request = Request::create('/', 'POST', ['action_type' => 'system.ping']);
        
        $result = $action->execute($request, $this->user);
        
        $this->assertIsArray($result);
        $this->assertEquals('pong', $result['message']);
        $this->assertEquals($this->user->id, $result['user_id']);
        $this->assertEquals('healthy', $result['system_status']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('server_time', $result);
    }

    /**
     * 測試GetUserInfoAction執行
     */
    public function test_user_info_action_execution(): void
    {
        $action = $this->registry->resolve('user.info');
        $request = Request::create('/', 'POST', ['action_type' => 'user.info']);
        
        $result = $action->execute($request, $this->user);
        
        $this->assertIsArray($result);
        $this->assertEquals($this->user->id, $result['id']);
        $this->assertEquals($this->user->name, $result['name']);
        $this->assertEquals($this->user->email, $result['email']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
    }

    /**
     * 測試Action驗證功能
     */
    public function test_action_validation(): void
    {
        $action = $this->registry->resolve('system.ping');
        $request = Request::create('/', 'POST', ['action_type' => 'system.ping']);
        
        // PingAction沒有驗證規則，應該直接通過
        $result = $action->validate($request);
        $this->assertTrue($result);
    }

    /**
     * 測試Action文件生成
     */
    public function test_action_documentation(): void
    {
        $pingAction = $this->registry->resolve('system.ping');
        $userInfoAction = $this->registry->resolve('user.info');
        
        $pingDocs = $pingAction->getDocumentation();
        $userInfoDocs = $userInfoAction->getDocumentation();
        
        // 檢查文件結構
        $this->assertArrayHasKey('name', $pingDocs);
        $this->assertArrayHasKey('description', $pingDocs);
        $this->assertArrayHasKey('parameters', $pingDocs);
        $this->assertArrayHasKey('responses', $pingDocs);
        $this->assertArrayHasKey('examples', $pingDocs);
        
        $this->assertArrayHasKey('name', $userInfoDocs);
        $this->assertArrayHasKey('description', $userInfoDocs);
        $this->assertArrayHasKey('required_permissions', $userInfoDocs);
        
        // 檢查權限設定
        $this->assertEmpty($pingAction->getRequiredPermissions());
        $this->assertEquals(['user.read'], $userInfoAction->getRequiredPermissions());
    }

    /**
     * 測試Action屬性
     */
    public function test_action_properties(): void
    {
        $pingAction = $this->registry->resolve('system.ping');
        $userInfoAction = $this->registry->resolve('user.info');
        
        // 檢查Action類型
        $this->assertEquals('system.ping', $pingAction->getActionType());
        $this->assertEquals('user.info', $userInfoAction->getActionType());
        
        // 檢查版本
        $this->assertEquals('1.0.0', $pingAction->getVersion());
        $this->assertEquals('1.0.0', $userInfoAction->getVersion());
        
        // 檢查啟用狀態
        $this->assertTrue($pingAction->isEnabled());
        $this->assertTrue($userInfoAction->isEnabled());
    }

    /**
     * 測試Action快取機制
     */
    public function test_action_caching(): void
    {
        $action1 = $this->registry->resolve('system.ping');
        $action2 = $this->registry->resolve('system.ping');
        
        // 應該回傳相同的實例
        $this->assertSame($action1, $action2);
        
        // 清除快取後應該建立新實例
        $this->registry->clearCache();
        $action3 = $this->registry->resolve('system.ping');
        
        $this->assertNotSame($action1, $action3);
        $this->assertInstanceOf(PingAction::class, $action3);
    }

    /**
     * 測試動態註冊Action
     */
    public function test_dynamic_action_registration(): void
    {
        // 建立測試Action
        $testActionClass = new class extends \App\Actions\BaseAction {
            public function getActionType(): string
            {
                return 'test.dynamic';
            }

            public function execute(\Illuminate\Http\Request $request, \App\Models\User $user): array
            {
                return [
                    'message' => 'dynamic action executed',
                    'user_id' => $user->id,
                    'timestamp' => now()->toISOString(),
                ];
            }
        };

        // 註冊新Action
        $this->registry->register('test.dynamic', get_class($testActionClass));
        
        // 驗證註冊成功
        $this->assertTrue($this->registry->hasAction('test.dynamic'));
        
        // 測試執行
        $action = $this->registry->resolve('test.dynamic');
        $request = Request::create('/', 'POST', ['action_type' => 'test.dynamic']);
        $result = $action->execute($request, $this->user);
        
        $this->assertEquals('dynamic action executed', $result['message']);
        $this->assertEquals($this->user->id, $result['user_id']);
    }

    /**
     * 測試Action統計資訊
     */
    public function test_action_statistics(): void
    {
        $stats = $this->registry->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('enabled_actions', $stats);
        $this->assertArrayHasKey('disabled_actions', $stats);
        $this->assertArrayHasKey('version_distribution', $stats);
        $this->assertArrayHasKey('cached_instances', $stats);
        
        // 至少應該有兩個Action
        $this->assertGreaterThanOrEqual(2, $stats['total_actions']);
        $this->assertGreaterThanOrEqual(2, $stats['enabled_actions']);
        
        // 版本分佈應該包含1.0.0
        $this->assertArrayHasKey('1.0.0', $stats['version_distribution']);
        $this->assertGreaterThanOrEqual(2, $stats['version_distribution']['1.0.0']);
    }
}