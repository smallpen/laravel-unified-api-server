<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ApiToken;
use App\Models\ActionPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * 權限系統整合測試
 * 
 * 測試權限系統在完整API流程中的運作
 */
class PermissionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試使用者具有權限時可以成功執行Action
     */
    public function test_user_can_execute_action_with_sufficient_permissions(): void
    {
        // 建立具有權限的使用者
        $user = User::factory()->create([
            'permissions' => ['user.read'],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 發送API請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ]);
    }

    /**
     * 測試使用者缺少權限時無法執行Action
     */
    public function test_user_cannot_execute_action_without_sufficient_permissions(): void
    {
        // 建立沒有權限的使用者
        $user = User::factory()->create([
            'permissions' => ['user.update'], // 沒有user.read權限
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 發送API請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS',
                ]);
    }

    /**
     * 測試資料庫權限配置覆蓋Action預設權限
     */
    public function test_database_permission_config_overrides_action_default(): void
    {
        // 建立使用者（只有admin.read權限）
        $user = User::factory()->create([
            'permissions' => ['admin.read'],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 建立Action權限配置（覆蓋預設的user.read權限）
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['admin.read'],
            'is_active' => true,
            'description' => '管理員可查看使用者資訊',
        ]);

        // 發送API請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應（應該成功，因為使用資料庫配置的權限）
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ]);
    }

    /**
     * 測試停用的Action權限配置
     */
    public function test_inactive_action_permission_config(): void
    {
        // 建立使用者
        $user = User::factory()->create([
            'permissions' => ['user.read'],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 建立停用的Action權限配置
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['admin.read'],
            'is_active' => false, // 停用
            'description' => '停用的權限配置',
        ]);

        // 發送API請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應（應該成功，因為停用的配置不會被使用，回到Action預設權限）
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ]);
    }

    /**
     * 測試管理員使用者的權限
     */
    public function test_admin_user_permissions(): void
    {
        // 建立管理員使用者
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => [
                'user.read',
                'user.update',
                'user.list',
                'admin.read',
                'admin.write',
                'system.read',
                'system.server_status',
            ],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Admin Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 測試多個需要不同權限的Action
        $testCases = [
            'user.info' => 200,
            'user.list' => 200,
            'system.info' => 200,
            'system.server_status' => 200,
        ];

        foreach ($testCases as $actionType => $expectedStatus) {
            $response = $this->postJson('/api/', [
                'action_type' => $actionType,
            ], [
                'Authorization' => 'Bearer ' . $plainToken,
            ]);

            $response->assertStatus($expectedStatus);
        }
    }

    /**
     * 測試一般使用者的權限限制
     */
    public function test_regular_user_permission_restrictions(): void
    {
        // 建立一般使用者
        $user = User::factory()->create([
            'is_admin' => false,
            'permissions' => [
                'user.read',
                'user.update',
                'user.change_password',
            ],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'User Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 測試允許的Action
        $allowedActions = [
            'user.info' => [
                'data' => ['action_type' => 'user.info'],
                'expected_status' => 200,
            ],
            'user.update' => [
                'data' => [
                    'action_type' => 'user.update',
                    'name' => '更新的名稱',
                ],
                'expected_status' => 200,
            ],
            'user.change_password' => [
                'data' => [
                    'action_type' => 'user.change_password',
                    'current_password' => 'password',
                    'new_password' => 'newpassword123',
                    'new_password_confirmation' => 'newpassword123',
                ],
                'expected_status' => 200,
            ],
        ];

        foreach ($allowedActions as $actionType => $config) {
            $response = $this->postJson('/api/', $config['data'], [
                'Authorization' => 'Bearer ' . $plainToken,
            ]);

            $response->assertStatus($config['expected_status']);
        }

        // 測試被拒絕的Action
        $deniedActions = [
            'user.list',
            'system.info',
            'system.server_status',
        ];

        foreach ($deniedActions as $actionType) {
            $response = $this->postJson('/api/', [
                'action_type' => $actionType,
            ], [
                'Authorization' => 'Bearer ' . $plainToken,
            ]);

            $response->assertStatus(403)
                    ->assertJson([
                        'status' => 'error',
                        'error_code' => 'INSUFFICIENT_PERMISSIONS',
                    ]);
        }
    }

    /**
     * 測試無權限要求的Action
     */
    public function test_action_with_no_permission_requirements(): void
    {
        // 建立沒有任何權限的使用者
        $user = User::factory()->create([
            'permissions' => [],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 發送API請求到無權限要求的Action
        $response = $this->postJson('/api/', [
            'action_type' => 'system.ping',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應（應該成功）
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ]);
    }

    /**
     * 測試動態權限配置更新
     */
    public function test_dynamic_permission_config_update(): void
    {
        // 建立使用者
        $user = User::factory()->create([
            'permissions' => ['user.read'],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 第一次請求（使用預設權限）
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(200);

        // 建立新的權限配置（需要admin.read權限）
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['admin.read'],
            'is_active' => true,
            'description' => '動態更新的權限配置',
        ]);

        // 第二次請求（應該被拒絕，因為權限配置已更新）
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS',
                ]);

        // 更新使用者權限
        $user->update([
            'permissions' => ['user.read', 'admin.read'],
        ]);

        // 第三次請求（應該成功）
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試多重權限要求
     */
    public function test_multiple_permission_requirements(): void
    {
        // 建立Action權限配置（需要多個權限）
        ActionPermission::create([
            'action_type' => 'user.info',
            'required_permissions' => ['user.read', 'admin.read'],
            'is_active' => true,
            'description' => '需要多重權限的配置',
        ]);

        // 測試只有部分權限的使用者
        $partialUser = User::factory()->create([
            'permissions' => ['user.read'], // 缺少admin.read
        ]);

        $partialToken = ApiToken::create([
            'user_id' => $partialUser->id,
            'token_hash' => hash('sha256', $partialPlainToken = Str::random(40)),
            'name' => 'Partial Token',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $partialPlainToken,
        ]);

        $response->assertStatus(403);

        // 測試具有所有權限的使用者
        $fullUser = User::factory()->create([
            'permissions' => ['user.read', 'admin.read'],
        ]);

        $fullToken = ApiToken::create([
            'user_id' => $fullUser->id,
            'token_hash' => hash('sha256', $fullPlainToken = Str::random(40)),
            'name' => 'Full Token',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $fullPlainToken,
        ]);

        $response->assertStatus(200);
    }

    /**
     * 測試權限檢查的日誌記錄
     */
    public function test_permission_check_logging(): void
    {
        // 建立沒有權限的使用者
        $user = User::factory()->create([
            'permissions' => [],
        ]);

        // 建立API Token
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken = Str::random(40)),
            'name' => 'Test Token',
            'expires_at' => now()->addDays(30),
        ]);

        // 模擬Log facade
        \Log::shouldReceive('warning')
            ->once()
            ->with('權限檢查失敗', \Mockery::type('array'));

        \Log::shouldReceive('debug')
            ->atLeast()
            ->once();

        \Log::shouldReceive('info')
            ->atLeast()
            ->once();

        // 發送API請求
        $response = $this->postJson('/api/', [
            'action_type' => 'user.info',
        ], [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        // 驗證回應
        $response->assertStatus(403);
    }
}