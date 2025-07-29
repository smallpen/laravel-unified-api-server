<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PermissionChecker;
use App\Models\User;
use App\Models\ActionPermission;
use App\Contracts\ActionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * 權限檢查服務單元測試
 */
class PermissionCheckerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 權限檢查器實例
     * 
     * @var PermissionChecker
     */
    protected PermissionChecker $permissionChecker;

    /**
     * 設定測試環境
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionChecker = new PermissionChecker();
    }

    /**
     * 測試使用者具有權限時可以執行Action
     */
    public function test_can_execute_action_when_user_has_permissions(): void
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'permissions' => ['user.read', 'user.update'],
        ]);

        // 建立模擬Action
        $action = Mockery::mock(ActionInterface::class);
        $action->shouldReceive('getActionType')->andReturn('user.info');
        $action->shouldReceive('isEnabled')->andReturn(true);
        $action->shouldReceive('getRequiredPermissions')->andReturn(['user.read']);

        // 執行權限檢查
        $result = $this->permissionChecker->canExecuteAction($user, $action);

        // 驗證結果
        $this->assertTrue($result);
    }

    /**
     * 測試使用者缺少權限時無法執行Action
     */
    public function test_cannot_execute_action_when_user_lacks_permissions(): void
    {
        // 建立測試使用者（沒有所需權限）
        $user = User::factory()->create([
            'permissions' => ['user.update'],
        ]);

        // 建立模擬Action
        $action = Mockery::mock(ActionInterface::class);
        $action->shouldReceive('getActionType')->andReturn('user.info');
        $action->shouldReceive('isEnabled')->andReturn(true);
        $action->shouldReceive('getRequiredPermissions')->andReturn(['user.read']);

        // 執行權限檢查
        $result = $this->permissionChecker->canExecuteAction($user, $action);

        // 驗證結果
        $this->assertFalse($result);
    }

    /**
     * 測試Action停用時無法執行
     */
    public function test_cannot_execute_disabled_action(): void
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'permissions' => ['user.read'],
        ]);

        // 建立模擬Action（已停用）
        $action = Mockery::mock(ActionInterface::class);
        $action->shouldReceive('getActionType')->andReturn('user.info');
        $action->shouldReceive('isEnabled')->andReturn(false);

        // 執行權限檢查
        $result = $this->permissionChecker->canExecuteAction($user, $action);

        // 驗證結果
        $this->assertFalse($result);
    }

    /**
     * 測試Action無權限要求時允許執行
     */
    public function test_can_execute_action_with_no_permission_requirements(): void
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'permissions' => [],
        ]);

        // 建立模擬Action（無權限要求）
        $action = Mockery::mock(ActionInterface::class);
        $action->shouldReceive('getActionType')->andReturn('system.ping');
        $action->shouldReceive('isEnabled')->andReturn(true);
        $action->shouldReceive('getRequiredPermissions')->andReturn([]);

        // 執行權限檢查
        $result = $this->permissionChecker->canExecuteAction($user, $action);

        // 驗證結果
        $this->assertTrue($result);
    }

    /**
     * 測試使用資料庫權限配置覆蓋Action預設權限
     */
    public function test_database_permission_config_overrides_action_permissions(): void
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'permissions' => ['admin.read'],
        ]);

        // 建立Action權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['admin.read'],
            'is_active' => true,
            'description' => '測試權限配置',
        ]);

        // 建立模擬Action（預設權限不同）
        $action = Mockery::mock(ActionInterface::class);
        $action->shouldReceive('getActionType')->andReturn('user.info');
        $action->shouldReceive('isEnabled')->andReturn(true);
        $action->shouldReceive('getRequiredPermissions')->andReturn(['user.read']);

        // 執行權限檢查
        $result = $this->permissionChecker->canExecuteAction($user, $action);

        // 驗證結果（應該使用資料庫配置的權限）
        $this->assertTrue($result);
    }

    /**
     * 測試檢查使用者是否具有指定權限
     */
    public function test_user_has_permissions(): void
    {
        // 建立測試使用者
        $user = User::factory()->create([
            'permissions' => ['user.read', 'user.update', 'admin.read'],
        ]);

        // 測試單一權限檢查
        $this->assertTrue($this->permissionChecker->userHasPermissions($user, ['user.read']));
        $this->assertFalse($this->permissionChecker->userHasPermissions($user, ['user.delete']));

        // 測試多重權限檢查
        $this->assertTrue($this->permissionChecker->userHasPermissions($user, ['user.read', 'user.update']));
        $this->assertFalse($this->permissionChecker->userHasPermissions($user, ['user.read', 'user.delete']));
    }

    /**
     * 測試取得Action權限配置
     */
    public function test_get_action_permission_config(): void
    {
        // 建立Action權限配置
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
            'description' => '取得使用者資訊權限',
        ]);

        // 取得權限配置
        $config = $this->permissionChecker->getActionPermissionConfig('user.info');

        // 驗證結果
        $this->assertNotNull($config);
        $this->assertEquals('user.info', $config['action_type']);
        $this->assertEquals(['user.read'], $config['required_permissions']);
        $this->assertTrue($config['is_active']);
        $this->assertEquals('取得使用者資訊權限', $config['description']);
    }

    /**
     * 測試取得不存在的Action權限配置
     */
    public function test_get_nonexistent_action_permission_config(): void
    {
        // 取得不存在的權限配置
        $config = $this->permissionChecker->getActionPermissionConfig('nonexistent.action');

        // 驗證結果
        $this->assertNull($config);
    }

    /**
     * 測試設定Action權限配置
     */
    public function test_set_action_permissions(): void
    {
        // 設定Action權限
        $permission = $this->permissionChecker->setActionPermissions(
            'user.info',
            ['user.read', 'user.update'],
            '使用者資訊權限'
        );

        // 驗證結果
        $this->assertInstanceOf(ActionPermission::class, $permission);
        $this->assertEquals('user.info', $permission->action_type);
        $this->assertEquals(['user.read', 'user.update'], $permission->required_permissions);
        $this->assertTrue($permission->is_active);
        $this->assertEquals('使用者資訊權限', $permission->description);

        // 驗證資料庫中的記錄
        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'user.info',
            'is_active' => true,
        ]);
    }

    /**
     * 測試更新現有Action權限配置
     */
    public function test_update_existing_action_permissions(): void
    {
        // 建立初始權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
            'description' => '初始權限',
        ]);

        // 更新權限配置
        $permission = $this->permissionChecker->setActionPermissions(
            'user.info',
            ['user.read', 'user.update'],
            '更新後的權限'
        );

        // 驗證結果
        $this->assertEquals(['user.read', 'user.update'], $permission->required_permissions);
        $this->assertEquals('更新後的權限', $permission->description);

        // 驗證資料庫中只有一筆記錄
        $this->assertEquals(1, ActionPermission::where('action_type', 'user.info')->count());
    }

    /**
     * 測試移除Action權限配置
     */
    public function test_remove_action_permissions(): void
    {
        // 建立權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 移除權限配置
        $result = $this->permissionChecker->removeActionPermissions('user.info');

        // 驗證結果
        $this->assertTrue($result);
        $this->assertDatabaseMissing('action_permissions', [
            'action_type' => 'user.info',
        ]);
    }

    /**
     * 測試移除不存在的Action權限配置
     */
    public function test_remove_nonexistent_action_permissions(): void
    {
        // 移除不存在的權限配置
        $result = $this->permissionChecker->removeActionPermissions('nonexistent.action');

        // 驗證結果
        $this->assertFalse($result);
    }

    /**
     * 測試取得所有Action權限配置
     */
    public function test_get_all_action_permissions(): void
    {
        // 建立多個權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        ActionPermission::create([
            'action_type' => 'user.update',
            'required_permissions' => ['user.update'],
            'is_active' => true,
        ]);

        ActionPermission::create([
            'action_type' => 'admin.delete',
            'required_permissions' => ['admin.delete'],
            'is_active' => false, // 停用的配置
        ]);

        // 取得所有權限配置
        $permissions = $this->permissionChecker->getAllActionPermissions();

        // 驗證結果（只包含啟用的配置）
        $this->assertCount(2, $permissions);
        $this->assertArrayHasKey('user.info', $permissions);
        $this->assertArrayHasKey('user.update', $permissions);
        $this->assertArrayNotHasKey('admin.delete', $permissions);
    }

    /**
     * 測試批量同步Action權限配置
     */
    public function test_sync_action_permissions(): void
    {
        // 準備同步資料
        $actionPermissions = [
            'user.info' => [
                'permissions' => ['user.read'],
                'description' => '取得使用者資訊',
                'is_active' => true,
            ],
            'user.update' => [
                'permissions' => ['user.update'],
                'description' => '更新使用者資訊',
                'is_active' => true,
            ],
            'admin.delete' => [
                'permissions' => ['admin.delete'],
                'description' => '刪除功能',
                'is_active' => false,
            ],
        ];

        // 執行同步
        $syncCount = $this->permissionChecker->syncActionPermissions($actionPermissions);

        // 驗證結果
        $this->assertEquals(3, $syncCount);

        // 驗證資料庫記錄
        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'user.info',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'admin.delete',
            'is_active' => false,
        ]);
    }

    /**
     * 測試記錄權限拒絕
     */
    public function test_log_permission_denied(): void
    {
        // 模擬Log facade
        Log::shouldReceive('warning')
            ->once()
            ->with('權限檢查失敗', Mockery::type('array'));

        // 建立測試使用者
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'permissions' => ['user.update'],
        ]);

        // 記錄權限拒絕
        $this->permissionChecker->logPermissionDenied(
            $user,
            'user.info',
            ['user.read']
        );

        // 驗證Log被呼叫（透過Mockery驗證）
        $this->assertTrue(true);
    }

    /**
     * 清理測試環境
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}