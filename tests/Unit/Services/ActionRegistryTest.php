<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ActionRegistry;
use App\Contracts\ActionInterface;
use App\Actions\System\PingAction;
use App\Actions\User\GetUserInfoAction;
use Illuminate\Http\Request;
use App\Models\User;
use InvalidArgumentException;

/**
 * ActionRegistry單元測試
 */
class ActionRegistryTest extends TestCase
{
    protected ActionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ActionRegistry();
    }

    /**
     * 測試註冊有效的Action類別
     */
    public function test_register_valid_action(): void
    {
        $actionType = 'test.ping';
        $actionClass = PingAction::class;

        $this->registry->register($actionType, $actionClass);

        $this->assertTrue($this->registry->hasAction($actionType));
        $this->assertEquals($actionClass, $this->registry->getAllActions()[$actionType]);
    }

    /**
     * 測試註冊不存在的類別應該拋出例外
     */
    public function test_register_non_existent_class_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action類別不存在');

        $this->registry->register('test.invalid', 'NonExistentClass');
    }

    /**
     * 測試註冊未實作ActionInterface的類別應該拋出例外
     */
    public function test_register_invalid_interface_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action類別必須實作ActionInterface');

        $this->registry->register('test.invalid', static::class);
    }

    /**
     * 測試解析已註冊的Action
     */
    public function test_resolve_registered_action(): void
    {
        $actionType = 'system.ping';
        $actionClass = PingAction::class;

        $this->registry->register($actionType, $actionClass);
        $action = $this->registry->resolve($actionType);

        $this->assertInstanceOf(ActionInterface::class, $action);
        $this->assertInstanceOf($actionClass, $action);
        $this->assertEquals($actionType, $action->getActionType());
    }

    /**
     * 測試解析未註冊的Action應該拋出例外
     */
    public function test_resolve_unregistered_action_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('找不到指定的Action');

        $this->registry->resolve('non.existent');
    }

    /**
     * 測試Action實例快取機制
     */
    public function test_action_instance_caching(): void
    {
        $actionType = 'system.ping';
        $actionClass = PingAction::class;

        $this->registry->register($actionType, $actionClass);

        $instance1 = $this->registry->resolve($actionType);
        $instance2 = $this->registry->resolve($actionType);

        // 應該回傳相同的實例（快取機制）
        $this->assertSame($instance1, $instance2);
    }

    /**
     * 測試取得所有已註冊的Action
     */
    public function test_get_all_actions(): void
    {
        $actions = [
            'system.ping' => PingAction::class,
            'user.info' => GetUserInfoAction::class,
        ];

        foreach ($actions as $actionType => $actionClass) {
            $this->registry->register($actionType, $actionClass);
        }

        $allActions = $this->registry->getAllActions();

        $this->assertEquals($actions, $allActions);
        $this->assertCount(2, $allActions);
    }

    /**
     * 測試檢查Action是否存在
     */
    public function test_has_action(): void
    {
        $actionType = 'system.ping';
        $actionClass = PingAction::class;

        $this->assertFalse($this->registry->hasAction($actionType));

        $this->registry->register($actionType, $actionClass);

        $this->assertTrue($this->registry->hasAction($actionType));
    }

    /**
     * 測試移除已註冊的Action
     */
    public function test_unregister_action(): void
    {
        $actionType = 'system.ping';
        $actionClass = PingAction::class;

        $this->registry->register($actionType, $actionClass);
        $this->assertTrue($this->registry->hasAction($actionType));

        $this->registry->unregister($actionType);
        $this->assertFalse($this->registry->hasAction($actionType));
    }

    /**
     * 測試清除快取
     */
    public function test_clear_cache(): void
    {
        $actionType = 'system.ping';
        $actionClass = PingAction::class;

        $this->registry->register($actionType, $actionClass);
        $instance1 = $this->registry->resolve($actionType);

        $this->registry->clearCache();
        $instance2 = $this->registry->resolve($actionType);

        // 清除快取後應該建立新的實例
        $this->assertNotSame($instance1, $instance2);
        $this->assertInstanceOf($actionClass, $instance2);
    }

    /**
     * 測試取得統計資訊
     */
    public function test_get_statistics(): void
    {
        // 建立新的registry實例以避免自動發現的影響
        $registry = new ActionRegistry();
        
        $registry->register('system.ping', PingAction::class);
        $registry->register('user.info', GetUserInfoAction::class);

        $stats = $registry->getStatistics();

        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('enabled_actions', $stats);
        $this->assertArrayHasKey('disabled_actions', $stats);
        $this->assertArrayHasKey('version_distribution', $stats);
        $this->assertArrayHasKey('cached_instances', $stats);

        $this->assertEquals(2, $stats['total_actions']);
        $this->assertEquals(2, $stats['cached_instances']); // 統計方法會解析所有Action來檢查狀態
    }

    /**
     * 測試設定和取得掃描目錄
     */
    public function test_scan_directories_management(): void
    {
        $directories = ['app/CustomActions', 'app/PluginActions'];

        $this->registry->setScanDirectories($directories);
        $this->assertEquals($directories, $this->registry->getScanDirectories());
    }

    /**
     * 測試自動發現功能（模擬）
     * 
     * 由於自動發現需要實際的檔案系統，這裡只測試方法是否可以正常呼叫
     */
    public function test_auto_discover_actions(): void
    {
        // 設定一個不存在的目錄，確保不會發現任何Action
        $this->registry->setScanDirectories(['non/existent/directory']);

        // 這個方法應該能正常執行而不拋出例外
        $this->registry->autoDiscoverActions();

        // 由於目錄不存在，應該沒有發現任何Action
        $this->assertEmpty($this->registry->getAllActions());
    }
}