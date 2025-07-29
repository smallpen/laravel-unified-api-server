<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ActionPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ActionPermission模型單元測試
 */
class ActionPermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試建立ActionPermission
     */
    public function test_create_action_permission(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read', 'user.update'],
            'is_active' => true,
            'description' => '使用者資訊權限',
        ]);

        // 驗證結果
        $this->assertInstanceOf(ActionPermission::class, $permission);
        $this->assertEquals('user.info', $permission->action_type);
        $this->assertEquals(['user.read', 'user.update'], $permission->required_permissions);
        $this->assertTrue($permission->is_active);
        $this->assertEquals('使用者資訊權限', $permission->description);

        // 驗證資料庫記錄
        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'user.info',
            'is_active' => true,
        ]);
    }

    /**
     * 測試屬性類型轉換
     */
    public function test_attribute_casting(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => 1, // 整數
        ]);

        // 驗證類型轉換
        $this->assertIsArray($permission->required_permissions);
        $this->assertIsBool($permission->is_active);
        $this->assertInstanceOf(\Carbon\Carbon::class, $permission->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $permission->updated_at);
    }

    /**
     * 測試根據Action類型查詢
     */
    public function test_scope_for_action_type(): void
    {
        // 建立測試資料
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

        // 查詢特定Action類型
        $permissions = ActionPermission::forActionType('user.info')->get();

        // 驗證結果
        $this->assertCount(1, $permissions);
        $this->assertEquals('user.info', $permissions->first()->action_type);
    }

    /**
     * 測試查詢啟用的權限配置
     */
    public function test_scope_active(): void
    {
        // 建立測試資料
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        ActionPermission::create([
            'action_type' => 'user.update',
            'required_permissions' => ['user.update'],
            'is_active' => false,
        ]);

        // 查詢啟用的權限配置
        $activePermissions = ActionPermission::active()->get();

        // 驗證結果
        $this->assertCount(1, $activePermissions);
        $this->assertTrue($activePermissions->first()->is_active);
    }

    /**
     * 測試查詢停用的權限配置
     */
    public function test_scope_inactive(): void
    {
        // 建立測試資料
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        ActionPermission::create([
            'action_type' => 'user.update',
            'required_permissions' => ['user.update'],
            'is_active' => false,
        ]);

        // 查詢停用的權限配置
        $inactivePermissions = ActionPermission::inactive()->get();

        // 驗證結果
        $this->assertCount(1, $inactivePermissions);
        $this->assertFalse($inactivePermissions->first()->is_active);
    }

    /**
     * 測試檢查是否包含指定權限
     */
    public function test_has_permission(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read', 'user.update'],
            'is_active' => true,
        ]);

        // 測試權限檢查
        $this->assertTrue($permission->hasPermission('user.read'));
        $this->assertTrue($permission->hasPermission('user.update'));
        $this->assertFalse($permission->hasPermission('user.delete'));
    }

    /**
     * 測試新增權限到權限清單
     */
    public function test_add_permission(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 新增權限
        $result = $permission->addPermission('user.update');

        // 驗證結果
        $this->assertTrue($result);
        $this->assertTrue($permission->hasPermission('user.update'));
        $this->assertContains('user.update', $permission->required_permissions);

        // 測試新增重複權限
        $duplicateResult = $permission->addPermission('user.read');
        $this->assertFalse($duplicateResult);
    }

    /**
     * 測試從權限清單移除權限
     */
    public function test_remove_permission(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read', 'user.update'],
            'is_active' => true,
        ]);

        // 移除權限
        $result = $permission->removePermission('user.update');

        // 驗證結果
        $this->assertTrue($result);
        $this->assertFalse($permission->hasPermission('user.update'));
        $this->assertNotContains('user.update', $permission->required_permissions);

        // 測試移除不存在的權限
        $nonexistentResult = $permission->removePermission('user.delete');
        $this->assertFalse($nonexistentResult);
    }

    /**
     * 測試設定權限清單
     */
    public function test_set_permissions(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 設定新的權限清單
        $newPermissions = ['user.read', 'user.update', 'user.delete'];
        $permission->setPermissions($newPermissions);

        // 驗證結果
        $this->assertEquals($newPermissions, $permission->required_permissions);

        // 測試去重功能
        $duplicatePermissions = ['user.read', 'user.read', 'user.update'];
        $permission->setPermissions($duplicatePermissions);
        $this->assertEquals(['user.read', 'user.update'], $permission->required_permissions);
    }

    /**
     * 測試取得權限清單
     */
    public function test_get_permissions(): void
    {
        // 建立ActionPermission
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read', 'user.update'],
            'is_active' => true,
        ]);

        // 取得權限清單
        $permissions = $permission->getPermissions();

        // 驗證結果
        $this->assertEquals(['user.read', 'user.update'], $permissions);

        // 測試空權限清單
        $emptyPermission = ActionPermission::create([
            'action_type' => 'test.action',
            'required_permissions' => [],
            'is_active' => true,
        ]);

        $this->assertEquals([], $emptyPermission->getPermissions());
    }

    /**
     * 測試檢查權限配置是否啟用
     */
    public function test_is_active(): void
    {
        // 建立啟用的權限配置
        $activePermission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 建立停用的權限配置
        $inactivePermission = ActionPermission::create([
            'action_type' => 'user.update',
            'required_permissions' => ['user.update'],
            'is_active' => false,
        ]);

        // 驗證結果
        $this->assertTrue($activePermission->isActive());
        $this->assertFalse($inactivePermission->isActive());
    }

    /**
     * 測試啟用權限配置
     */
    public function test_activate(): void
    {
        // 建立停用的權限配置
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => false,
        ]);

        // 啟用權限配置
        $permission->activate();

        // 驗證結果
        $this->assertTrue($permission->is_active);
    }

    /**
     * 測試停用權限配置
     */
    public function test_deactivate(): void
    {
        // 建立啟用的權限配置
        $permission = ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 停用權限配置
        $permission->deactivate();

        // 驗證結果
        $this->assertFalse($permission->is_active);
    }

    /**
     * 測試根據Action類型查找權限配置
     */
    public function test_find_by_action_type(): void
    {
        // 建立測試資料
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        ActionPermission::create([
            'action_type' => 'user.update',
            'required_permissions' => ['user.update'],
            'is_active' => false, // 停用
        ]);

        // 查找啟用的權限配置
        $permission = ActionPermission::findByActionType('user.info');
        $this->assertNotNull($permission);
        $this->assertEquals('user.info', $permission->action_type);

        // 查找停用的權限配置（應該找不到）
        $inactivePermission = ActionPermission::findByActionType('user.update');
        $this->assertNull($inactivePermission);

        // 查找不存在的權限配置
        $nonexistentPermission = ActionPermission::findByActionType('nonexistent.action');
        $this->assertNull($nonexistentPermission);
    }

    /**
     * 測試建立或更新Action權限配置
     */
    public function test_create_or_update(): void
    {
        // 建立新的權限配置
        $permission = ActionPermission::createOrUpdate(
            'user.info',
            ['user.read'],
            '使用者資訊權限'
        );

        // 驗證結果
        $this->assertEquals('user.info', $permission->action_type);
        $this->assertEquals(['user.read'], $permission->required_permissions);
        $this->assertEquals('使用者資訊權限', $permission->description);
        $this->assertTrue($permission->is_active);

        // 更新現有的權限配置
        $updatedPermission = ActionPermission::createOrUpdate(
            'user.info',
            ['user.read', 'user.update'],
            '更新後的權限'
        );

        // 驗證結果
        $this->assertEquals($permission->id, $updatedPermission->id); // 同一筆記錄
        $this->assertEquals(['user.read', 'user.update'], $updatedPermission->required_permissions);
        $this->assertEquals('更新後的權限', $updatedPermission->description);

        // 驗證資料庫中只有一筆記錄
        $this->assertEquals(1, ActionPermission::where('action_type', 'user.info')->count());
    }

    /**
     * 測試取得所有啟用的Action權限配置
     */
    public function test_get_all_active(): void
    {
        // 建立測試資料
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
            'is_active' => false,
        ]);

        // 取得所有啟用的權限配置
        $activePermissions = ActionPermission::getAllActive();

        // 驗證結果
        $this->assertCount(2, $activePermissions);
        $activePermissions->each(function ($permission) {
            $this->assertTrue($permission->is_active);
        });
    }

    /**
     * 測試批量同步Action權限配置
     */
    public function test_sync_permissions(): void
    {
        // 建立初始資料
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 準備同步資料
        $actionPermissions = [
            'user.info' => [
                'permissions' => ['user.read', 'user.update'],
                'description' => '更新後的權限',
                'is_active' => true,
            ],
            'user.create' => [
                'permissions' => ['user.create'],
                'description' => '建立使用者權限',
                'is_active' => true,
            ],
            'admin.delete' => [
                'permissions' => ['admin.delete'],
                'description' => '刪除權限',
                'is_active' => false,
            ],
        ];

        // 執行同步
        $syncCount = ActionPermission::syncPermissions($actionPermissions);

        // 驗證結果
        $this->assertEquals(3, $syncCount);

        // 驗證更新的記錄
        $userInfo = ActionPermission::where('action_type', 'user.info')->first();
        $this->assertEquals(['user.read', 'user.update'], $userInfo->required_permissions);
        $this->assertEquals('更新後的權限', $userInfo->description);

        // 驗證新建的記錄
        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'user.create',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('action_permissions', [
            'action_type' => 'admin.delete',
            'is_active' => false,
        ]);
    }

    /**
     * 測試action_type唯一性約束
     */
    public function test_action_type_unique_constraint(): void
    {
        // 建立第一個權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read'],
            'is_active' => true,
        ]);

        // 嘗試建立重複的action_type（應該拋出例外）
        $this->expectException(\Illuminate\Database\QueryException::class);

        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.update'],
            'is_active' => true,
        ]);
    }
}