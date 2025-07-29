<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ActionRegistry;
use App\Actions\System\PingAction;
use App\Actions\User\GetUserInfoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ActionRegistry整合測試
 */
class ActionRegistryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected ActionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ActionRegistry::class);
    }

    /**
     * 測試ActionRegistry服務是否正確註冊
     */
    public function test_action_registry_service_is_registered(): void
    {
        $registry = app(ActionRegistry::class);

        $this->assertInstanceOf(ActionRegistry::class, $registry);
    }

    /**
     * 測試ActionRegistry別名是否正確設定
     */
    public function test_action_registry_alias_is_registered(): void
    {
        $registry = app('action.registry');

        $this->assertInstanceOf(ActionRegistry::class, $registry);
    }

    /**
     * 測試ActionRegistry是否為單例服務
     */
    public function test_action_registry_is_singleton(): void
    {
        $registry1 = app(ActionRegistry::class);
        $registry2 = app(ActionRegistry::class);

        $this->assertSame($registry1, $registry2);
    }

    /**
     * 測試自動發現是否在應用程式啟動時執行
     */
    public function test_auto_discovery_runs_on_boot(): void
    {
        // 由於自動發現在服務提供者的boot方法中執行
        // 我們檢查是否已經發現了預期的Action
        $this->assertTrue($this->registry->hasAction('system.ping'));
        $this->assertTrue($this->registry->hasAction('user.info'));
    }

    /**
     * 測試已發現的Action是否可以正確解析
     */
    public function test_discovered_actions_can_be_resolved(): void
    {
        $pingAction = $this->registry->resolve('system.ping');
        $userInfoAction = $this->registry->resolve('user.info');

        $this->assertInstanceOf(PingAction::class, $pingAction);
        $this->assertInstanceOf(GetUserInfoAction::class, $userInfoAction);
    }

    /**
     * 測試Action的基本屬性
     */
    public function test_action_basic_properties(): void
    {
        $pingAction = $this->registry->resolve('system.ping');

        $this->assertEquals('system.ping', $pingAction->getActionType());
        $this->assertEquals('1.0.0', $pingAction->getVersion());
        $this->assertTrue($pingAction->isEnabled());
    }

    /**
     * 測試Action文件生成
     */
    public function test_action_documentation_generation(): void
    {
        $pingAction = $this->registry->resolve('system.ping');
        $documentation = $pingAction->getDocumentation();

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('name', $documentation);
        $this->assertArrayHasKey('description', $documentation);
        $this->assertArrayHasKey('parameters', $documentation);
        $this->assertArrayHasKey('responses', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        $this->assertEquals('系統Ping測試', $documentation['name']);
        $this->assertStringContainsString('測試API系統', $documentation['description']);
    }

    /**
     * 測試Action權限設定
     */
    public function test_action_permissions(): void
    {
        $pingAction = $this->registry->resolve('system.ping');
        $userInfoAction = $this->registry->resolve('user.info');

        // PingAction不需要特殊權限
        $this->assertEmpty($pingAction->getRequiredPermissions());

        // GetUserInfoAction需要user.read權限
        $this->assertEquals(['user.read'], $userInfoAction->getRequiredPermissions());
    }

    /**
     * 測試註冊系統統計資訊
     */
    public function test_registry_statistics(): void
    {
        $stats = $this->registry->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('enabled_actions', $stats);
        $this->assertArrayHasKey('disabled_actions', $stats);
        $this->assertArrayHasKey('version_distribution', $stats);

        // 至少應該有兩個Action（ping和user.info）
        $this->assertGreaterThanOrEqual(2, $stats['total_actions']);
        $this->assertGreaterThanOrEqual(2, $stats['enabled_actions']);
    }

    /**
     * 測試動態註冊新的Action
     */
    public function test_dynamic_action_registration(): void
    {
        // 建立測試用的Action類別
        $testActionClass = new class extends \App\Actions\BaseAction {
            public function getActionType(): string
            {
                return 'test.dynamic';
            }

            public function execute(\Illuminate\Http\Request $request, \App\Models\User $user): array
            {
                return ['message' => 'dynamic action executed'];
            }
        };

        // 註冊新的Action
        $this->registry->register('test.dynamic', get_class($testActionClass));

        // 驗證Action已註冊
        $this->assertTrue($this->registry->hasAction('test.dynamic'));

        // 驗證可以解析Action
        $resolvedAction = $this->registry->resolve('test.dynamic');
        $this->assertEquals('test.dynamic', $resolvedAction->getActionType());
    }

    /**
     * 測試Action快取機制
     */
    public function test_action_caching_mechanism(): void
    {
        // 第一次解析
        $action1 = $this->registry->resolve('system.ping');
        
        // 第二次解析應該回傳相同的實例
        $action2 = $this->registry->resolve('system.ping');
        
        $this->assertSame($action1, $action2);

        // 清除快取後應該建立新的實例
        $this->registry->clearCache();
        $action3 = $this->registry->resolve('system.ping');
        
        $this->assertNotSame($action1, $action3);
        $this->assertInstanceOf(PingAction::class, $action3);
    }
}